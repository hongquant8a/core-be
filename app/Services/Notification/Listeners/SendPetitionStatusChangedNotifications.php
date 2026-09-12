<?php

namespace App\Services\Notification\Listeners;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentEmployeeDepartment;
use App\Services\Notification\Enums\NotificationModuleEnum;
use App\Services\Notification\Events\PetitionStatusChanged;
use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPetitionStatusChangedNotifications implements ShouldQueue
{
    /** Đẩy vào queue tier `notifications` (Horizon supervisor riêng), không dồn vào `default`. */
    public $queue = 'notifications';

    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(PetitionStatusChanged $event): void
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

        // Báo cho người đại diện phòng ban tiếp nhận, TRỪ người vừa đổi trạng
        // thái — họ tự làm nên không cần báo lại.
        //
        // Cố ý KHÔNG báo cho `creator`: bảng `task_assignment_petitions` không
        // có chỗ nào ghi người tạo (`created_by` luôn NULL, kể cả đơn nhập từ hệ
        // thống cũ), nên nhánh đó sẽ không bao giờ gửi được cho ai. Khi nào hệ
        // thống ghi được người lập đơn thì mở rộng thêm ở đây.
        //
        // Bảng `task_assignment_employee_department` không có cột `user_id` — nó
        // nối qua `task_assignment_employees` (xem chú thích đầu model).
        $actorId = (int) auth()->id();

        $userIds = TaskAssignmentEmployeeDepartment::query()
            ->where('task_assignment_department_id', $petition->department_id)
            ->where('organization_id', $organizationId)
            ->where('is_representative', true)
            ->activeEmployee()
            ->with('employee:id,user_id')
            ->get()
            ->pluck('employee.user_id')
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === $actorId);

        $recipients = User::whereIn('id', $userIds)->get();

        foreach ($recipients as $recipient) {
            $this->dispatcher->dispatch(
                eventKey: 'petition_status_changed',
                recipient: $recipient,
                notifiable: $petition,
                channels: $channels,
                builder: $this->registry->for('petition_status_changed'),
                organizationId: $organizationId,
                extraArgs: [$event->previousStatus],
            );
        }
    }

    private function resolveChannels(int $organizationId): array
    {
        $config = NotificationEventConfig::with('schedules')
            ->where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('organization_id', $organizationId)
            ->where('event_key', 'petition_status_changed')
            ->first();
        if (! $config || ! $config->enabled) {
            return [];
        }
        $instant = $config->schedules->firstWhere('moment', null);

        return $instant?->channels ?? [];
    }
}
