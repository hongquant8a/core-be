<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Concerns\HasMeetingRole;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Phiếu biểu quyết — đại biểu vote, sửa phiếu, xem aggregate.
 *
 *  - store: đại biểu tự bỏ phiếu cho chính mình. Auto-derive participant từ auth user qua
 *    topic.meeting_id (FE không gửi meeting_participant_id để tránh vote hộ).
 *  - update: chỉ owner đại biểu sửa được phiếu của mình + topic chưa closed.
 *  - destroy / bulkDestroy: admin action — defer.
 *  - stats: aggregate. Chỉ trả khi privileged (chair/op/secretary/admin) HOẶC topic.show_result_on_personal_device=true.
 *  - index/show: chi tiết per-person. Chỉ privileged được xem khi topic.ballot_mode=public_named.
 *    Anonymous mode → 403 cho mọi vai trò (spec line 166: ẩn danh tính trong mọi màn hình nghiệp vụ).
 */
class MeetingVoteResponseService
{
    use HasMeetingRole;

    public function stats(array $filters): array
    {
        $organizationId = $this->resolveCurrentOrganizationId();
        $topicId = $filters['meeting_vote_topic_id'] ?? null;

        // Nếu có lọc theo topic → enforce visibility theo flag show_result_on_personal_device.
        if ($topicId) {
            $topic = MeetingVoteTopic::query()
                ->where('organization_id', $organizationId)
                ->with('meeting')
                ->findOrFail($topicId);

            // Bỏ check theo yêu cầu: open cho cả đại biểu xem kết quả vote
            // $isPrivileged = $this->isPrivilegedForMeeting($topic->meeting);
            // if (! $isPrivileged && ! $topic->show_result_on_personal_device) {
            //     throw new AuthorizationException('Phiên biểu quyết này không cho phép xem kết quả tổng hợp ngoài quản lý/chủ trì.');
            // }
        }

        $base = MeetingVoteResponse::query()
            ->where('organization_id', $organizationId)
            ->when($topicId, fn ($q, $tid) => $q->where('meeting_vote_topic_id', $tid));

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
        $topicId = $filters['meeting_vote_topic_id'] ?? null;

        // index per-person chỉ trả khi public_named + privileged. Anonymous → 403 luôn.
        if ($topicId) {
            $topic = MeetingVoteTopic::query()
                ->where('organization_id', $organizationId)
                ->with('meeting')
                ->findOrFail($topicId);

            $this->ensureCanReadResponseDetail($topic);
        } else {
            // Không có filter topic → chỉ admin org-wide đọc chéo phiếu, đại biểu/chair của 1 cuộc họp riêng không có usecase.
            $user = auth()->user();
            if (! ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Super Admin', 'Admin']))) {
                throw new AuthorizationException('Cần truyền meeting_vote_topic_id để xem phiếu chi tiết.');
            }
        }

        return MeetingVoteResponse::with(['topic', 'participant'])
            ->where('organization_id', $organizationId)
            ->when($topicId, fn ($q, $tid) => $q->where('meeting_vote_topic_id', $tid))
            ->orderByDesc('voted_at')
            ->paginate($limit);
    }

    public function store(array $validated): MeetingVoteResponse
    {
        $organizationId = $this->resolveCurrentOrganizationId();
        $userId = $this->resolveCurrentUserId();

        $topic = MeetingVoteTopic::query()
            ->where('organization_id', $organizationId)
            ->with('meeting.chairperson')
            ->findOrFail($validated['meeting_vote_topic_id']);

        // Spec line 165: chỉ vote khi topic đang opened.
        // Phase derive từ opened_at + closed_at + duration_minutes (timeout) — block cả 3 case:
        // chưa mở (draft), đã đóng manual (closed_at), hoặc tự hết giờ (timeout).
        if ($topic->derivePhase() !== 'opened') {
            throw ValidationException::withMessages([
                'meeting_vote_topic_id' => ['Chương trình biểu quyết chưa mở hoặc đã đóng — không thể bỏ phiếu.'],
            ]);
        }

        // Chỉ participant (đại biểu) đã được xác nhận điểm danh mới được vote.
        // Chủ trì chỉ vote được nếu cũng là đại biểu. Thư ký không được vote.
        $participant = MeetingParticipant::query()
            ->where('meeting_id', $topic->meeting_id)
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->with('attendance')
            ->first();

        if (! $participant) {
            throw new ModelNotFoundException('Bạn không có quyền bỏ phiếu trong cuộc họp này.');
        }
        if (! $participant->attendance || $participant->attendance->status !== 'present') {
            throw ValidationException::withMessages([
                'meeting_vote_topic_id' => ['Bạn chưa được xác nhận điểm danh — không thể bỏ phiếu.'],
            ]);
        }

        $existing = MeetingVoteResponse::query()
            ->where('meeting_vote_topic_id', $topic->id)
            ->where('user_id', $userId)
            ->first();

        // Spam cùng option → no-op + không broadcast (FE counter không bị cộng dồn).
        if ($existing && $existing->option === $validated['option']) {
            return $existing->load(['topic', 'participant']);
        }

        $previousOption = $existing?->option;

        $response = MeetingVoteResponse::updateOrCreate(
            [
                'meeting_vote_topic_id' => $topic->id,
                'user_id' => $userId,
            ],
            [
                'organization_id' => $organizationId,
                // participant_id nullable — set nếu user là participant, null nếu là chair.
                'meeting_participant_id' => $participant?->id,
                'option' => $validated['option'],
                'voted_at' => now(),
            ]
        )->load(['topic', 'participant']);

        // Broadcast kèm previous_option để FE biết decrement cái cũ + increment cái mới
        // khi đại biểu đổi ý vote (vd "Tán thành" → "Ý kiến khác").
        broadcast(new \App\Modules\Meeting\Events\MeetingVoteResponseAdded($response, $previousOption))->toOthers();

        return $response;
    }

    public function show(MeetingVoteResponse $meetingVoteResponse): MeetingVoteResponse
    {
        $meetingVoteResponse->loadMissing(['topic.meeting', 'participant']);
        $this->ensureCanReadResponseDetail($meetingVoteResponse->topic);

        return $meetingVoteResponse;
    }

    /**
     * Enforce: chỉ trả chi tiết phiếu khi topic.ballot_mode=public_named + caller là privileged
     * (chair/op/secretary/admin của meeting). Anonymous → 403 luôn.
     */
    private function ensureCanReadResponseDetail(MeetingVoteTopic $topic): void
    {
        if ($topic->ballot_mode === 'anonymous') {
            throw new AuthorizationException('Phiên ẩn danh không có danh sách phiếu chi tiết — dùng endpoint /stats để xem tổng hợp.');
        }

        $topic->loadMissing('meeting');
        if (! $this->isPrivilegedForMeeting($topic->meeting)) {
            throw new AuthorizationException('Chỉ chủ trì / thư ký họp / quản trị mới xem được chi tiết phiếu biểu quyết.');
        }
    }

    public function update(MeetingVoteResponse $meetingVoteResponse, array $validated): MeetingVoteResponse
    {
        $this->ensureOwned($meetingVoteResponse);

        // Spec line 165: sau khi đóng biểu quyết thì không cho sửa phiếu.
        // Reload topic + derive phase (closed nếu manual close HOẶC timeout duration_minutes).
        $topic = MeetingVoteTopic::find($meetingVoteResponse->meeting_vote_topic_id);
        if ($topic && $topic->derivePhase() === 'closed') {
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
     * Scope theo user_id trực tiếp — đúng cho cả chair (không có participant) lẫn đại biểu.
     */
    private function ensureOwned(MeetingVoteResponse $response): void
    {
        if ((int) $response->user_id !== $this->resolveCurrentUserId()) {
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
