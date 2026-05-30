@extends('emails.notification-layout', [
    'subjectText' => 'Nhắc lịch công tác sắp diễn ra',
    'accentColor' => '#F59E0B',
    'accentLabel' => 'NHẮC LỊCH CÔNG TÁC',
])

@section('body')
    <p>Nhắc nhở: Bạn có lịch công tác sắp diễn ra trong thời gian tới:</p>

    <div class="info-title"><span class="info-title-dot"></span>Chi tiết lịch công tác</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Nội dung</td>
            <td class="info-value"><strong>{{ $schedule->content }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Ngày diễn ra</td>
            <td class="info-value">{{ \Carbon\Carbon::parse($schedule->event_date)->format('d/m/Y') }}</td>
        </tr>
        @if($schedule->start_time)
            <tr>
                <td class="info-label">Thời gian bắt đầu</td>
                <td class="info-value">{{ \Carbon\Carbon::parse($schedule->start_time)->format('H:i') }}</td>
            </tr>
        @endif
        @if($schedule->location)
            <tr>
                <td class="info-label">Địa điểm</td>
                <td class="info-value">{{ $schedule->location }}</td>
            </tr>
        @endif
        @if($schedule->host)
            <tr>
                <td class="info-label">Chủ trì</td>
                <td class="info-value">{{ $schedule->host->name }}</td>
            </tr>
        @endif
    </table>

    @isset($url)
        <p class="action-note">Xem chi tiết: <a href="{{ $url }}">{{ $url }}</a></p>
    @endisset
@endsection
