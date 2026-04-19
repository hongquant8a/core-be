@php
    $accent = $accentColor ?? '#C8102E';
    $label = $accentLabel ?? null;
    $greetName = trim((string) ($recipient->name ?? ''));
    $greeting = $greeting ?? ('Kính gửi Ông/Bà '.($greetName !== '' ? $greetName : 'Quý vị'));
    $closing = $closing ?? 'Trân trọng./.';
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectText ?? 'Thông báo' }}</title>
    <style>
        body { margin:0; padding:0; background:#F5F1EA; font-family:'Times New Roman','Source Serif Pro',Georgia,'DejaVu Serif',serif; color:#1F2937; line-height:1.65; -webkit-font-smoothing:antialiased; }
        table { border-collapse:collapse; }
        img { border:0; display:block; max-width:100%; }
        a { color:#0B4F9C; }
        .wrapper { width:100%; background:#F5F1EA; padding:32px 16px; }
        .container { max-width:640px; margin:0 auto; }
        .ribbon-top, .ribbon-bot { height:5px; background:#C8102E; font-size:0; line-height:0; }
        .ribbon-gold { height:2px; background:#F4C20D; font-size:0; line-height:0; }
        .card { background:#FFFFFF; border:1px solid #E7DFD1; border-top:0; border-bottom:0; box-shadow:0 1px 4px rgba(0,0,0,0.04); }
        .header { padding:24px 32px 20px; }
        .header-logo { width:64px; height:64px; vertical-align:top; }
        .header-logo-fallback { width:64px; height:64px; background:#C8102E; color:#FFFFFF; text-align:center; vertical-align:middle; font-size:24px; font-weight:700; letter-spacing:2px; font-family:Georgia,serif; }
        .header-meta { padding-left:20px; vertical-align:middle; }
        .header-appname { font-size:13px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#6B7280; margin:0 0 6px; }
        .header-divider { width:40px; height:2px; background:#F4C20D; margin:6px 0 10px; font-size:0; line-height:0; }
        .header-title { font-size:20px; font-weight:700; color:#C8102E; margin:0; line-height:1.3; }
        .header-label { display:inline-block; margin-top:10px; padding:3px 10px; font-size:11px; letter-spacing:1.5px; text-transform:uppercase; color:#FFFFFF; font-family:Arial,sans-serif; font-weight:700; }
        .body { padding:28px 40px 8px; border-left:4px solid #C8102E; }
        .body p { margin:0 0 14px; font-size:15px; }
        .greeting { font-size:15px; margin:0 0 16px; }
        .info-title { font-size:12px; letter-spacing:2px; text-transform:uppercase; color:#C8102E; font-weight:700; margin:22px 0 0; padding-bottom:6px; border-bottom:1px solid #C8102E; font-family:Arial,sans-serif; }
        .info-title-dot { display:inline-block; width:6px; height:6px; background:#C8102E; margin-right:8px; vertical-align:middle; }
        .info-table { width:100%; font-size:14px; border-bottom:1px double #C8102E; margin:0 0 18px; }
        .info-table td { padding:8px 10px; vertical-align:top; border-bottom:1px dotted #E7DFD1; }
        .info-table tr:last-child td { border-bottom:0; }
        .info-label { color:#6B7280; width:38%; font-family:Arial,sans-serif; font-size:13px; }
        .info-value { color:#1F2937; font-weight:500; }
        .action-note { font-style:italic; color:#4B5563; margin:18px 0 6px; font-size:14px; }
        .closing { text-align:right; font-weight:700; margin:24px 0 4px; font-size:15px; }
        .footer { padding:18px 32px 22px; background:#FAF7F1; text-align:center; font-size:12px; color:#6B7280; font-family:Arial,sans-serif; }
        .footer-copyright { margin:0 0 4px; }
        .footer-auto { margin:0; font-style:italic; }
        @media only screen and (max-width:480px) {
            .wrapper { padding:16px 8px; }
            .header { padding:20px 20px 16px; }
            .body { padding:22px 22px 6px; }
            .footer { padding:16px 20px 18px; }
            .header-title { font-size:18px; }
            .info-label { width:45%; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" class="container" width="100%" cellpadding="0" cellspacing="0">
            <tr><td class="ribbon-top">&nbsp;</td></tr>
            <tr>
                <td class="card">
                    <div class="header">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                @if(!empty($logoUrl))
                                    <td width="64" style="width:64px;">
                                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" class="header-logo" width="64" height="64">
                                    </td>
                                @else
                                    <td width="64" style="width:64px;">
                                        <div class="header-logo-fallback">{{ mb_strtoupper(mb_substr($appName, 0, 1, 'UTF-8'), 'UTF-8') }}</div>
                                    </td>
                                @endif
                                <td class="header-meta">
                                    <div class="header-appname">{{ $appName }}</div>
                                    <div class="header-divider">&nbsp;</div>
                                    <h1 class="header-title">{{ $subjectText ?? 'Thông báo' }}</h1>
                                    @if(!empty($label))
                                        <span class="header-label" style="background:{{ $accent }};">{{ $label }}</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="ribbon-gold">&nbsp;</div>
                    <div class="body" style="border-left-color:{{ $accent }};">
                        <p class="greeting">{{ $greeting }},</p>

                        @yield('body')

                        <p class="closing">{{ $closing }}</p>
                    </div>
                    <div class="footer">
                        @if(!empty($copyright))
                            <p class="footer-copyright">{{ $copyright }}</p>
                        @endif
                        <p class="footer-auto">Thông báo tự động từ hệ thống — vui lòng không trả lời email này.</p>
                    </div>
                </td>
            </tr>
            <tr><td class="ribbon-gold">&nbsp;</td></tr>
            <tr><td class="ribbon-bot">&nbsp;</td></tr>
        </table>
    </div>
</body>
</html>
