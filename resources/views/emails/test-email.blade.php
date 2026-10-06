<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Test email</title>
</head>
<body style="margin:0;background:#f4f7fb;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #dfe6ef;border-radius:12px;overflow:hidden;">
            <div style="padding:24px 28px;background:#172033;color:#ffffff;">
                <div style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#c7d2e3;">{{ $details['business_name'] }}</div>
                <h1 style="margin:8px 0 0;font-size:24px;line-height:1.3;">Email delivery is working</h1>
            </div>

            <div style="padding:28px;">
                <p style="margin:0 0 16px;line-height:1.6;">
                    This test was sent from the PartFlow Auto super admin console on {{ $details['sent_at'] }}.
                </p>
                <p style="margin:0 0 24px;line-height:1.6;">
                    Low-stock alerts and scheduled reminders use these same mail settings. They are sent in the background, so the server's cron job must also be running.
                </p>

                <a href="{{ $details['console_url'] }}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#f97316;color:#ffffff;text-decoration:none;font-weight:bold;">Open console</a>
            </div>
        </div>
    </div>
</body>
</html>
