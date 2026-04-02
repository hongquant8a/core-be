<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Trạng thái nhận việc.
 */
enum TaskUserAssignmentStatusEnum: string
{
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Done = 'done';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:assigned,accepted,rejected,done */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Đã giao',
            self::Accepted => 'Đã nhận',
            self::Rejected => 'Từ chối',
            self::Done => 'Hoàn thành',
        };
    }
}
