@extends('emails.notification-layout', [
    'subjectText' => 'Bạn được giao công việc mới',
    'accentColor' => '#1E5EAA',
    'accentLabel' => 'GIAO VIỆC MỚI',
])

@section('body')
    <p>Văn bản giao việc <strong>{{ $document?->name }}</strong> đã được ban hành. Quý vị được phân công thực hiện công việc với thông tin như sau:</p>

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
        @if($item->description)
            <tr>
                <td class="info-label">Nội dung</td>
                <td class="info-value">{!! strip_tags($item->description, '<p><br><strong><b><em><i><u><ol><ul><li><a><span><div>') !!}</td>
            </tr>
        @endif
    </table>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết và triển khai thực hiện đúng tiến độ.</p>
@endsection
