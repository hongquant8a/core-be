<?php

namespace App\Modules\Beneficiary\Enums;

enum DependentRelationshipEnum: string
{
    case Spouse = 'spouse';
    case Child = 'child';
    case Father = 'father';
    case Mother = 'mother';
    case FosterParent = 'foster_parent';
    case Guardian = 'guardian';

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
            self::Spouse => 'Vợ/Chồng',
            self::Child => 'Con',
            self::Father => 'Cha',
            self::Mother => 'Mẹ',
            self::FosterParent => 'Người nuôi dưỡng',
            self::Guardian => 'Người giám hộ',
        };
    }
}
