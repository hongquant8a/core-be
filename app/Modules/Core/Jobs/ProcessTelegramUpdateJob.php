<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Services\TelegramLinkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Xử lý một update từ webhook Telegram.
 *
 * Controller trả 200 ngay rồi đẩy việc sang đây: Telegram timeout 60s và sẽ gửi lại
 * update nếu chờ lâu, xử lý đồng bộ là nhân đôi tin nhắn cho người dùng.
 */
class ProcessTelegramUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 30];

    /** @param  array<string, mixed>  $update  payload thô của Telegram */
    public function __construct(public array $update) {}

    public function handle(TelegramLinkService $linkService): void
    {
        $message = $this->update['message'] ?? null;
        $chatId = $message['chat']['id'] ?? null;
        $text = trim((string) ($message['text'] ?? ''));

        // Chỉ quan tâm lệnh khởi tạo chat. Mọi update khác (tin nhắn thường, sửa tin,
        // thành viên rời nhóm) bỏ qua — giai đoạn này bot chỉ gửi một chiều.
        if (! $chatId || ! str_starts_with($text, '/start')) {
            return;
        }

        $token = trim(substr($text, strlen('/start')));

        $linkService->handleStart((string) $chatId, $token !== '' ? $token : null);
    }
}
