@extends('emails.notification-layout', [
    'subjectText' => 'Công việc chờ xác nhận',
    'accentColor' => '#0E8A8E',
    'accentLabel' => 'CHỜ XÁC NHẬN',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: công việc <strong>{{ $item->name }}</strong> đã được báo cáo hoàn thành và đang chờ Quý vị xác nhận kết quả.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin công việc</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item->name }}</td>
        </tr>
        @if($item->completed_at)
            <tr>
                <td class="info-label">Thời điểm báo cáo</td>
                <td class="info-value">{{ $item->completed_at->format('H:i') }} ngày {{ $item->completed_at->format('d/m/Y') }}</td>
            </tr>
        @endif
        <tr>
            <td class="info-label">Tiến độ báo cáo</td>
            <td class="info-value">{{ $item->completion_percent }}%</td>
        </tr>
    </table>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để kiểm tra và xác nhận kết quả thực hiện.</p>
@endsection
