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

/**
 * Zalo ZNS channel — gửi tin nhắn ZNS template qua WorldSMS relay (South Telecom).
 *
 * - Endpoint: POST https://api-04.worldsms.vn/apidebit/sendZNS
 * - Auth: Basic auth (Authorization: Basic base64(user:password))
 * - Recipient là `phone` (format 84xxxxxxxxx).
 * - Template-based: cần template_id đã được ZNS duyệt + template_data từ payload.context.
 * - Tuỳ chọn SMS Failover: nếu config đầy đủ (sms_sender, sms_unicode) thì gắn thêm smsFailover.
 *
 * Settings keys (group: zalo_zns):
 *   zns_enabled, zns_server, zns_username, zns_password,
 *   zns_sender, zns_template_id, zns_extra_params,
 *   zns_sms_failover_sender, zns_sms_failover_unicode
 */
class ZaloZnsChannel implements NotificationChannel
{
    public function __construct(private SettingService $settings) {}

    public function key(): string
    {
        return 'zalo_zns';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('Kênh Zalo ZNS đang bị tắt.');
        }

        if (! $cfg['server'] || ! $cfg['username'] || ! $cfg['password']) {
            return $this->fail('Zalo ZNS chưa cấu hình (thiếu server/username/password).');
        }

        if (! $cfg['sender']) {
            return $this->fail('Thiếu Zalo OA Sender ID (zns_sender).');
        }

        if (! $cfg['template_id']) {
            return $this->fail('Thiếu Zalo ZNS Template ID (zns_template_id).');
        }

        $phone = $recipient->phone;
        if (! $phone) {
            return $this->fail('Missing phone');
        }

        $phone = $this->normalizePhone($phone);
        if (! preg_match('/^84\d{9,10}$/', $phone)) {
            return $this->fail('Số điện thoại không hợp lệ (cần format 84xxxxxxxxx).');
        }

        $templateData = array_merge($cfg['extra_params'], $payload->context ?? []);

        $body = [
            'from'          => $cfg['sender'],
            'to'            => $phone,
            'template_id'   => $cfg['template_id'],
            'template_data' => $templateData ?: (object) [],
            'client_req_id' => Str::uuid()->toString(),
            'dlr'           => 0,
        ];

        // Thêm SMS Failover nếu đã cấu hình
        if ($cfg['sms_failover_sender']) {
            $text = trim((string) ($payload->content ?? ''));
            if ($text !== '') {
                $body['smsFailover'] = [
                    'from'    => $cfg['sms_failover_sender'],
                    'to'      => $phone,
                    'text'    => $text,
                    'unicode' => (int) $cfg['sms_failover_unicode'],
                ];
            }
        }

        try {
            $response = Http::withBasicAuth($cfg['username'], $cfg['password'])
                ->acceptJson()
                ->post($cfg['server'], $body);

            $data = $response->json() ?? [];
        } catch (Throwable $e) {
            return $this->fail('HTTP error: '.$e->getMessage());
        }

        $status = (int) ($data['status'] ?? 0);

        if ($status === 1) {
            return new SendResult(
                channel: 'zalo_zns',
                success: true,
                messageId: $data['tracking_id'] ?? null,
            );
        }

        $errorCode   = $data['errorcode'] ?? 'unknown';
        $description = $data['description'] ?? 'Zalo ZNS gửi thất bại';

        return $this->fail("[{$errorCode}] {$description}");
    }

    private function loadConfig(): array
    {
        $extra       = $this->settings->getByKey('zns_extra_params')['value'] ?? null;
        $extraParams = is_array($extra) ? $extra : (is_string($extra) ? (json_decode($extra, true) ?? []) : []);

        return [
            'enabled'              => (bool) ($this->settings->getByKey('zns_enabled')['value'] ?? false),
            'server'               => $this->settings->getByKey('zns_server')['value'] ?? null,
            'username'             => $this->settings->getByKey('zns_username')['value'] ?? null,
            'password'             => $this->settings->getByKey('zns_password')['value'] ?? null,
            'sender'               => $this->settings->getByKey('zns_sender')['value'] ?? null,
            'template_id'          => $this->settings->getByKey('zns_template_id')['value'] ?? null,
            'extra_params'         => $extraParams,
            'sms_failover_sender'  => $this->settings->getByKey('zns_sms_failover_sender')['value'] ?? null,
            'sms_failover_unicode' => (int) ($this->settings->getByKey('zns_sms_failover_unicode')['value'] ?? 1),
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
        return new SendResult(channel: 'zalo_zns', success: false, error: $error);
    }
}
