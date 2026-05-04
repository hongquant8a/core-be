<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

        $topic = MeetingVoteTopic::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['meeting_vote_topic_id']);

        $participant = MeetingParticipant::query()
            ->where('organization_id', $organizationId)
            ->findOrFail($validated['meeting_participant_id']);

        if ((int) $topic->meeting_id !== (int) $participant->meeting_id) {
            throw new ModelNotFoundException('Người tham dự không thuộc cuộc họp của chương trình biểu quyết.');
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

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
