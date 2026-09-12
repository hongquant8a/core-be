<?php

namespace App\Modules\TaskAssignment\Observers;

use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Services\ReminderScheduler;

class TaskAssignmentItemObserver
{
    public function __construct(private ReminderScheduler $scheduler) {}

    public function saved(TaskAssignmentItem $item): void
    {
        // Nếu item vừa done → cancel pending reminders
        if ($item->processing_status === TaskProgressStatusEnum::Done->value) {
            $this->scheduler->cancelPending($item);

            return;
        }

        // Với item mới tạo: service tự gọi scheduleFor() sau transaction để tránh deadlock.
        if ($item->wasRecentlyCreated) {
            return;
        }

        // Schedule reminders khi document đã ban hành VÀ end_at/status/deadline thay đổi.
        $item->loadMissing('document');
        $isIssued = ($item->document->status ?? null) === TaskAssignmentDocumentStatusEnum::Issued->value;

        if ($isIssued && $item->wasChanged(['end_at', 'processing_status', 'deadline_type'])) {
            $this->scheduler->scheduleFor($item);
        }
    }

    public function deleted(TaskAssignmentItem $item): void
    {
        $this->scheduler->cancelPending($item);
    }

    /**
     * Khôi phục công việc từ thùng rác → dựng lại lịch nhắc theo thời hạn hiện có.
     *
     * `deleted()` đã huỷ hết lịch nhắc đang chờ lúc xoá, nên không dựng lại thì
     * công việc sống lại mà không còn ai nhắc — im lặng, không có gì báo.
     */
    public function restored(TaskAssignmentItem $item): void
    {
        $this->scheduler->scheduleFor($item);
    }
}
