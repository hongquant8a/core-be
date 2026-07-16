<?php

namespace App\Modules\Beneficiary\Enums;

enum SubsidyStatusEnum: string
{
    case Active = 'active';
    case Terminated = 'terminated';
    case Suspended = 'suspended';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang chi trả',
            self::Terminated => 'Đã dừng',
            self::Suspended => 'Tạm dừng',
        };
    }
}
