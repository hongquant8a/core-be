<?php

namespace App\Modules\TaskAssignment\Enums;

/**
 * Vai trò phòng ban trong công việc.
 */
enum TaskAssignmentRoleEnum: string
{
    case Main = 'main';
    case Cooperate = 'cooperate';

    /** Danh sách giá trị để validate. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Rule validation: in:main,cooperate */
    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    /** Nhãn tiếng Việt. */
    public function label(): string
    {
        return match ($this) {
            self::Main => 'Chủ trì',
            self::Cooperate => 'Phối hợp',
        };
    }
}
