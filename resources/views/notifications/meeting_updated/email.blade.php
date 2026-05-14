@extends('emails.notification-layout', [
    'subjectText' => 'Cập nhật cuộc họp',
    'accentColor' => '#D97706',
    'accentLabel' => 'CẬP NHẬT CUỘC HỌP',
])

@section('body')
    <p>Trân trọng thông báo, cuộc họp <strong>{{ $meeting->title }}</strong> vừa có thông tin được cập nhật. Vui lòng xem lại chi tiết bên dưới.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin cuộc họp (đã cập nhật)</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tiêu đề</td>
            <td class="info-value">{{ $meeting->title }}</td>
        </tr>
        @if($meeting->start_time)
            <tr>
                <td class="info-label">Thời gian bắt đầu</td>
                <td class="info-value">{{ $meeting->start_time->format('H:i') }} ngày {{ $meeting->start_time->format('d/m/Y') }}</td>
            </tr>
        @endif
        @if($meeting->end_time)
            <tr>
                <td class="info-label">Thời gian kết thúc dự kiến</td>
                <td class="info-value">{{ $meeting->end_time->format('H:i') }} ngày {{ $meeting->end_time->format('d/m/Y') }}</td>
            </tr>
        @endif
        @if($meeting->meetingLocation?->name)
            <tr>
                <td class="info-label">Địa điểm</td>
                <td class="info-value">{{ $meeting->meetingLocation->name }}</td>
            </tr>
        @endif
        @if($meeting->meetingType?->name)
            <tr>
                <td class="info-label">Loại cuộc họp</td>
                <td class="info-value">{{ $meeting->meetingType->name }}</td>
            </tr>
        @endif
        @if($meeting->content)
            <tr>
                <td class="info-label">Nội dung</td>
                <td class="info-value">{!! strip_tags($meeting->content, '<p><br><strong><b><em><i><u><ol><ul><li><a><span><div>') !!}</td>
            </tr>
        @endif
    </table>

    <p class="action-note">Xem chi tiết tại: <a href="{{ $url }}">{{ $url }}</a></p>
@endsection
