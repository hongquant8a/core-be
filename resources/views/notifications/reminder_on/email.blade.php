@extends('emails.notification-layout', [
    'subjectText' => 'Công việc đến hạn hôm nay',
])

@section('body')
    <p>Xin chào <strong>{{ $recipient->name }}</strong>,</p>
    <p>Công việc <strong>{{ $item->name }}</strong> đã đến hạn hoàn thành.</p>
    <table style="width:100%; font-size:14px; border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:6px 8px; color:#6b7280; width:35%;">Tên công việc</td><td style="padding:6px 8px; font-weight:500;">{{ $item->name }}</td></tr>
        @if($item->end_at)
            <tr><td style="padding:6px 8px; color:#6b7280;">Hạn</td><td style="padding:6px 8px; font-weight:500;">{{ $item->end_at->format('H:i d/m/Y') }}</td></tr>
        @endif
        <tr><td style="padding:6px 8px; color:#6b7280;">Tiến độ</td><td style="padding:6px 8px;">{{ $item->completion_percent }}%</td></tr>
    </table>
    <p>Vui lòng hoàn thành và báo cáo trong hôm nay.</p>
@endsection
