<?php

namespace App\Services\Notification\Enums;

/**
 * Đăng ký module có notification — FE dùng để filter config theo module.
 * Khi thêm module mới, thêm case ở đây + map events vào module trong NotificationEventEnum::module().
 */
enum NotificationModuleEnum: string
{
    case TaskAssignment = 'task_assignment';
    case Meeting = 'meeting';

    public function label(): string
    {
        return match ($this) {
            self::TaskAssignment => 'Giao việc',
            self::Meeting => 'Cuộc họp',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function rule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * List event_keys thuộc module này.
     */
    public function events(): array
    {
        return array_values(array_filter(
            NotificationEventEnum::cases(),
            fn (NotificationEventEnum $e) => $e->module() === $this,
        ));
    }
}
