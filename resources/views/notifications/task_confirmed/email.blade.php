@extends('emails.notification-layout', [
    'subjectText' => 'Công việc đã được xác nhận',
    'accentColor' => '#2F7D32',
    'accentLabel' => 'ĐÃ XÁC NHẬN',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: công việc <strong>{{ $item->name }}</strong> đã được xác nhận hoàn thành.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin công việc</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item->name }}</td>
        </tr>
        @if($item->confirmed_at)
            <tr>
                <td class="info-label">Thời điểm xác nhận</td>
                <td class="info-value">{{ $item->confirmed_at->format('H:i') }} ngày {{ $item->confirmed_at->format('d/m/Y') }}</td>
            </tr>
        @endif
    </table>

    <p class="action-note">Xin cảm ơn Quý vị đã hoàn thành công việc đúng yêu cầu.</p>
@endsection
