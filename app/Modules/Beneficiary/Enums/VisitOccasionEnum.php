<?php

namespace App\Modules\Beneficiary\Enums;

enum VisitOccasionEnum: string
{
    case Tet = 'tet';
    case WarInvalidsDay = 'war_invalids_day_27_7';
    case Birthday = 'birthday';
    case Custom = 'custom';

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
            self::Tet => 'Tết Nguyên đán',
            self::WarInvalidsDay => 'Ngày Thương binh - Liệt sĩ 27/7',
            self::Birthday => 'Sinh nhật',
            self::Custom => 'Khác',
        };
    }
}
