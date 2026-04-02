<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Trạng thái nhắc việc.
 */
enum TaskReminderStatusEnum: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:pending,sent,failed */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ gửi',
            self::Sent => 'Đã gửi',
            self::Failed => 'Thất bại',
        };
    }
}
