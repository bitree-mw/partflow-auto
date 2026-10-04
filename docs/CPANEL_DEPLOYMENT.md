# cPanel deployment and database transfer

## Existing database import (the selected deployment path)

Keep `database/migrations` unchanged and copy the **complete schema and data,
including the migrations table**. There are 46 historical migration files through
2026-09-15. Renaming or replacing them would make Laravel treat old work as pending;
some historical migrations transform product codes and merge vehicle fitments.

1. Rehearse a restore into a separate staging database before cutover. Record the
   source table row counts, product codes, per-site stock totals, movement counts,
   document/payment totals and outstanding balances for reconciliation.
2. During the final export, stop source writes and scheduling; let outstanding mail
   jobs finish, then stop its worker. Export a consistent full database backup using
   your database tool. Preserve IDs, foreign keys, decimal values, timestamps and
   migration records. Keep backups outside the web root.
3. Create an empty cPanel database/user with access to that database. Import the
   full backup through phpMyAdmin or the host's supported import tool. Do not run
   migrations or seeders to pre-create tables before importing the full backup.
4. Upload the application outside `public_html` and point the domain document root
   at its `public` directory. Upload built `public/build` assets. Install the locked
   production Composer dependencies; do not run `composer run setup` because it
   also migrates automatically. Use PHP 8.4 with the extensions required by the lock
   file; verify both the web and cron PHP versions.
5. Configure the target environment privately: production mode, debug off, HTTPS
   `APP_URL`, target database credentials, and the existing `APP_KEY` transferred
   securely. Keep `.env`, `vendor`, application source and backups outside the public
   document root. Make `storage` and `bootstrap/cache` writable by the application.
6. Refresh configuration with `php artisan config:cache`. Inspect
   `php artisan migrate:status`; only run `php artisan migrate --force` during an
   approved deployment after reviewing genuine pending migrations. Do not run
   `migrate:fresh`, reset/refresh, baseline installation, or demo/catalogue seeders
   against the imported database.
7. Reconcile the recorded counts and balances. Verify login, site permissions,
   catalogue, stock, sales and reports in staging. Activate the target cron only
   after disabling the source cron/worker so mail is not sent from both copies.

If pending jobs remain in the export, process them on one installation only. Keep
`scheduled_email_reminders` and `low_stock_notified_at` state so already-sent mail
is not intentionally repeated. SMTP can still duplicate a message after a crash
between provider acceptance and recording success.

## Database compatibility

Run this read-only query on source and target via your database administrator tool:

```sql
SELECT VERSION() AS server_version,
       @@character_set_database AS database_charset,
       @@collation_database AS database_collation;
```

The local metadata check on 2026-10-04 returned MySQL 9.7.1, `utf8mb4`, and
`utf8mb4_0900_ai_ci`. The application config defaults to `utf8mb4` /
`utf8mb4_unicode_ci`. Older MySQL or
MariaDB hosts may reject a source dump using `utf8mb4_0900_ai_ci`. Confirm supported
collations with the host and rehearse the export/import conversion in staging;
do not blindly replace strings in a live SQL dump. Check uniqueness under the
target collation. cPanel's server version and imported-data compatibility have not
been verified by the repository's SQLite tests.

## Grouped schema baseline (empty databases only)

`database/baseline` combines the final schema into six dependency-ordered groups:

| Group | Contents |
| --- | --- |
| 01 | Roles, users, sessions, password reset tokens, Sanctum tokens |
| 02 | Sites, user site access, business settings, contacts |
| 03 | Makes, vehicle models, fitments, product types, brands, fuels, tax, products and references |
| 04 | Inventory documents/items, site balances, stock movements |
| 05 | Payment accounts, payments, expense categories and expenses |
| 06 | Cache/locks, queued/batched/failed jobs, scheduled reminders |

Later column additions and table renames are folded into their final definitions.
Historical data conversions are omitted because this installer requires an empty
database. This is a schema installer, not a data importer.

For a new empty development/staging database, use:

```shell
php artisan schema:install-baseline
php artisan migrate
```

The installer refuses any existing table or view, including a migrations table.
After all groups succeed, it records the 46 covered historical migration names as
batch 1. Normal `migrate` then runs only newer migrations. Use the command rather
than `migrate --path=database/baseline`. Do not edit the frozen manifest to include
new migrations: add future changes to `database/migrations` normally. Keep the
historical files for imported databases and migration history.

Run the installer once, with no concurrent installer/application process. MySQL
DDL cannot be rolled back as one transaction: an interrupted baseline may leave
partial tables and deliberately refuses to rerun. Inspect it and use a new empty
database for a retry. Never use rollback/reset as a data-transfer strategy; the
historical batch includes irreversible data transformations. Production execution
also requires Laravel's normal confirmation or the explicit `--force` option.

## Emails using cPanel cron

Both current asynchronous features use the database queue:

- Low-stock alerts are queued after committed stock changes.
- The weekly stock digest is queued Monday at 08:00 in the application timezone.

No payment reminders are implemented; due-date and recipient rules remain undefined.
No Redis, Supervisor, new package or continuous worker is required for this cron
mode. Laravel's database queue retains retries and terminal failures.

Set these environment values privately on cPanel:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=120
CACHE_STORE=database
CPANEL_CRON_QUEUE=true
MAIL_MAILER=smtp
MAIL_TIMEOUT=20
```

Set `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
`MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` from your provider's settings. Use the
provider's prescribed `smtp`/STARTTLS or `smtps`/implicit TLS combination. The SMTP
socket timeout defaults to 20 seconds. Select the internal recipient in
**Settings → Business information → Low-stock notification email**. Keep
`QUEUE_CONNECTION=database` when cron mode is enabled: the scheduled worker
explicitly consumes that connection's configured queue (`DB_QUEUE`, default
`default`). Keep `CACHE_STORE=database` for shared overlap locks.

Refresh configuration after setting these values. In **cPanel → Cron Jobs**, use
`* * * * *` (every minute) and this command, replacing both absolute paths:

```shell
/usr/local/bin/php /home/ACCOUNT/partflow-auto/artisan schedule:run >> /home/ACCOUNT/partflow-auto/storage/logs/cron-scheduler.log 2>&1
```

Ask the host for the actual PHP 8.4 CLI binary; `/usr/local/bin/php` is only an
example. The scheduler uses its CLI PHP binary for child commands. Confirm the
host permits once-per-minute cron and process execution, and that CLI PHP has
`pcntl` for queue job timeouts. Configure log rotation for both cron logs.

The scheduler invokes `queue:work database` with `--stop-when-empty`,
`--max-time=45`, `--max-jobs=50`, `--sleep=1`, `--tries=3`, `--timeout=60`, and
`--backoff=60`. Per-job settings retain the existing 30/60-second timeouts and
60/300-second retry backoffs. `max-time` is checked between jobs, so a run can take
longer than 45 seconds while finishing its current job. A shared five-minute
overlap lock skips concurrent ticks and expires after a crashed process.
The host must allow enough execution time to finish a job. Keep `retry_after`
greater than the longest job timeout. Do not also run an unmanaged queue worker
or a second scheduler for this deployment.

Expect up to one cron interval before an email starts, plus backlog and retry
delays. Monitor backlog if volume exceeds what these short runs can process.
`CPANEL_CRON_QUEUE=false` (the default) retains supervised-worker deployments.

## Verification and recovery

```shell
php artisan schedule:list
php artisan reminders:stock --dry-run
php artisan queue:failed
```

Confirm the minute worker and Monday digest appear in `schedule:list`. Check
`storage/logs/cron-scheduler.log`, `cron-queue.log`, and the configured Laravel log.
Use a staging SMTP mailbox for a delivery test; the automated tests use an in-memory
mail transport and do not prove cPanel SMTP connectivity or inbox delivery.

If jobs accumulate, check cron execution, PHP path/extensions, queue connection,
cache locks and SMTP settings. Resolve the cause before retrying a specific failed
job using `php artisan queue:retry JOB_UUID`. Do not indiscriminately retry all
mail: low-stock messages retain their original snapshot, and retrying after an
uncertain delivery may duplicate an email. A missed Monday 08:00 tick does not
catch up automatically; after review, `php artisan reminders:stock` can queue that
week's digest on Monday, with the existing type/period duplicate protection.

References: [Laravel queue workers](https://laravel.com/docs/12.x/queues#running-the-queue-worker),
[Laravel scheduler](https://laravel.com/docs/12.x/scheduling#running-the-scheduler),
[cPanel Cron Jobs](https://docs.cpanel.net/cpanel/advanced/cron-jobs/).
