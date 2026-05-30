<?php

namespace App\Modules\Scheduling\Enums;

enum ModuleTypeEnum: string
{
    case Executive = 'EXECUTIVE';
    case Office = 'OFFICE';

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
            self::Executive => 'Thường trực',
            self::Office => 'Văn phòng',
        };
    }
}
