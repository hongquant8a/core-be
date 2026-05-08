@extends('emails.notification-layout', [
    'subjectText' => 'Mời tham dự cuộc họp',
    'accentColor' => '#0B4F9C',
    'accentLabel' => 'GIẤY MỜI HỌP',
])

@section('body')
    <p>Trân trọng kính mời Quý vị tham dự cuộc họp <strong>{{ $meeting->title }}</strong>.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin cuộc họp</div>
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
                <td class="info-value">{!! nl2br(e($meeting->content)) !!}</td>
            </tr>
        @endif
    </table>

    <p class="action-note">Đề nghị Quý vị xác nhận tham dự trên hệ thống và sắp xếp thời gian dự họp đúng giờ.</p>
@endsection
