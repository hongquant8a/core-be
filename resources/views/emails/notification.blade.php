<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f5f7;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .header {
            background: #2563eb;
            padding: 24px 32px;
            text-align: center;
        }
        .header h1 {
            color: #fff;
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        .body {
            padding: 32px;
        }
        .body p {
            margin: 0 0 16px;
            font-size: 15px;
        }
        .content-block {
            background: #f8fafc;
            border-left: 4px solid #2563eb;
            padding: 16px 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
            font-size: 15px;
            white-space: pre-line;
        }
        .footer {
            padding: 20px 32px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            border-top: 1px solid #f0f0f0;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header">
                <h1>{{ $subject ?? 'Thông báo' }}</h1>
            </div>
            <div class="body">
                @if(!empty($recipientName))
                    <p>Xin chào <strong>{{ $recipientName }}</strong>,</p>
                @endif

                <div class="content-block">{!! nl2br(e($content)) !!}</div>

                @if(!empty($context))
                    <table style="width:100%; font-size:14px; border-collapse:collapse; margin-top:16px;">
                        @foreach($context as $key => $value)
                            <tr>
                                <td style="padding:6px 8px; color:#6b7280; width:40%;">{{ $key }}</td>
                                <td style="padding:6px 8px; font-weight:500;">{{ is_array($value) ? json_encode($value) : $value }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
            <div class="footer">
                {{ $senderName ?? config('app.name', 'System') }} &mdash; Email tự động, vui lòng không trả lời.
            </div>
        </div>
    </div>
</body>
</html>
