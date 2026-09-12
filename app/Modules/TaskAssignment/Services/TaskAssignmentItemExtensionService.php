<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\TaskAssignment\Enums\TaskDeadlineTypeEnum;
use App\Modules\TaskAssignment\Enums\TaskExtensionStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemExtension;
use App\Services\Notification\Events\DeadlineExtensionRequested;
use App\Services\Notification\Events\DeadlineExtensionReviewed;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TaskAssignmentItemExtensionService
{
    /** Trạng thái không cho xin gia hạn nữa. */
    private const BLOCKED_STATUSES = [
        TaskProgressStatusEnum::Done->value,
        TaskProgressStatusEnum::Cancelled->value,
        // Đang chờ duyệt hoàn thành nghĩa là đã báo cáo xong 100% — lúc đó là chờ
        // duyệt, không phải chờ hạn. Chốt điểm 4 ngày 12/09/2026.
        TaskProgressStatusEnum::PendingApproval->value,
    ];

    /** Lịch sử xin gia hạn của một công việc, mới nhất trước. */
    public function history(int $itemId, int $limit): LengthAwarePaginator
    {
        return TaskAssignmentItemExtension::where('task_assignment_item_id', $itemId)
            ->with(['requestedBy', 'reviewedBy'])
            ->orderByDesc('created_at')
            ->paginate($limit);
    }

    /**
     * Tạo yêu cầu gia hạn.
     *
     * KHÔNG đổi `end_at` ở đây. Hạn chỉ dời khi đã duyệt — trong lúc chờ duyệt
     * việc đang trễ vẫn trễ, vẫn vào thống kê Trễ hạn, lịch nhắc vẫn chạy theo
     * hạn cũ. Gửi yêu cầu không phải là cách tạm hoãn. Chốt điểm 2 ngày 12/09/2026.
     *
     * @throws \RuntimeException
     */
    public function request(TaskAssignmentItem $item, array $validated): TaskAssignmentItemExtension
    {
        if ($item->deadline_type !== TaskDeadlineTypeEnum::HasDeadline->value) {
            throw new \RuntimeException('Công việc không có thời hạn nên không cần gia hạn.');
        }

        if (in_array($item->processing_status, self::BLOCKED_STATUSES, true)) {
            throw new \RuntimeException('Công việc đang ở trạng thái không thể xin gia hạn.');
        }

        return DB::transaction(function () use ($item, $validated) {
            // MySQL không có unique index có điều kiện, nên ràng buộc "mỗi công
            // việc chỉ một yêu cầu chờ duyệt" phải kiểm ở đây. `lockForUpdate`
            // để hai người bấm cùng lúc không tạo được hai yêu cầu.
            $hasPending = TaskAssignmentItemExtension::where('task_assignment_item_id', $item->id)
                ->pending()
                ->lockForUpdate()
                ->exists();

            if ($hasPending) {
                throw new \RuntimeException('Công việc đang có một yêu cầu gia hạn chờ duyệt.');
            }

            $extension = TaskAssignmentItemExtension::create([
                'task_assignment_item_id' => $item->id,
                'requested_by_user_id' => auth()->id(),
                'current_end_at' => $item->end_at,
                'requested_end_at' => $validated['requested_end_at'],
                'reason' => $validated['reason'],
                'status' => TaskExtensionStatusEnum::Pending->value,
            ]);

            // Service chỉ bắn event, không gọi Notification trực tiếp. Event có
            // ShouldDispatchAfterCommit nên chỉ chạy sau khi transaction commit.
            event(new DeadlineExtensionRequested($extension));

            return $extension;
        });
    }

    /**
     * Duyệt yêu cầu — đây là lúc duy nhất `end_at` đổi theo luồng gia hạn.
     *
     * @throws \RuntimeException
     */
    public function approve(TaskAssignmentItemExtension $extension, ?string $note): TaskAssignmentItemExtension
    {
        $this->assertPending($extension);

        return DB::transaction(function () use ($extension, $note) {
            $extension->update([
                'status' => TaskExtensionStatusEnum::Approved->value,
                'reviewed_by_user_id' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            // BẮT BUỘC dùng Eloquent, không dùng query builder hay mass update.
            // TaskAssignmentItemObserver bắt `saved()` + `wasChanged(['end_at'])`
            // để gọi ReminderScheduler::scheduleFor() — huỷ lịch nhắc cũ và dựng
            // lại theo hạn mới. Ghi kiểu khác thì observer không chạy, lịch nhắc
            // giữ nguyên hạn cũ và người thực hiện bị nhắc sai ngày. Lỗi im lặng.
            $item = $extension->item;
            if (! $item) {
                throw new \RuntimeException('Không tìm thấy công việc của yêu cầu gia hạn này.');
            }

            $item->update(['end_at' => $extension->requested_end_at]);

            $fresh = $extension->fresh(['requestedBy', 'reviewedBy', 'item']);
            event(new DeadlineExtensionReviewed($fresh));

            return $fresh;
        });
    }

    /**
     * Từ chối yêu cầu — `end_at` và lịch nhắc giữ nguyên.
     *
     * @throws \RuntimeException
     */
    public function reject(TaskAssignmentItemExtension $extension, string $note): TaskAssignmentItemExtension
    {
        $this->assertPending($extension);

        $extension->update([
            'status' => TaskExtensionStatusEnum::Rejected->value,
            'reviewed_by_user_id' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $fresh = $extension->fresh(['requestedBy', 'reviewedBy', 'item']);
        event(new DeadlineExtensionReviewed($fresh));

        return $fresh;
    }

    /**
     * Người xin thu hồi yêu cầu của chính mình khi còn đang chờ duyệt.
     *
     * @throws \RuntimeException
     */
    public function cancel(TaskAssignmentItemExtension $extension): TaskAssignmentItemExtension
    {
        $this->assertPending($extension);

        if ((int) $extension->requested_by_user_id !== (int) auth()->id()) {
            throw new \RuntimeException('Chỉ người gửi yêu cầu mới thu hồi được yêu cầu đó.');
        }

        $extension->update(['status' => TaskExtensionStatusEnum::Cancelled->value]);

        return $extension->fresh(['requestedBy', 'reviewedBy']);
    }

    /** @throws \RuntimeException */
    private function assertPending(TaskAssignmentItemExtension $extension): void
    {
        if ($extension->status !== TaskExtensionStatusEnum::Pending->value) {
            throw new \RuntimeException('Yêu cầu gia hạn này đã được xử lý.');
        }
    }
}
