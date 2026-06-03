<?php

namespace App\Modules\Scheduling\Enums;

enum ScheduleStatusEnum: string
{
    case Pending   = 'PENDING';
    case Approved  = 'APPROVED';
    case Rejected  = 'REJECTED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Chờ duyệt',
            self::Approved  => 'Đã duyệt',
            self::Rejected  => 'Từ chối',
            self::Cancelled => 'Đã hủy',
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
