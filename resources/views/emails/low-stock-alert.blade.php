<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $alert['available_quantity'] === 0 ? 'Out of stock' : 'Low stock' }}</title>
</head>
<body style="margin:0;background:#f4f7fb;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:640px;margin:0 auto;padding:32px 16px;">
        <div style="background:#ffffff;border:1px solid #dfe6ef;border-radius:12px;overflow:hidden;">
            <div style="padding:24px 28px;background:#172033;color:#ffffff;">
                <div style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#c7d2e3;">{{ $alert['business_name'] }}</div>
                <h1 style="margin:8px 0 0;font-size:24px;line-height:1.3;">
                    {{ $alert['available_quantity'] === 0 ? 'Part is out of stock' : 'Part has reached low stock' }}
                </h1>
            </div>

            <div style="padding:28px;">
                <p style="margin:0 0 20px;line-height:1.6;">
                    <strong>{{ $alert['product_code'] }} — {{ $alert['product_name'] }}</strong>
                    needs attention at <strong>{{ $alert['site_name'] }}</strong>.
                </p>

                <table role="presentation" style="width:100%;border-collapse:collapse;margin-bottom:24px;">
                    <tr><td style="padding:10px;border-bottom:1px solid #e7ebf0;">Available</td><td style="padding:10px;border-bottom:1px solid #e7ebf0;text-align:right;font-weight:bold;">{{ number_format($alert['available_quantity']) }}</td></tr>
                    <tr><td style="padding:10px;border-bottom:1px solid #e7ebf0;">On hand</td><td style="padding:10px;border-bottom:1px solid #e7ebf0;text-align:right;">{{ number_format($alert['quantity_on_hand']) }}</td></tr>
                    <tr><td style="padding:10px;border-bottom:1px solid #e7ebf0;">Reserved</td><td style="padding:10px;border-bottom:1px solid #e7ebf0;text-align:right;">{{ number_format($alert['reserved_quantity']) }}</td></tr>
                    <tr><td style="padding:10px;">Low-stock level</td><td style="padding:10px;text-align:right;">{{ number_format($alert['low_stock_level']) }}</td></tr>
                </table>

                <a href="{{ $alert['alerts_url'] }}" style="display:inline-block;padding:12px 18px;border-radius:8px;background:#f97316;color:#ffffff;text-decoration:none;font-weight:bold;">Review stock alerts</a>
            </div>
        </div>
    </div>
</body>
</html>
