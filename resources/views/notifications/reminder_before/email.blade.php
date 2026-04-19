@extends('emails.notification-layout', [
    'subjectText' => 'Công việc sắp đến hạn',
    'accentColor' => '#F4C20D',
    'accentLabel' => 'SẮP ĐẾN HẠN',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: công việc <strong>{{ $item->name }}</strong> mà Quý vị phụ trách sắp đến thời hạn hoàn thành.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin công việc</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item->name }}</td>
        </tr>
        @if($item->end_at)
            <tr>
                <td class="info-label">Hạn hoàn thành</td>
                <td class="info-value">{{ $item->end_at->format('H:i') }} ngày {{ $item->end_at->format('d/m/Y') }}</td>
            </tr>
        @endif
        <tr>
            <td class="info-label">Tiến độ hiện tại</td>
            <td class="info-value">{{ $item->completion_percent }}%</td>
        </tr>
    </table>

    <p class="action-note">Đề nghị Quý vị sắp xếp, bố trí thời gian để hoàn thành công việc đúng thời hạn đã định.</p>
@endsection
