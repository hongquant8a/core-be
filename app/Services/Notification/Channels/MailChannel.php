<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailChannel implements NotificationChannel
{
    public function __construct(private SettingService $settings) {}

    public function key(): string
    {
        return 'mail';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('Kênh Email đang bị tắt.');
        }

        if (! $cfg['host'] || ! $cfg['username'] || ! $cfg['password']) {
            return $this->fail('Mail not configured');
        }

        if (! $cfg['sender_address']) {
            return $this->fail('Missing sender email address');
        }

        if (! $recipient->email) {
            return $this->fail('Missing recipient email');
        }

        $subject = $payload->subject ?: 'Thông báo';

        try {
            config([
                'mail.mailers.notification_smtp' => [
                    'transport' => 'smtp',
                    'host' => $cfg['host'],
                    'port' => (int) $cfg['port'],
                    'username' => $cfg['username'],
                    'password' => $cfg['password'],
                    'encryption' => $cfg['encryption'],
                ],
            ]);

            Mail::mailer('notification_smtp')
                ->html($payload->content, function (Message $message) use ($recipient, $subject, $cfg) {
                    $message->from($cfg['sender_address'], $cfg['sender_name'])
                        ->to($recipient->email, $recipient->name)
                        ->subject($subject);
                });

            return new SendResult(channel: 'mail', success: true);
        } catch (Throwable $e) {
            return $this->fail('Mail error: '.$e->getMessage());
        }
    }

    private function loadConfig(): array
    {
        return [
            'enabled' => (bool) ($this->settings->getByKey('email_enabled')['value'] ?? false),
            'host' => $this->settings->getByKey('email_smtp_host')['value'] ?? null,
            'port' => $this->settings->getByKey('email_smtp_port')['value'] ?? '587',
            'username' => $this->settings->getByKey('email_smtp_username')['value'] ?? null,
            'password' => $this->settings->getByKey('email_smtp_password')['value'] ?? null,
            'encryption' => $this->settings->getByKey('email_smtp_encryption')['value'] ?? 'tls',
            'sender_address' => $this->settings->getByKey('email_sender_address')['value'] ?? null,
            'sender_name' => $this->settings->getByKey('email_sender_name')['value'] ?? '',
        ];
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'mail', success: false, error: $error);
    }
}
