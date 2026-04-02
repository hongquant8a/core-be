<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Vai trò người dùng trong công việc.
 */
enum TaskUserAssignmentRoleEnum: string
{
    case Main = 'main';
    case Support = 'support';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:main,support */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Main => 'Chủ trì',
            self::Support => 'Hỗ trợ',
        };
    }
}
