<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Trạng thái nhận việc.
 */
enum TaskUserAssignmentStatusEnum: string
{
    case Assigned = 'assigned';
    case Done = 'done';
    case Transferred = 'transferred';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:assigned,done,transferred */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Đã giao',
            self::Done => 'Hoàn thành',
            self::Transferred => 'Đã chuyển',
        };
    }
}
