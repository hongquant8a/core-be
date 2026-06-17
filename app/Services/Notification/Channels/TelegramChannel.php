<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Telegram channel — gửi tin nhắn đến người dùng qua Telegram Bot API.
 *
 * - Endpoint: https://api.telegram.org/bot<token>/sendMessage
 * - Recipient là telegram_chat_id (lấy được sau khi user start bot).
 * - Hỗ trợ parse_mode=HTML để giữ định dạng.
 *
 * Settings keys (group: telegram):
 *   tg_enabled, tg_bot_token
 *
 * Server VN bị nhà mạng chặn HTTP/2 đến Telegram → force HTTP/1.1 + IPv4.
 */
class TelegramChannel implements NotificationChannel
{
    public function __construct(private SettingService $settings) {}

    public function key(): string
    {
        return 'telegram';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        $cfg = $this->loadConfig();

        if (! $cfg['enabled']) {
            return $this->fail('Kênh Telegram đang bị tắt.');
        }

        if (! $cfg['bot_token']) {
            return $this->fail('Telegram chưa cấu hình bot token.');
        }

        $chatId = $recipient->telegramChatId;
        if (! $chatId) {
            return $this->fail('Người nhận chưa liên kết Telegram (thiếu chat_id).');
        }

        $text = $payload->content ?: '';
        if ($text === '') {
            return $this->fail('Nội dung tin nhắn trống.');
        }

        try {
            $response = Http::timeout(30)
                ->withOptions(['curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ]])
                ->post("https://api.telegram.org/bot{$cfg['bot_token']}/sendMessage", [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                ]);

            $data = $response->json() ?? [];
        } catch (Throwable $e) {
            return $this->fail('HTTP error: ' . $e->getMessage());
        }

        if (($data['ok'] ?? false) === true) {
            return new SendResult(
                channel: 'telegram',
                success: true,
                messageId: (string) ($data['result']['message_id'] ?? null),
            );
        }

        $error = $data['description'] ?? 'Telegram gửi thất bại';

        return $this->fail($error);
    }

    private function loadConfig(): array
    {
        return [
            'enabled'   => (bool) ($this->settings->getByKey('tg_enabled')['value'] ?? false),
            'bot_token' => $this->settings->getByKey('tg_bot_token')['value'] ?? null,
        ];
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'telegram', success: false, error: $error);
    }
}
