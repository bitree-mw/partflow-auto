<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reminder['subject'] }}</title>
</head>
<body style="margin:0;background:#f4f7fb;color:#172033;font-family:Arial,Helvetica,sans-serif;">
    <div style="max-width:960px;margin:0 auto;padding:24px 16px;">
        <div style="padding:24px;background:#172033;color:#ffffff;">
            <p>{{ $reminder['business_name'] }}</p>
            <h1 style="font-size:24px;">{{ $reminder['heading'] }}</h1>
        </div>
        <div style="padding:24px;background:#ffffff;">
            <p>{{ $reminder['description'] }}</p>
            <p>Prepared {{ $reminder['prepared_at'] }}. {{ $reminder['count_label'] }}</p>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        @foreach ($reminder['columns'] as $column)
                            <th scope="col" style="padding:10px;text-align:left;border-bottom:2px solid #dfe6ef;">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reminder['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td style="padding:10px;border-bottom:1px solid #dfe6ef;">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p><a href="{{ $reminder['review_url'] }}">{{ $reminder['review_label'] }}</a></p>
            <p>This is an internal business reminder. Values reflect the records when this message was prepared.</p>
        </div>
    </div>
</body>
</html>
