<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleModuleTypeEnum: string
{
    case Executive = 'EXECUTIVE';
    case Office    = 'OFFICE';

    public function label(): string
    {
        return match($this) {
            self::Executive => 'Thường trực Thành ủy',
            self::Office    => 'Văn phòng Thành ủy',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }
}
