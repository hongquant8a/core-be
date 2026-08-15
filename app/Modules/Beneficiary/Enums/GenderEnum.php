<?php

namespace App\Modules\Beneficiary\Enums;

/**
 * Giới tính — dùng cho cả `beneficiaries` và `beneficiary_dependents`.
 */
enum GenderEnum: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Nam',
            self::Female => 'Nữ',
            self::Other => 'Khác',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }
}
