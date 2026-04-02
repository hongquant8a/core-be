<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Loại thời hạn công việc.
 */
enum TaskDeadlineTypeEnum: string
{
    case HasDeadline = 'has_deadline';
    case NoDeadline = 'no_deadline';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:has_deadline,no_deadline */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::HasDeadline => 'Có thời hạn',
            self::NoDeadline => 'Không có thời hạn',
        };
    }
}
