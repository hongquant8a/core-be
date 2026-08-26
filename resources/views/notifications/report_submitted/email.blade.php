@extends('emails.notification-layout', [
    'subjectText' => 'Có báo cáo công việc mới',
    'accentColor' => '#0E8A8E',
    'accentLabel' => 'BÁO CÁO MỚI',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: công việc <strong>{{ $item->name }}</strong> vừa được nộp báo cáo tiến độ, đang chờ Quý vị xem.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin công việc</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item->name }}</td>
        </tr>
        <tr>
            <td class="info-label">Tiến độ báo cáo</td>
            <td class="info-value">{{ $item->completion_percent }}%</td>
        </tr>
    </table>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem nội dung báo cáo.</p>
@endsection
