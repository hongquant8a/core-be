<?php

namespace App\Services\Notification\Enums;

enum NotificationDeliveryChannelEnum: string
{
    case Sms = 'sms';
    case Mail = 'mail';
    case Zalo = 'zalo';
    case Fcm = 'fcm';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Sms => 'SMS',
            self::Mail => 'Email',
            self::Zalo => 'Zalo',
            self::Fcm => 'Thông báo đẩy',
        };
    }

    /**
     * Resolve label cho 1 channel key string — trả về key nguyên gốc nếu không match enum.
     * Dùng cho stats grouping khi key có thể là channel chưa biết (legacy data).
     */
    public static function labelOf(string $key): string
    {
        return self::tryFrom($key)?->label() ?? $key;
    }
}
