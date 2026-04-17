@extends('emails.notification-layout', [
    'subjectText' => 'Công việc chờ xác nhận',
])

@section('body')
    <p>Xin chào <strong>{{ $recipient->name }}</strong>,</p>
    <p>Công việc <strong>{{ $item->name }}</strong> đã được báo cáo hoàn thành và chờ bạn xác nhận.</p>
    <table style="width:100%; font-size:14px; border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:6px 8px; color:#6b7280; width:35%;">Tên công việc</td><td style="padding:6px 8px; font-weight:500;">{{ $item->name }}</td></tr>
        @if($item->completed_at)
            <tr><td style="padding:6px 8px; color:#6b7280;">Báo cáo lúc</td><td style="padding:6px 8px;">{{ $item->completed_at->format('H:i d/m/Y') }}</td></tr>
        @endif
        <tr><td style="padding:6px 8px; color:#6b7280;">Tiến độ</td><td style="padding:6px 8px;">{{ $item->completion_percent }}%</td></tr>
    </table>
    <p>Vui lòng truy cập hệ thống để xác nhận.</p>
@endsection
