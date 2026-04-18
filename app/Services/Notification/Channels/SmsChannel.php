<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\SmsClient;
use Illuminate\Support\Str;
use Throwable;

class SmsChannel implements NotificationChannel
{
    public function __construct(private SmsClient $client, private SettingService $settings) {}

    public function key(): string
    {
        return 'sms';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('SMS is disabled');
        }

        if (! $cfg['server'] || ! $cfg['username'] || ! $cfg['password']) {
            return $this->fail('SMS not configured');
        }

        if (! $recipient->phone) {
            return $this->fail('Missing phone');
        }

        $phone = $this->normalizePhone($recipient->phone);
        if (! preg_match('/^84\d{9,10}$/', $phone)) {
            return $this->fail('Invalid phone format');
        }

        $content = $this->normalizeContent($payload->content);

        try {
            $resp = $this->client->sendSms($cfg['server'], $cfg['username'], $cfg['password'], $phone, $content);
        } catch (Throwable $e) {
            return $this->fail('SOAP error: '.$e->getMessage());
        }

        $code = $resp['result'] ?? -999;
        if ($code >= 0) {
            return new SendResult(channel: 'sms', success: true, messageId: (string) $code);
        }

        return $this->fail($resp['message'] ?? 'SMS send failed');
    }

    private function loadConfig(): array
    {
        return [
            'enabled' => (bool) ($this->settings->getByKey('sms_enabled')['value'] ?? false),
            'server' => $this->settings->getByKey('sms_server')['value'] ?? null,
            'username' => $this->settings->getByKey('sms_username')['value'] ?? null,
            'password' => $this->settings->getByKey('sms_password')['value'] ?? null,
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if (str_starts_with($phone, '0')) {
            return '84'.substr($phone, 1);
        }

        return $phone;
    }

    private function normalizeContent(string $content): string
    {
        $ascii = Str::ascii($content);
        $hasPrefix = stripos($ascii, 'thong bao:') === 0;
        $hasSuffix = stripos($ascii, 'tran trong !') !== false;

        if (! $hasPrefix && ! $hasSuffix) {
            return 'Thong bao: '.$ascii;
        }

        return $ascii;
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'sms', success: false, error: $error);
    }
}
