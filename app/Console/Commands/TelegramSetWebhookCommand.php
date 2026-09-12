<?php

namespace App\Console\Commands;

use App\Modules\Core\Services\SettingService;
use App\Modules\Core\Services\TelegramLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Đăng ký webhook của bot với Telegram. Chạy một lần sau khi cấu hình bot token,
 * và chạy lại mỗi khi đổi domain.
 *
 * Domain lấy theo thứ tự: tùy chọn --url > cấu hình tg_webhook_url > APP_URL trong .env.
 * Telegram chỉ chấp nhận HTTPS và phải gọi vào được từ Internet — localhost không dùng được,
 * lúc phát triển thì dựng đường hầm (ngrok, cloudflared) rồi truyền --url.
 */
class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {--url= : Domain HTTPS của backend, vd https://api-qlcv.danatec.vn}';

    protected $description = 'Đăng ký webhook Telegram cho bot đang cấu hình';

    public function handle(SettingService $settings, TelegramLinkService $linkService): int
    {
        if (! ($settings->getByKey('tg_bot_token')['value'] ?? null)) {
            $this->error('Chưa cấu hình tg_bot_token trong màn Cài đặt.');

            return self::FAILURE;
        }

        $base = rtrim((string) (
            $this->option('url')
            ?: ($settings->getByKey('tg_webhook_url')['value'] ?? null)
            ?: config('app.url')
        ), '/');

        if (! str_starts_with($base, 'https://')) {
            $this->error("Domain webhook phải là HTTPS, đang là: {$base}");

            return self::FAILURE;
        }

        // Secret sinh sẵn để không ai phải tự nghĩ chuỗi rồi copy nhầm giữa hai nơi —
        // webhook từ chối mọi request không kèm đúng chuỗi này.
        $secret = $settings->getByKey('tg_webhook_secret')['value'] ?? null;
        if (! $secret) {
            $secret = Str::random(48);
            $settings->update(['tg_webhook_secret' => $secret]);
            $this->info('Đã sinh khóa bí mật webhook và lưu vào cấu hình.');
        }

        $url = "{$base}/api/telegram/webhook";

        $response = $linkService->callApi('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            // Chỉ nhận tin nhắn: giai đoạn này bot gửi một chiều, các loại update khác chỉ tốn hàng đợi.
            'allowed_updates' => ['message'],
            'drop_pending_updates' => true,
        ]);

        if (($response['ok'] ?? false) !== true) {
            $this->error('Đăng ký webhook thất bại: '.($response['description'] ?? 'không có phản hồi từ Telegram'));

            return self::FAILURE;
        }

        $this->info("Đã đăng ký webhook: {$url}");

        if ($username = $linkService->botUsername()) {
            $this->info("Bot: @{$username}");
        }

        return self::SUCCESS;
    }
}
