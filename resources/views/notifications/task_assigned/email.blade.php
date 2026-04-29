@extends('emails.notification-layout', [
    'subjectText' => 'Bạn vừa được giao công việc',
    'accentColor' => '#1F6FEB',
    'accentLabel' => 'CÔNG VIỆC MỚI',
])

@section('body')
    <p>Hệ thống xin trân trọng thông báo: Quý vị vừa được giao công việc <strong>{{ $item->name }}</strong>@if($item->document) thuộc văn bản <strong>{{ $item->document->name }}</strong>@endif.</p>

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
        @if($item->priority)
            <tr>
                <td class="info-label">Độ ưu tiên</td>
                <td class="info-value">{{ ucfirst($item->priority) }}</td>
            </tr>
        @endif
    </table>

    @if($item->document)
        <div class="info-title"><span class="info-title-dot"></span>Văn bản giao việc</div>
        <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
            <tr>
                <td class="info-label">Tên văn bản</td>
                <td class="info-value">{{ $item->document->name }}</td>
            </tr>
            @if($item->document->summary)
                <tr>
                    <td class="info-label">Tóm tắt</td>
                    <td class="info-value">{{ $item->document->summary }}</td>
                </tr>
            @endif
            @if($item->document->issue_date)
                <tr>
                    <td class="info-label">Ngày văn bản</td>
                    <td class="info-value">{{ \Carbon\Carbon::parse($item->document->issue_date)->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if($item->document->issued_at)
                <tr>
                    <td class="info-label">Ban hành lúc</td>
                    <td class="info-value">{{ $item->document->issued_at->format('H:i') }} ngày {{ $item->document->issued_at->format('d/m/Y') }}</td>
                </tr>
            @endif
        </table>
    @endif

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết và bắt đầu thực hiện.</p>
@endsection
