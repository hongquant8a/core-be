@extends('emails.notification-layout', [
    'subjectText' => 'Công việc bị trả lại',
    'accentColor' => '#C62828',
    'accentLabel' => 'BỊ TRẢ LẠI',
])

@section('body')
    <p>Hệ thống xin thông báo: công việc <strong>{{ $item->name }}</strong> đã bị trả lại, đề nghị Quý vị thực hiện lại.</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin công việc</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Tên công việc</td>
            <td class="info-value">{{ $item->name }}</td>
        </tr>
        @if(!empty($reason))
            <tr>
                <td class="info-label">Lý do trả lại</td>
                <td class="info-value">{{ $reason }}</td>
            </tr>
        @endif
    </table>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết và thực hiện lại công việc.</p>
@endsection
