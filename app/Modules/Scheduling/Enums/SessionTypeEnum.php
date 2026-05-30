<?php

namespace App\Modules\Scheduling\Enums;

enum SessionTypeEnum: string
{
    case S = 'S'; // Sáng
    case C = 'C'; // Chiều
    case T = 'T'; // Tối

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
            self::S => 'Sáng',
            self::C => 'Chiều',
            self::T => 'Tối',
        };
    }

    public static function fromTime(string $time): self
    {
        $hour = (int) explode(':', $time)[0];
        if ($hour < 12) {
            return self::S;
        } elseif ($hour < 18) {
            return self::C;
        } else {
            return self::T;
        }
    }
}
