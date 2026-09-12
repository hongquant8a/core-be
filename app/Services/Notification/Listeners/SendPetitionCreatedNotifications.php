<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\PetitionCreated;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPetitionCreatedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(PetitionCreated $event): void
    {
        $petition = $event->petition->load('department');
        $organizationId = (int) $petition->organization_id;
        if (! $organizationId) {
            return;
        }

        $channels = $this->resolveChannels($organizationId);
        if (empty($channels)) {
            return;
        }

        // Đơn thư không có danh sách người thực hiện, chỉ có phòng ban tiếp nhận
        // — nên địa chỉ đúng nhất là người đại diện của phòng ban đó.
        // Bảng `task_assignment_employee_department` KHÔNG có cột `user_id` — nó
        // nối qua `task_assignment_employees`, nên phải đi bằng quan hệ
        // `employee.user` chứ không pluck thẳng (xem chú thích ở đầu model).
        $userIds = TaskAssignmentEmployeeDepartment::query()
            ->where('task_assignment_department_id', $petition->department_id)
            ->where('organization_id', $organizationId)
            ->where('is_representative', true)
            ->activeEmployee()
            ->with('employee:id,user_id')
            ->get()
            ->pluck('employee.user_id')
            ->filter()
            ->unique();

        $recipients = User::whereIn('id', $userIds)->get();

        foreach ($recipients as $recipient) {
            $this->dispatcher->dispatch(
                eventKey: 'petition_created',
                recipient: $recipient,
                notifiable: $petition,
                channels: $channels,
                builder: $this->registry->for('petition_created'),
                organizationId: $organizationId,
            );
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'petition_created')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
