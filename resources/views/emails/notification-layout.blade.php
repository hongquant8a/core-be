<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; padding:0; background:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#333; line-height:1.6; }
        .wrapper { max-width:600px; margin:0 auto; padding:40px 20px; }
        .card { background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; }
        .header { background:#2563eb; padding:24px 32px; text-align:center; }
        .header h1 { color:#fff; font-size:20px; font-weight:600; margin:0; }
        .body { padding:32px; }
        .body p { margin:0 0 12px; font-size:15px; }
        .footer { padding:20px 32px; text-align:center; font-size:12px; color:#9ca3af; border-top:1px solid #f0f0f0; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="header"><h1>{{ $subjectText ?? 'Thông báo' }}</h1></div>
            <div class="body">@yield('body')</div>
            <div class="footer">{{ config('app.name', 'Hệ thống') }} — Email tự động, vui lòng không trả lời.</div>
        </div>
    </div>
</body>
</html>
