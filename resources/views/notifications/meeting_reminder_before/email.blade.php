@extends('emails.notification-layout', [
    'subjectText' => 'Cuộc họp sắp diễn ra',
    'accentColor' => '#F4C20D',
    'accentLabel' => 'SẮP DIỄN RA',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: cuộc họp <strong>{{ $meeting->title }}</strong> sắp đến giờ diễn ra.</p>

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
    </table>

    <p class="action-note">Đề nghị Quý vị sắp xếp, bố trí thời gian để tham dự đúng giờ.</p>
    @isset($url)
        <p class="action-note">Xem chi tiết: <a href="{{ $url }}">{{ $url }}</a></p>
    @endisset
@endsection
