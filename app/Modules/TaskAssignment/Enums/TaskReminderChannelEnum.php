<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Kênh nhắc việc.
 */
enum TaskReminderChannelEnum: string
{
    case System = 'system';
    case Email = 'email';
    case Zalo = 'zalo';
    case Sms = 'sms';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:system,email,zalo,sms */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::System => 'Hệ thống',
            self::Email => 'Email',
            self::Zalo => 'Zalo',
            self::Sms => 'SMS',
        };
    }
}
