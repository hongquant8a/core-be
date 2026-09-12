@extends('emails.notification-layout', [
    'subjectText' => $title,
    'accentColor' => '#EF6C00',
    'accentLabel' => 'THỜI HẠN ĐỔI',
])

@section('body')
    <p>{{ $summary }}</p>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết.</p>
@endsection
