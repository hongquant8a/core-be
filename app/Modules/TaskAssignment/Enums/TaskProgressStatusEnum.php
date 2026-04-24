<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Trạng thái xử lý công việc.
 */
enum TaskProgressStatusEnum: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:todo,in_progress,done,overdue,paused,cancelled (generated from cases) */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /**
     * Trạng thái user được phép chọn trong UI (loại `done` — internal).
     * - `done`: BE auto set qua endpoint `PATCH /items/{id}/mark-done` (manager).
     *
     * Khái niệm "trễ hạn" KHÔNG còn là enum value — chuyển sang computed flag
     * `is_overdue` trên ItemResource (derive từ `end_at` vs `now()`).
     * Timing của báo cáo đã hoàn thành (on_time/late) nằm ở report.timing_status.
     */
    public static function selectableValues(): array
    {
        return array_values(array_diff(self::values(), [self::Done->value]));
    }

    /** Rule validation cho user input — KHÔNG cho phép chọn `done` trực tiếp. */
    public static function selectableRule(): string
    {
        return 'in:'.implode(',', self::selectableValues());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'Chưa bắt đầu',
            self::InProgress => 'Đang thực hiện',
            self::Done => 'Hoàn thành',
            self::Paused => 'Tạm dừng',
            self::Cancelled => 'Đã hủy',
        };
    }
}
