<?php

namespace App\Modules\TaskAssignment\Enums;

enum PetitionStatusEnum: string
{
    case New       = 'new';
    case Processing = 'processing';
    case Completed = 'completed';
    case Paused    = 'paused';
    case Cancelled = 'cancelled';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    public function label(): string
    {
        return match ($this) {
            self::New       => 'Mới tiếp nhận',
            self::Processing => 'Đang xử lý',
            self::Completed => 'Đã hoàn thành',
            self::Paused    => 'Tạm dừng',
            self::Cancelled => 'Đã hủy',
        };
    }
}
