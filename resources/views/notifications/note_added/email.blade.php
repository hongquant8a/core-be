@extends('emails.notification-layout', [
    'subjectText' => $title,
    'accentColor' => '#1565C0',
    'accentLabel' => 'TRAO ĐỔI MỚI',
])

@section('body')
    <p>{{ $summary }}</p>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết.</p>
@endsection
