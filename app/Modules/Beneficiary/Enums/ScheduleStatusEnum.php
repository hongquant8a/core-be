<?php

namespace App\Modules\Beneficiary\Enums;

enum ScheduleStatusEnum: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thực hiện',
            self::Done => 'Đã thực hiện',
            self::Skipped => 'Bỏ qua',
        };
    }
}
