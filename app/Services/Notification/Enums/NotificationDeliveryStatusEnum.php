<?php

namespace App\Services\Notification\Enums;

enum NotificationDeliveryStatusEnum: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ gửi',
            self::Sent => 'Đã gửi',
            self::Failed => 'Thất bại',
        };
    }

    /**
     * Resolve label cho 1 status key string — trả về key nguyên gốc nếu không match enum.
     * Dùng cho stats grouping khi key có thể là status legacy / chưa biết.
     */
    public static function labelOf(string $key): string
    {
        return self::tryFrom($key)?->label() ?? $key;
    }
}
