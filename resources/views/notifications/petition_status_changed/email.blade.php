@extends('emails.notification-layout', [
    'subjectText' => $title,
    'accentColor' => '#00838F',
    'accentLabel' => 'ĐƠN THƯ',
])

@section('body')
    <p>{{ $summary }}</p>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết.</p>
@endsection
