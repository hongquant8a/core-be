<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Phiếu biểu quyết — đại biểu vote, sửa phiếu, xem aggregate.
 *
 *  - store: đại biểu tự bỏ phiếu cho chính mình. Auto-derive participant từ auth user qua
 *    topic.meeting_id (FE không gửi meeting_participant_id để tránh vote hộ).
 *  - update: chỉ owner đại biểu sửa được phiếu của mình + topic chưa closed.
 *  - destroy / bulkDestroy: admin action — defer (Sprint 1 sẽ enforce qua middleware meeting.role).
 *  - stats: aggregate, ai cũng xem được.
 *  - index: detail responses — anonymous logic FE handle theo topic.ballot_mode.
 */
class MeetingVoteResponseService
{
    public function stats(array $filters): array
    {
        $organizationId = $this->resolveCurrentOrganizationId();
        $base = MeetingVoteResponse::query()
            ->where('organization_id', $organizationId)
            ->when($filters['meeting_vote_topic_id'] ?? null, fn ($q, $topicId) => $q->where('meeting_vote_topic_id', $topicId));

        return [
            'total' => (clone $base)->count(),
            'agree' => (clone $base)->where('option', 'agree')->count(),
            'disagree' => (clone $base)->where('option', 'disagree')->count(),
            'approve' => (clone $base)->where('option', 'approve')->count(),
            'reject' => (clone $base)->where('option', 'reject')->count(),
            'abstain' => (clone $base)->where('option', 'abstain')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        $organizationId = $this->resolveCurrentOrganizationId();

        return MeetingVoteResponse::with(['topic', 'participant'])
            ->where('organization_id', $organizationId)
            ->when($filters['meeting_vote_topic_id'] ?? null, fn ($q, $topicId) => $q->where('meeting_vote_topic_id', $topicId))
            ->orderByDesc('voted_at')
            ->paginate($limit);
    }

    public function store(array $validated): MeetingVoteResponse
    {
        $organizationId = $this->resolveCurrentOrganizationId();
        $userId = $this->resolveCurrentUserId();

        $topic = MeetingVoteTopic::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['meeting_vote_topic_id']);

        // Spec line 165: chỉ vote khi topic đang opened.
        if ($topic->status !== 'opened') {
            throw ValidationException::withMessages([
                'meeting_vote_topic_id' => ['Chương trình biểu quyết chưa mở hoặc đã đóng — không thể bỏ phiếu.'],
            ]);
        }

        // Auto-derive participant từ auth user — tìm participant của user trong meeting của topic.
        $participant = MeetingParticipant::query()
            ->where('meeting_id', $topic->meeting_id)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->first();

        if (! $participant) {
            throw new ModelNotFoundException('Bạn không phải đại biểu của cuộc họp này.');
        }

        return MeetingVoteResponse::updateOrCreate(
            [
                'meeting_vote_topic_id' => $topic->id,
                'meeting_participant_id' => $participant->id,
            ],
            [
                'organization_id' => $organizationId,
                'option' => $validated['option'],
                'voted_at' => now(),
            ]
        )->load(['topic', 'participant']);
    }

    public function show(MeetingVoteResponse $meetingVoteResponse): MeetingVoteResponse
    {
        return $meetingVoteResponse->load(['topic', 'participant']);
    }

    public function update(MeetingVoteResponse $meetingVoteResponse, array $validated): MeetingVoteResponse
    {
        $this->ensureOwned($meetingVoteResponse);

        // Spec line 165: sau khi đóng biểu quyết thì không cho sửa phiếu.
        // Reload topic để lấy status mới nhất, tránh stale relation cached.
        $topic = MeetingVoteTopic::find($meetingVoteResponse->meeting_vote_topic_id);
        if ($topic && $topic->status === 'closed') {
            throw ValidationException::withMessages([
                'meeting_vote_topic_id' => ['Chương trình biểu quyết đã đóng — không thể sửa phiếu.'],
            ]);
        }

        $meetingVoteResponse->update([
            'option' => $validated['option'],
            'voted_at' => now(),
        ]);

        return $meetingVoteResponse->load(['topic', 'participant']);
    }

    public function destroy(MeetingVoteResponse $meetingVoteResponse): void
    {
        $meetingVoteResponse->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingVoteResponse::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    /**
     * Throw 404 nếu phiếu không thuộc auth user (tránh leak ID-existence).
     */
    private function ensureOwned(MeetingVoteResponse $response): void
    {
        $userId = $this->resolveCurrentUserId();
        $response->loadMissing('participant.attendee');

        if ((int) ($response->participant?->attendee?->user_id ?? 0) !== (int) $userId) {
            throw new ModelNotFoundException('Không tìm thấy phiếu.');
        }
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }

    private function resolveCurrentUserId(): int
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new ModelNotFoundException('Cần đăng nhập để bỏ phiếu.');
        }

        return (int) $userId;
    }
}
