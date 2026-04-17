@extends('emails.notification-layout', [
    'subjectText' => 'Bạn được giao công việc mới',
])

@section('body')
    <p>Xin chào <strong>{{ $recipient->name }}</strong>,</p>
    <p>Văn bản giao việc <strong>{{ $document?->name }}</strong> đã được ban hành. Bạn được giao công việc sau:</p>
    <table style="width:100%; font-size:14px; border-collapse:collapse; margin:12px 0;">
        <tr><td style="padding:6px 8px; color:#6b7280; width:35%;">Tên công việc</td><td style="padding:6px 8px; font-weight:500;">{{ $item->name }}</td></tr>
        @if($item->end_at)
            <tr><td style="padding:6px 8px; color:#6b7280;">Hạn hoàn thành</td><td style="padding:6px 8px; font-weight:500;">{{ $item->end_at->format('H:i d/m/Y') }}</td></tr>
        @endif
        @if($item->description)
            <tr><td style="padding:6px 8px; color:#6b7280; vertical-align:top;">Mô tả</td><td style="padding:6px 8px;">{!! nl2br(e($item->description)) !!}</td></tr>
        @endif
    </table>
    <p>Vui lòng truy cập hệ thống để xem chi tiết và thực hiện.</p>
@endsection
