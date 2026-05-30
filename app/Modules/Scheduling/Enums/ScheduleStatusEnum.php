<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleStatusEnum: int
{
    case Draft = 0;
    case Pending = 1;
    case Published = 2;
    case Cancelled = 3;

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
            self::Draft => 'Bản nháp',
            self::Pending => 'Chờ duyệt',
            self::Published => 'Đã duyệt/Công bố',
            self::Cancelled => 'Đã hủy',
        };
    }
}
