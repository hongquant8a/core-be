<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use App\Modules\Meeting\Exports\MeetingExport;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Services\Notification\Events\MeetingPublished;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingService
{
    public function publicIndex(array $filters, int $limit)
    {
        $publicFilters = [
            ...$filters,
            'is_public' => true,
            'status' => MeetingStatusEnum::Published->value,
        ];

        return Meeting::with(['meetingType', 'meetingLocation'])
            ->filter($publicFilters)
            ->paginate($limit);
    }

    public function publicShow(Meeting $meeting): Meeting
    {
        if (! $meeting->is_public || $meeting->status !== MeetingStatusEnum::Published->value) {
            throw new ModelNotFoundException('Không tìm thấy cuộc họp công khai.');
        }

        // view_count + meeting_views log → middleware count.meeting.view xử lý (dedupe per user/day).
        // Preload nested cho citizen — chỉ document is_public=true; agenda/vote topic của public meeting
        // mặc định cũng public (theo design AND của is_public).
        return $meeting->load([
            'meetingType',
            'meetingLocation',
            'chairperson',
            'operator',
            'agendas',
            'documents' => fn ($q) => $q->where('is_public', true),
            'documents.documentType',
            'documents.mediaFile',
            'voteTopics',
        ]);
    }

    public function stats(array $filters): array
    {
        $base = Meeting::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'published' => (clone $base)->where('status', MeetingStatusEnum::Published->value)->count(),
            'draft' => (clone $base)->where('status', MeetingStatusEnum::Draft->value)->count(),
        ];
    }

    /**
     * Stats cho trang công khai — phase derived từ start_time/end_time vs now (không cần auth).
     * Scope: is_public=true + status=published.
     */
    public function publicStats(array $filters): array
    {
        $publicFilters = [
            ...$filters,
            'is_public' => true,
            'status' => MeetingStatusEnum::Published->value,
        ];

        $base = Meeting::filter($publicFilters);
        $now = now();

        return [
            'total' => (clone $base)->count(),
            'upcoming' => (clone $base)->where('start_time', '>', $now)->count(),
            'in_progress' => (clone $base)
                ->where('start_time', '<=', $now)
                ->where(fn ($q) => $q->whereNull('end_time')->orWhere('end_time', '>=', $now))
                ->count(),
            'finished' => (clone $base)->whereNotNull('end_time')->where('end_time', '<', $now)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Meeting::with(['meetingType', 'meetingLocation', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Meeting $meeting): Meeting
    {
        return $meeting->load([
            'meetingType',
            'meetingLocation',
            'chairperson',
            'operator',
            'creator.media',
            'editor.media',
            'participants.attendee.user',
            'agendas',
            'documents.documentType',
            'documents.mediaFile',
            'voteTopics',
        ]);
    }

    public function store(array $validated): Meeting
    {
        $payload = [
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return Meeting::create($payload)->load(['meetingType', 'meetingLocation', 'chairperson', 'operator', 'creator.media', 'editor.media']);
    }

    public function update(Meeting $meeting, array $validated): Meeting
    {
        $meeting->update($validated);

        return $meeting->load(['meetingType', 'meetingLocation', 'chairperson', 'operator', 'creator.media', 'editor.media']);
    }

    public function destroy(Meeting $meeting): void
    {
        $meeting->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Meeting::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Meeting::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(Meeting $meeting, string $status): Meeting
    {
        $previous = $meeting->status;

        DB::transaction(function () use ($meeting, $status) {
            $payload = ['status' => $status];

            // Auto-set published_at lần đầu publish; republish sau đó không ghi đè (giữ mốc gốc).
            if ($status === MeetingStatusEnum::Published->value && $meeting->published_at === null) {
                $payload['published_at'] = now();
            }

            $meeting->update($payload);

            if ($status === MeetingStatusEnum::Published->value) {
                $this->createInvitationsForParticipants($meeting);
            }
        });

        if ($previous !== MeetingStatusEnum::Published->value
            && $status === MeetingStatusEnum::Published->value) {
            Event::dispatch(new MeetingPublished($meeting->fresh()));
        }

        return $meeting->load(['meetingType', 'meetingLocation', 'creator.media', 'editor.media']);
    }

    /**
     * Tạo invitation cho participant + chủ trì + thư ký — idempotent.
     * Recipient = participant (đại biểu) HOẶC attendee trực tiếp (chair/operator).
     */
    private function createInvitationsForParticipants(Meeting $meeting): void
    {
        $participantIds = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->where('organization_id', $meeting->organization_id)
            ->pluck('id')
            ->all();

        $existingParticipantIds = MeetingInvitation::query()
            ->where('meeting_id', $meeting->id)
            ->whereNotNull('meeting_participant_id')
            ->pluck('meeting_participant_id')
            ->all();

        foreach ($participantIds as $participantId) {
            if (in_array($participantId, $existingParticipantIds, true)) {
                continue;
            }
            MeetingInvitation::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'meeting_participant_id' => $participantId,
                'send_type' => 'now',
                'status' => 'pending',
            ]);
        }

        // Chủ trì + thư ký — gửi giấy mời qua attendee_id (không qua participants).
        $chairOpAttendeeIds = array_values(array_filter([
            $meeting->chairperson_meeting_attendee_id,
            $meeting->operator_meeting_attendee_id,
        ]));
        if (empty($chairOpAttendeeIds)) {
            return;
        }

        // Tránh trùng nếu chair/operator đồng thời là 1 participant đã được mời.
        $participantAttendeeIds = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('meeting_attendee_id', $chairOpAttendeeIds)
            ->pluck('meeting_attendee_id')
            ->all();

        $existingAttendeeIds = MeetingInvitation::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('meeting_attendee_id', $chairOpAttendeeIds)
            ->pluck('meeting_attendee_id')
            ->all();

        foreach (array_unique($chairOpAttendeeIds) as $attendeeId) {
            if (in_array($attendeeId, $participantAttendeeIds, true) || in_array($attendeeId, $existingAttendeeIds, true)) {
                continue;
            }
            MeetingInvitation::create([
                'organization_id' => $meeting->organization_id,
                'meeting_id' => $meeting->id,
                'meeting_attendee_id' => $attendeeId,
                'send_type' => 'now',
                'status' => 'pending',
            ]);
        }
    }

    public function export(array $filters, string $fileName = 'meetings.xlsx'): BinaryFileResponse
    {
        return Excel::download(new MeetingExport($filters), $fileName);
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
