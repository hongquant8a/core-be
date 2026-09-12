@extends('emails.notification-layout', [
    'subjectText' => 'Yêu cầu gia hạn thời hạn công việc',
    'accentColor' => '#EF6C00',
    'accentLabel' => 'CHỜ DUYỆT',
])

@section('body')
    <p>Hệ thống xin thông báo: có yêu cầu gia hạn thời hạn công việc <strong>{{ $item?->name }}</strong> đang chờ Quý vị duyệt.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin yêu cầu</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item?->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Người xin gia hạn</td>
            <td class="info-value">{{ $extension->requestedBy?->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Thời hạn hiện tại</td>
            <td class="info-value">{{ $extension->current_end_at?->format('H:i d/m/Y') ?? '(chưa có)' }}</td>
        </tr>
        <tr>
            <td class="info-label">Xin dời tới</td>
            <td class="info-value">{{ $extension->requested_end_at?->format('H:i d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Lý do</td>
            <td class="info-value">{{ $extension->reason }}</td>
        </tr>
    </table>

    <p class="action-note">Thời hạn công việc chưa thay đổi. Đề nghị Quý vị truy cập hệ thống để duyệt hoặc từ chối yêu cầu.</p>
@endsection
