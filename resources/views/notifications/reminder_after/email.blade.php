@extends('emails.notification-layout', [
    'subjectText' => 'Công việc đã quá hạn',
    'accentColor' => '#C8102E',
    'accentLabel' => 'QUÁ HẠN',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: công việc <strong>{{ $item->name }}</strong> đã quá thời hạn hoàn thành theo quy định.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin công việc</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item->name }}</td>
        </tr>
        @if($item->end_at)
            <tr>
                <td class="info-label">Thời hạn</td>
                <td class="info-value">{{ $item->end_at->format('H:i') }} ngày {{ $item->end_at->format('d/m/Y') }}</td>
            </tr>
        @endif
        <tr>
            <td class="info-label">Tiến độ hiện tại</td>
            <td class="info-value">{{ $item->completion_percent }}%</td>
        </tr>
    </table>

    <p class="action-note">Đề nghị Quý vị khẩn trương hoàn thành công việc và có báo cáo giải trình với cấp quản lý trực tiếp.</p>
@endsection
