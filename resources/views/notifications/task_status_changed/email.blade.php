@extends('emails.notification-layout', [
    'subjectText' => $title,
    'accentColor' => '#6A1B9A',
    'accentLabel' => 'ĐỔI TRẠNG THÁI',
])

@section('body')
    <p>{{ $summary }}</p>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết.</p>
@endsection
