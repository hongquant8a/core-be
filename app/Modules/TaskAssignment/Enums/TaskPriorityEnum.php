<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Mức độ ưu tiên.
 */
enum TaskPriorityEnum: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:low,medium,high,urgent */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Thấp',
            self::Medium => 'Trung bình',
            self::High => 'Cao',
            self::Urgent => 'Khẩn cấp',
        };
    }
}
