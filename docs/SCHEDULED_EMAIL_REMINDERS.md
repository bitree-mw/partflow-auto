# Scheduled business email reminders

## Monday stock digest

`reminders:stock` runs every Monday at 08:00 in the configured application timezone (Africa/Blantyre for this installation). It queues one internal digest to the address saved in Business Information under Low-stock notification email. No customer or supplier is emailed directly.

The digest includes every active, non-deleted part/branch stock row whose available quantity is at or below its positive effective threshold. An explicit branch threshold of zero disables that row; a null branch threshold falls back to the product default. Out-of-stock rows are included. There is no dashboard-style 25-row limit.

This reminder is independent of `low_stock_notified_at`: an unchanged part can appear again next Monday without needing another sale or replenishment. The existing immediate stock-change notification is preserved.

At delivery time the worker checks stock again, excluding replenished or deactivated records. Blank recipients and empty digests produce no email. An old weekly run is skipped after its week ends. Re-running the command in the same week cannot resend a successful digest.

## Queue and delivery status

The scheduler only queues `SendScheduledEmailReminder`; SMTP runs in the background worker. Synchronous, deferred, and null queue drivers are rejected. The job allows three attempts, waits 60 then 300 seconds between delivery failures, and has a 60-second worker timeout (keep the queue connection's `retry_after` greater than this).

`scheduled_email_reminders` records the type, period, queued/sent/skipped/failed state, item count, mail-acceptance timestamp, and failure class. Its unique type/period key prevents duplicate scheduled runs. The queue middleware serializes reminder delivery to prevent concurrent duplicate jobs from sending the same digest.

`sent` means the mail transport accepted the message, not inbox delivery. SMTP delivery is at-least-once: a process crash after the provider accepts mail but before the database records success can result in a duplicate on retry. Failure details remain available in Laravel's failed-job store; application logs include reminder IDs rather than stock or recipient data.

## Operation

For cPanel shared hosting, use the opt-in minute-based cron worker described in
[cPanel deployment](CPANEL_DEPLOYMENT.md#emails-using-cpanel-cron). It processes the
same persistent database queue without a continuous worker process.

Run any pending migration through the normal deployment procedure. On hosts with
process supervision, run the queue worker and scheduler as services:

```shell
php artisan queue:work --sleep=3 --tries=3 --timeout=60
php artisan schedule:work
```

For production, supervise both processes as services, or run `php artisan schedule:run` once per minute using the host's scheduler. A manually started local process is not a reboot-persistent service. Restart workers after deployments/configuration changes. Do not run multiple unmanaged scheduler instances.

Read-only checks:

```shell
php artisan schedule:list
php artisan reminders:stock --dry-run
php artisan queue:failed
```

## Payment reminders awaiting business-rule confirmation

The existing schema has no invoice due-date field. A 14-day inactivity reminder can use completed documents with a positive outstanding balance, but inclusion of customer sales versus supplier purchases and which actions reset the clock require confirmation before implementation. No payment reminder is currently scheduled by this change.
