<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Trạng thái văn bản giao việc.
 */
enum TaskAssignmentDocumentStatusEnum: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:draft,issued */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bản nháp',
            self::Issued => 'Đã ban hành',
        };
    }
}
