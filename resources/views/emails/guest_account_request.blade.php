@extends('emails.notification-layout', [
    'subjectText' => $subjectText,
    'accentColor' => '#0B4F9C',
    'accentLabel' => 'YÊU CẦU MỞ TÀI KHOẢN',
    'greeting' => 'Kính gửi Quản trị viên',
])

@section('body')
    <p>Hệ thống vừa nhận một yêu cầu mở tài khoản từ khách. Thông tin chi tiết như sau:</p>

    <div class="info-title"><span class="info-title-dot"></span>Thông tin người gửi</div>
    <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td class="info-label">Họ tên</td>
            <td class="info-value">{{ $fullName }}</td>
        </tr>
        <tr>
            <td class="info-label">Số điện thoại</td>
            <td class="info-value">{{ $phone }}</td>
        </tr>
        <tr>
            <td class="info-label">Thư điện tử</td>
            <td class="info-value">{{ $email }}</td>
        </tr>
        <tr>
            <td class="info-label">Nội dung yêu cầu</td>
            <td class="info-value">{!! nl2br(e($content)) !!}</td>
        </tr>
    </table>

    <p class="action-note">Đề nghị Quản trị viên xem xét và liên hệ lại với người gửi qua thông tin ở trên.</p>
@endsection
