<?php

namespace App\Modules\TaskAssignment\Enums;

enum PetitionStatusEnum: string
{
    case MoiTiepNhan = 'new';
    case DangXuLy    = 'processing';
    case DaHoanThanh = 'completed';
    case TamDung     = 'paused';
    case DaHuy       = 'cancelled';

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
            self::MoiTiepNhan => 'Mới tiếp nhận',
            self::DangXuLy    => 'Đang xử lý',
            self::DaHoanThanh => 'Đã hoàn thành',
            self::TamDung     => 'Tạm dừng',
            self::DaHuy       => 'Đã hủy',
        };
    }
}
