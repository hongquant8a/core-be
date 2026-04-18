<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ZaloChannel implements NotificationChannel
{
    public function __construct(private SettingService $settings) {}

    public function key(): string
    {
        return 'zalo';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('Zalo is disabled');
        }

        if (! $cfg['server'] || ! $cfg['username'] || ! $cfg['password']) {
            return $this->fail('Zalo not configured');
        }

        if (! $cfg['sender']) {
            return $this->fail('Missing Zalo OA sender ID');
        }

        if (! $cfg['template_id']) {
            return $this->fail('Missing Zalo template ID');
        }

        $phone = $recipient->phone;
        if (! $phone) {
            return $this->fail('Missing phone');
        }

        $phone = $this->normalizePhone($phone);
        if (! preg_match('/^84\d{9,10}$/', $phone)) {
            return $this->fail('Invalid phone format');
        }

        $templateData = array_merge($cfg['extra_params'], $payload->context);

        $body = [
            'from' => $cfg['sender'],
            'to' => $phone,
            'template_id' => $cfg['template_id'],
            'template_data' => $templateData ?: (object) [],
            'client_req_id' => Str::uuid()->toString(),
        ];

        try {
            $response = Http::withBasicAuth($cfg['username'], $cfg['password'])
                ->acceptJson()
                ->post($cfg['server'], $body);

            $data = $response->json();
        } catch (Throwable $e) {
            return $this->fail('HTTP error: '.$e->getMessage());
        }

        $status = $data['status'] ?? 0;

        if ($status == 1) {
            return new SendResult(
                channel: 'zalo',
                success: true,
                messageId: $data['tracking_id'] ?? null,
            );
        }

        $errorCode = $data['errorcode'] ?? 'unknown';
        $description = $data['description'] ?? 'Zalo send failed';

        return $this->fail("[{$errorCode}] {$description}");
    }

    private function loadConfig(): array
    {
        $extra = $this->settings->getByKey('zalo_extra_params')['value'] ?? null;
        $extraParams = is_array($extra) ? $extra : (is_string($extra) ? (json_decode($extra, true) ?? []) : []);

        return [
            'enabled' => (bool) ($this->settings->getByKey('zalo_enabled')['value'] ?? false),
            'server' => $this->settings->getByKey('zalo_server')['value'] ?? null,
            'username' => $this->settings->getByKey('zalo_username')['value'] ?? null,
            'password' => $this->settings->getByKey('zalo_password')['value'] ?? null,
            'sender' => $this->settings->getByKey('zalo_sender')['value'] ?? null,
            'template_id' => $this->settings->getByKey('zalo_template_id')['value'] ?? null,
            'extra_params' => $extraParams,
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

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'zalo', success: false, error: $error);
    }
}
