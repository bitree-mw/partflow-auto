# Low-stock email notifications

## What this feature does

PartFlow Auto can email one configured business recipient when the available quantity of a part at a branch falls to or below its low-stock level. A part that reaches zero is identified as out of stock in the email.

Available quantity is calculated as:

`quantity on hand - reserved quantity`

Notifications are generated from committed stock changes such as completed sales, completed transfers, approved stock adjustments, stock takes, and other movement-backed inventory operations. Opening the dashboard or alerts page never sends an email.

Email delivery runs asynchronously through Laravel's configured queue so mail-provider latency does not delay the stock or sales flow.

## Configure the recipient

1. Sign in as an administrator or another user with `settings.manage` permission.
2. Open **Settings**.
3. In **Business information → Business identity**, enter an address in **Low-stock notification email**.
4. Select **Save settings**.

Only one recipient address is supported. Leave the field blank and save to disable future low-stock emails.

## Configure outgoing mail

The server also needs Laravel mail delivery configured. Set the appropriate non-secret deployment environment values for your provider, for example:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-provider-username
MAIL_PASSWORD=your-provider-password
MAIL_SCHEME=smtp
MAIL_FROM_ADDRESS=notifications@example.com
MAIL_FROM_NAME="PartFlow Auto"
```

Use the exact host, port, security scheme, and credentials supplied by the email provider. Providers using implicit TLS commonly specify `smtps`; STARTTLS providers commonly use `smtp` and negotiate encryption. Do not commit real credentials to the repository. After changing cached production configuration, follow the deployment's normal Laravel configuration-cache refresh procedure.

The deployment must also use a persistent queue connection such as `database` and keep a queue worker running, for example under Supervisor or the hosting platform's process manager:

```shell
php artisan queue:work --tries=3 --timeout=30
```

Restart long-running workers after deploying application or mail configuration changes. The repository's `composer run dev` command already starts a development queue listener.

For local verification without sending real email, `MAIL_MAILER=log` writes the rendered message to the configured Laravel log.

## Notification rules

- Stock is evaluated separately for every part and branch.
- The email is sent when available stock decreases into the low-stock range.
- Repeated sales while the same part remains low do not generate repeated successful emails.
- Replenishing available stock above the threshold resets the cycle. A later fall into low stock can send a new email.
- A low-stock level of `0` disables the alert and email for that branch/part, matching the application's existing alert rule.
- If the notification address is blank, no email is attempted.
- If delivery fails, the stock transaction remains committed. The queue retries delivery up to three times, records a terminal failure, writes a diagnostic to the Laravel log, and leaves the notification eligible to retry on a later stock decrease.

## Quick test

1. Configure a test recipient and working mail delivery.
2. Choose a part with a positive low-stock level, such as `5`, and available stock above it, such as `8`.
3. Complete a sale or approved stock adjustment that reduces available stock to `5` or lower.
4. Confirm the recipient receives an email containing the part, branch, on-hand, reserved, available, and low-stock quantities.
5. Make another sale while the part remains low and confirm no duplicate successful email is sent.
6. Replenish the part above `5`, then reduce it to `5` or lower again and confirm a new email is sent.

## Troubleshooting

- Confirm the Business Information address is saved and valid.
- Confirm the branch-specific part threshold is greater than zero.
- Check the deployment's Laravel log for `Low-stock email delivery failed.`
- Check `php artisan queue:failed` for terminally failed mail jobs and confirm a queue worker is continuously running.
- Verify the mail provider allows the configured sender address and that the server can connect to the provider.
- Confirm `APP_URL` is correct so the **Review stock alerts** link in the email opens the right installation.
