<?php

namespace App\Console\Commands;

use App\Modules\Core\Services\TelegramLinkService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Đăng ký webhook của bot với Telegram từ dòng lệnh — dùng khi triển khai tự động
 * hoặc khi chưa vào được màn Cài đặt. Cùng logic với nút "Đăng ký webhook" trên giao diện.
 *
 * Domain lấy theo thứ tự: --url > cấu hình tg_webhook_url > APP_URL trong .env.
 * Telegram chỉ chấp nhận HTTPS và phải gọi vào được từ Internet — localhost không dùng được,
 * lúc phát triển thì dựng đường hầm (ngrok, cloudflared) rồi truyền --url.
 */
class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {--url= : Domain HTTPS của backend, vd https://api-qlcv.danatec.vn}';

    protected $description = 'Đăng ký webhook Telegram cho bot đang cấu hình';

    public function handle(TelegramLinkService $linkService): int
    {
        try {
            $result = $linkService->registerWebhook($this->option('url'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($result['secret_generated']) {
            $this->info('Đã sinh khóa bí mật webhook và lưu vào cấu hình.');
        }

        $this->info("Đã đăng ký webhook: {$result['url']}");

        if ($result['bot_username']) {
            $this->info("Bot: @{$result['bot_username']}");
        }

        return self::SUCCESS;
    }
}
