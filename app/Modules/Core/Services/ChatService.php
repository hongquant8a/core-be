<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\ChatConversationTypeEnum;
use App\Modules\Core\Events\ChatMessageSent;
use App\Modules\Core\Models\ChatConversation;
use App\Modules\Core\Models\ChatMessage;
use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function listDirectConversations(int $userId, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = $filters['limit'] ?? 20;

        return ChatConversation::direct()
            ->where(fn ($q) => $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId))
            ->with(['userOne', 'userTwo', 'messages' => fn ($q) => $q->latest('created_at')->limit(1)])
            ->orderByDesc('updated_at')
            ->paginate($limit);
    }

    public function getOrCreateDirectConversation(int $userIdA, int $userIdB): ChatConversation
    {
        if ($userIdA === $userIdB) {
            throw ValidationException::withMessages([
                'user_id' => ['Không thể tự nhắn tin cho chính mình.'],
            ]);
        }

        $this->assertSameOrganization($userIdA);
        $this->assertSameOrganization($userIdB);

        $userOneId = min($userIdA, $userIdB);
        $userTwoId = max($userIdA, $userIdB);

        return ChatConversation::firstOrCreate(
            [
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'type' => ChatConversationTypeEnum::Direct->value,
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]
        );
    }

    public function listDirectMessages(int $userId, int $counterpartId, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = $filters['limit'] ?? 30;
        $userOneId = min($userId, $counterpartId);
        $userTwoId = max($userId, $counterpartId);

        $conversation = ChatConversation::direct()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('user_one_id', $userOneId)
            ->where('user_two_id', $userTwoId)
            ->first();

        if (! $conversation) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $limit);
        }

        return $conversation->messages()->with('sender')->paginate($limit);
    }

    public function sendDirectMessage(int $senderId, int $receiverId, string $content): ChatMessage
    {
        $conversation = $this->getOrCreateDirectConversation($senderId, $receiverId);

        $message = $conversation->messages()->create([
            'organization_id' => $conversation->organization_id,
            'sender_user_id' => $senderId,
            'content' => $content,
        ])->load('sender');

        $conversation->touch();

        broadcast(new ChatMessageSent($message, $conversation))->toOthers();

        return $message;
    }

    public function getOrCreateMeetingConversation(int $meetingId): ChatConversation
    {
        $meeting = Meeting::find($meetingId);

        if (! $meeting) {
            throw new ModelNotFoundException('Không tìm thấy cuộc họp.');
        }

        if (! $meeting->internal_chat_enabled) {
            throw ValidationException::withMessages([
                'meeting_id' => ['Cuộc họp chưa bật trao đổi nội bộ.'],
            ]);
        }

        return ChatConversation::firstOrCreate(
            [
                'organization_id' => $meeting->organization_id,
                'type' => ChatConversationTypeEnum::MeetingGroup->value,
                'meeting_id' => $meetingId,
            ]
        );
    }

    public function listMeetingMessages(int $meetingId, array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = $filters['limit'] ?? 30;
        $conversation = $this->getOrCreateMeetingConversation($meetingId);

        return $conversation->messages()->with('sender')->paginate($limit);
    }

    public function sendMeetingMessage(int $meetingId, int $senderUserId, string $content): ChatMessage
    {
        $conversation = $this->getOrCreateMeetingConversation($meetingId);

        $message = $conversation->messages()->create([
            'organization_id' => $conversation->organization_id,
            'sender_user_id' => $senderUserId,
            'content' => $content,
        ])->load('sender');

        $conversation->touch();

        broadcast(new ChatMessageSent($message, $conversation))->toOthers();

        return $message;
    }

    public function adminListMeetingConversations(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $limit = $filters['limit'] ?? 20;

        return ChatConversation::meetingGroup()
            ->with('meeting')
            ->withCount('messages')
            ->withMax('messages', 'created_at')
            ->orderByDesc('updated_at')
            ->paginate($limit);
    }

    public function adminShowMeetingConversation(ChatConversation $conversation): ChatConversation
    {
        return $conversation->load(['meeting', 'messages.sender']);
    }

    public function adminDestroyMeetingConversation(ChatConversation $conversation): void
    {
        DB::transaction(function () use ($conversation) {
            $conversation->messages()->delete();
            $conversation->delete();
        });
    }

    /**
     * User phải có role trong tổ chức hiện tại (qua model_has_roles) mới được nhắn tin —
     * cùng cơ chế check với StoreMeetingRequest::qrManagerSameOrgRule().
     */
    private function assertSameOrganization(int $userId): void
    {
        $orgId = $this->resolveCurrentOrganizationId();

        $hasAccess = DB::table('model_has_roles')
            ->where('model_id', $userId)
            ->where('model_type', (new User)->getMorphClass())
            ->where('organization_id', $orgId)
            ->exists();

        if (! $hasAccess) {
            throw ValidationException::withMessages([
                'user_id' => ['Người dùng không thuộc tổ chức hiện tại.'],
            ]);
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
}
