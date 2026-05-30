@extends('emails.notification-layout', [
    'subjectText' => 'Hủy lịch công tác',
    'accentColor' => '#EF4444',
    'accentLabel' => 'HỦY LỊCH CÔNG TÁC',
])

@section('body')
    <p>Thông báo lịch công tác sau đây đã bị hủy bỏ:</p>

    <div class="info-title"><span class="info-title-dot"></span>Chi tiết lịch công tác đã hủy</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Nội dung</td>
            <td class="info-value"><strong>{{ $schedule->content }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Ngày diễn ra ban đầu</td>
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
    </table>
@endsection
