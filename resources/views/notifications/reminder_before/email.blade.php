@extends('emails.notification-layout', [
    'subjectText' => 'Công việc sắp đến hạn',
])

@section('body')
    <p>Xin chào <strong>{{ $recipient->name }}</strong>,</p>
    <p>Công việc <strong>{{ $item->name }}</strong> sắp đến hạn hoàn thành.</p>
    <table style="width:100%; font-size:14px; border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:6px 8px; color:#6b7280; width:35%;">Tên công việc</td><td style="padding:6px 8px; font-weight:500;">{{ $item->name }}</td></tr>
        @if($item->end_at)
            <tr><td style="padding:6px 8px; color:#6b7280;">Hạn hoàn thành</td><td style="padding:6px 8px; font-weight:500;">{{ $item->end_at->format('H:i d/m/Y') }}</td></tr>
        @endif
        <tr><td style="padding:6px 8px; color:#6b7280;">Tiến độ hiện tại</td><td style="padding:6px 8px;">{{ $item->completion_percent }}%</td></tr>
    </table>
    <p>Vui lòng sắp xếp hoàn thành trước hạn.</p>
@endsection
