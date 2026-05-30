<?php

namespace App\Modules\Scheduling\Enums;

enum NatureEnum: string
{
    case Host = 'HOST';
    case Attend = 'ATTEND';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::Host => 'Chủ trì',
            self::Attend => 'Tham dự',
        };
    }
}
