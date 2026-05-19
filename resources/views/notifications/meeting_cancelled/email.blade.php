@extends('emails.notification-layout', [
    'subjectText' => 'Cuộc họp đã bị hủy',
    'accentColor' => '#C62828',
    'accentLabel' => 'HỦY CUỘC HỌP',
])

@section('body')
    <p>Trân trọng thông báo cuộc họp <strong>{{ $meeting->title }}</strong> đã bị <strong style="color:#C62828">HỦY</strong>.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin cuộc họp đã hủy</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tiêu đề</td>
            <td class="info-value">{{ $meeting->title }}</td>
        </tr>
        @if($meeting->start_time)
            <tr>
                <td class="info-label">Thời gian dự kiến</td>
                <td class="info-value">{{ $meeting->start_time->format('H:i') }} ngày {{ $meeting->start_time->format('d/m/Y') }}</td>
            </tr>
        @endif
        @if($meeting->meetingLocation?->name)
            <tr>
                <td class="info-label">Địa điểm dự kiến</td>
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

    <p class="action-note">Quý vị không cần tham dự cuộc họp này. Vui lòng kiểm tra hệ thống nếu có cuộc họp thay thế.</p>
    @isset($url)
        <p class="action-note">Xem chi tiết: <a href="{{ $url }}">{{ $url }}</a></p>
    @endisset
@endsection
