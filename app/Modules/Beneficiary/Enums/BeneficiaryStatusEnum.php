<?php

namespace App\Modules\Beneficiary\Enums;

enum BeneficiaryStatusEnum: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Deceased = 'deceased';
    case MovedOut = 'moved_out';
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
            self::Pending => 'Chờ công nhận',
            self::Active => 'Đang hưởng',
            self::Deceased => 'Đã mất',
            self::MovedOut => 'Đã chuyển đi',
            self::Suspended => 'Tạm dừng',
        };
    }
}
