@extends('emails.notification-layout', [
    'subjectText' => 'Kết quả duyệt gia hạn thời hạn',
    'accentColor' => $approved ? '#2E7D32' : '#C62828',
    'accentLabel' => $approved ? 'ĐÃ DUYỆT' : 'BỊ TỪ CHỐI',
])

@section('body')
    @if($approved)
        <p>Hệ thống xin thông báo: yêu cầu gia hạn công việc <strong>{{ $item?->name }}</strong> đã được duyệt. Thời hạn công việc đã được cập nhật.</p>
    @else
        <p>Hệ thống xin thông báo: yêu cầu gia hạn công việc <strong>{{ $item?->name }}</strong> đã bị từ chối. Thời hạn công việc giữ nguyên.</p>
    @endif

    <div class="info-title"><span class="info-title-dot"></span>Thông tin yêu cầu</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item?->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Thời hạn trước khi xin</td>
            <td class="info-value">{{ $extension->current_end_at?->format('H:i d/m/Y') ?? '(chưa có)' }}</td>
        </tr>
        <tr>
            <td class="info-label">{{ $approved ? 'Thời hạn mới' : 'Thời hạn đã xin (không áp dụng)' }}</td>
            <td class="info-value">{{ $extension->requested_end_at?->format('H:i d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="info-label">Người duyệt</td>
            <td class="info-value">{{ $extension->reviewedBy?->name }}</td>
        </tr>
        @if(!empty($extension->review_note))
            <tr>
                <td class="info-label">Ghi chú</td>
                <td class="info-value">{{ $extension->review_note }}</td>
            </tr>
        @endif
    </table>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết công việc.</p>
@endsection
