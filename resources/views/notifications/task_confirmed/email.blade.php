@extends('emails.notification-layout', [
    'subjectText' => 'Công việc đã được xác nhận',
])

@section('body')
    <p>Xin chào <strong>{{ $recipient->name }}</strong>,</p>
    <p>Công việc <strong>{{ $item->name }}</strong> đã được xác nhận hoàn thành.</p>
    <table style="width:100%; font-size:14px; border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:6px 8px; color:#6b7280; width:35%;">Tên công việc</td><td style="padding:6px 8px; font-weight:500;">{{ $item->name }}</td></tr>
        @if($item->confirmed_at)
            <tr><td style="padding:6px 8px; color:#6b7280;">Xác nhận lúc</td><td style="padding:6px 8px;">{{ $item->confirmed_at->format('H:i d/m/Y') }}</td></tr>
        @endif
    </table>
    <p>Cảm ơn bạn đã hoàn thành công việc.</p>
@endsection
