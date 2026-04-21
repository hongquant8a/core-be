<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Trạng thái nhắc việc (khớp migration restructure 2026-04-16).
 */
enum TaskReminderStatusEnum: string
{
    case Pending = 'pending';
    case Fired = 'fired';
    case Cancelled = 'cancelled';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation. */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ gửi',
            self::Fired => 'Đã gửi',
            self::Cancelled => 'Đã hủy',
        };
    }
}
