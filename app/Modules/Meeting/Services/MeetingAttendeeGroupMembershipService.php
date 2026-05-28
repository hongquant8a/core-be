<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quản lý pivot meeting_attendee_group_members — M-N giữa attendee ↔ group.
 *
 *  - listAttendees: phân trang attendees thuộc 1 group + pivot `id` (cho FE delete cụ thể row).
 *  - syncAttendees: sync mode — diff với current pivot, thêm mới + xoá cũ. Idempotent.
 *  - removeAttendee: gỡ 1 attendee khỏi group (tìm pivot row matching → delete).
 *
 * Tất cả validate cross-org: attendee phải thuộc cùng organization với group.
 */
class MeetingAttendeeGroupMembershipService
{
    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function listAttendees(MeetingAttendeeGroup $group, array $filters, int $limit)
    {
        if ($limit === -1) {
            $limit = 1000000;
        }

        $search = $filters['search'] ?? null;
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSort = ['id', 'created_at', 'updated_at', 'status'];
        $sortColumn = in_array($sortBy, $allowedSort, true) ? $sortBy : 'created_at';

        return $group->attendees()
            ->with(['user.profile'])
            ->when($search, function ($q, $search) {
                $q->whereHas('user', function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('meeting_attendees.'.$sortColumn, $sortOrder)
            ->paginate($limit);
    }

    /**
     * Sync mode — diff với current pivot, return summary {added, removed, total}.
     */
    public function syncAttendees(MeetingAttendeeGroup $group, array $attendeeIds): array
    {
        $attendeeIds = array_values(array_unique(array_map('intval', $attendeeIds)));

        // Validate tất cả attendee thuộc cùng organization với group.
        $validIds = MeetingAttendee::query()
            ->where('organization_id', $group->organization_id)
            ->whereIn('id', $attendeeIds)
            ->pluck('id')
            ->all();

        $invalid = array_diff($attendeeIds, $validIds);
        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'meeting_attendee_ids' => ['Có đại biểu không thuộc cùng tổ chức với nhóm.'],
            ]);
        }

        return $this->doSync($group, $attendeeIds);
    }

    /**
     * Sync nhóm bằng user_ids — BE auto find-or-create MeetingAttendee cho từng user
     * (1 attendee per org/user) rồi gắn pivot. Phù hợp FE workflow pick từ User pool.
     */
    public function syncByUserIds(MeetingAttendeeGroup $group, array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        $attendeeIds = [];
        foreach ($userIds as $userId) {
            $attendee = MeetingAttendee::firstOrCreate(
                ['organization_id' => $group->organization_id, 'user_id' => $userId],
                ['status' => 'active']
            );
            $attendeeIds[] = $attendee->id;
        }

        return $this->doSync($group, $attendeeIds);
    }

    /**
     * Internal — diff existing pivot, attach/detach.
     */
    private function doSync(MeetingAttendeeGroup $group, array $attendeeIds): array
    {

        // Diff với pivot hiện có.
        $existing = $group->attendees()->pluck('meeting_attendees.id')->all();
        $toAdd = array_diff($attendeeIds, $existing);
        $toRemove = array_diff($existing, $attendeeIds);

        DB::transaction(function () use ($group, $toAdd, $toRemove) {
            if (! empty($toRemove)) {
                $group->attendees()->detach($toRemove);
            }
            if (! empty($toAdd)) {
                // Pivot có column organization_id NOT NULL — phải set thủ công khi attach.
                $now = now();
                $payload = [];
                foreach ($toAdd as $attendeeId) {
                    $payload[$attendeeId] = [
                        'organization_id' => $group->organization_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                $group->attendees()->attach($payload);
            }
        });

        return [
            'added' => count($toAdd),
            'removed' => count($toRemove),
            'total' => count($attendeeIds),
        ];
    }

    public function removeAttendee(MeetingAttendeeGroup $group, int $attendeeId): void
    {
        $detached = $group->attendees()->detach($attendeeId);
        if ($detached === 0) {
            throw new ModelNotFoundException('Đại biểu không thuộc nhóm này.');
        }
    }

    /**
     * Gỡ attendee ra khỏi nhóm bằng user_id (FE pick từ User pool, không cần biết attendee_id).
     */
    public function removeByUserId(MeetingAttendeeGroup $group, int $userId): void
    {
        $attendee = MeetingAttendee::query()
            ->where('organization_id', $group->organization_id)
            ->where('user_id', $userId)
            ->first();
        if (! $attendee) {
            throw new ModelNotFoundException('User không phải đại biểu của tổ chức.');
        }
        $this->removeAttendee($group, $attendee->id);
    }
}
