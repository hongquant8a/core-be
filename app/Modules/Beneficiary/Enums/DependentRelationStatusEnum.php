<?php

namespace App\Modules\Beneficiary\Enums;

enum DependentRelationStatusEnum: string
{
    case Active = 'active';
    case Expired = 'expired';
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
            self::Active => 'Đang hưởng',
            self::Expired => 'Hết điều kiện hưởng',
            self::Suspended => 'Tạm dừng',
        };
    }
}
