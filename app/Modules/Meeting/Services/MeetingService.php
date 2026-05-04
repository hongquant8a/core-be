<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use App\Modules\Meeting\Exports\MeetingExport;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingView;
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

        $meeting->increment('view_count');
        $this->logView($meeting->id, null);

        return $meeting->fresh()->load(['meetingType', 'meetingLocation']);
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

    public function index(array $filters, int $limit)
    {
        return Meeting::with(['meetingType', 'meetingLocation', 'creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Meeting $meeting): Meeting
    {
        return $meeting->load([
            'meetingType',
            'meetingLocation',
            'creator',
            'editor',
            'participants.attendee.user',
            'agendas',
            'documents.documentType',
            'documents.mediaFile',
        ]);
    }

    public function store(array $validated): Meeting
    {
        $payload = [
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return Meeting::create($payload)->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
    }

    public function update(Meeting $meeting, array $validated): Meeting
    {
        $meeting->update($validated);

        return $meeting->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
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

        return $meeting->load(['meetingType', 'meetingLocation', 'creator', 'editor']);
    }

    /**
     * Tạo invitation cho từng participant — idempotent: bỏ qua participant đã có invitation pending/sent.
     */
    private function createInvitationsForParticipants(Meeting $meeting): void
    {
        $participants = MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->where('organization_id', $meeting->organization_id)
            ->pluck('id');

        $existing = MeetingInvitation::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('meeting_participant_id', $participants)
            ->pluck('meeting_participant_id')
            ->all();

        foreach ($participants as $participantId) {
            if (in_array($participantId, $existing, true)) {
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
    }

    public function export(array $filters, string $fileName = 'meetings.xlsx'): BinaryFileResponse
    {
        return Excel::download(new MeetingExport($filters), $fileName);
    }

    private function logView(int $meetingId, ?int $documentId): void
    {
        $request = request();
        MeetingView::create([
            'meeting_id' => $meetingId,
            'meeting_document_id' => $documentId,
            'user_id' => auth()->id(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'viewed_at' => now(),
        ]);
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
