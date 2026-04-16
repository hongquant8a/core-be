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
    private ?string $host;

    private ?string $port;

    private ?string $username;

    private ?string $password;

    private ?string $encryption;

    private ?string $senderAddress;

    private ?string $senderName;

    public function __construct(SettingService $settings)
    {
        $this->host = $settings->getByKey('email_smtp_host')['value'] ?? null;
        $this->port = $settings->getByKey('email_smtp_port')['value'] ?? '587';
        $this->username = $settings->getByKey('email_smtp_username')['value'] ?? null;
        $this->password = $settings->getByKey('email_smtp_password')['value'] ?? null;
        $this->encryption = $settings->getByKey('email_smtp_encryption')['value'] ?? 'tls';
        $this->senderAddress = $settings->getByKey('email_sender_address')['value'] ?? null;
        $this->senderName = $settings->getByKey('email_sender_name')['value'] ?? '';
    }

    public function key(): string
    {
        return 'mail';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        if (! $this->host || ! $this->username || ! $this->password) {
            return $this->fail('Mail not configured');
        }

        if (! $this->senderAddress) {
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
                    'host' => $this->host,
                    'port' => (int) $this->port,
                    'username' => $this->username,
                    'password' => $this->password,
                    'encryption' => $this->encryption,
                ],
            ]);

            $html = view('emails.notification', [
                'subject' => $subject,
                'content' => $payload->content,
                'recipientName' => $recipient->name,
                'context' => $payload->context ?: [],
                'senderName' => $this->senderName,
            ])->render();

            Mail::mailer('notification_smtp')
                ->html($html, function (Message $message) use ($recipient, $subject) {
                    $message->from($this->senderAddress, $this->senderName)
                        ->to($recipient->email, $recipient->name)
                        ->subject($subject);
                });

            return new SendResult(channel: 'mail', success: true);
        } catch (Throwable $e) {
            return $this->fail('Mail error: '.$e->getMessage());
        }
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'mail', success: false, error: $error);
    }
}
