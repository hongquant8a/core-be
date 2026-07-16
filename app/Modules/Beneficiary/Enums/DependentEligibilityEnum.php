<?php

namespace App\Modules\Beneficiary\Enums;

enum DependentEligibilityEnum: string
{
    case Normal = 'normal';
    case Studying = 'studying';
    case DisabledNoWorkCapacity = 'disabled_no_work_capacity';

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
            self::Normal => 'Bình thường',
            self::Studying => 'Đang đi học',
            self::DisabledNoWorkCapacity => 'Mất khả năng lao động',
        };
    }
}
