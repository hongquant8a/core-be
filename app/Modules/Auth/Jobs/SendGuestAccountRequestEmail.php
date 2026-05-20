<?php

namespace App\Modules\Auth\Jobs;

use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class SendGuestAccountRequestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'notifications';

    public function __construct(public string $contactEmail, public array $data) {}

    public function handle(MailChannel $mail): void
    {
        $appName = null;
        try {
            $appName = app(\App\Modules\Core\Services\SettingService::class)
                ->getByKey('organization_name')['value'] ?? null;
        } catch (\Throwable) {
        }

        $html = View::make('emails.guest_account_request', [
            'fullName' => $this->data['full_name'],
            'phone' => $this->data['phone'],
            'email' => $this->data['email'],
            'content' => $this->data['content'],
            'recipient' => (object) ['name' => 'Quản trị viên'],
            'appName' => $appName ?: config('app.name', 'Hệ thống'),
            'logoUrl' => null,
            'copyright' => null,
            'subjectText' => 'Yêu cầu mở tài khoản từ '.$this->data['full_name'],
        ])->render();

        $recipient = new Recipient(email: $this->contactEmail, name: 'Quản trị viên');

        $payload = new NotificationPayload(
            channels: ['mail'],
            recipient: $recipient,
            content: $html,
            subject: 'Yêu cầu mở tài khoản từ '.$this->data['full_name'],
        );

        $mail->send($recipient, $payload);
    }
}
