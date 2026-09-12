@extends('emails.notification-layout', [
    'subjectText' => $title,
    'accentColor' => '#455A64',
    'accentLabel' => 'VĂN BẢN CẬP NHẬT',
])

@section('body')
    <p>{{ $summary }}</p>

    <p class="action-note">Đề nghị Quý vị truy cập hệ thống để xem chi tiết.</p>
@endsection
