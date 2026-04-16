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
    private ?string $server;

    private ?string $username;

    private ?string $password;

    private ?string $sender;

    private ?string $templateId;

    private array $extraParams;

    public function __construct(SettingService $settings)
    {
        $this->server = $settings->getByKey('zalo_server')['value'] ?? null;
        $this->username = $settings->getByKey('zalo_username')['value'] ?? null;
        $this->password = $settings->getByKey('zalo_password')['value'] ?? null;
        $this->sender = $settings->getByKey('zalo_sender')['value'] ?? null;
        $this->templateId = $settings->getByKey('zalo_template_id')['value'] ?? null;

        $extra = $settings->getByKey('zalo_extra_params')['value'] ?? null;
        $this->extraParams = is_array($extra) ? $extra : (is_string($extra) ? (json_decode($extra, true) ?? []) : []);
    }

    public function key(): string
    {
        return 'zalo';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        if (! $this->server || ! $this->username || ! $this->password) {
            return $this->fail('Zalo not configured');
        }

        if (! $this->sender) {
            return $this->fail('Missing Zalo OA sender ID');
        }

        if (! $this->templateId) {
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

        $templateData = array_merge($this->extraParams, $payload->context);

        $body = [
            'from' => $this->sender,
            'to' => $phone,
            'template_id' => $this->templateId,
            'template_data' => $templateData ?: (object) [],
            'client_req_id' => Str::uuid()->toString(),
        ];

        try {
            $response = Http::withBasicAuth($this->username, $this->password)
                ->acceptJson()
                ->post($this->server, $body);

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
