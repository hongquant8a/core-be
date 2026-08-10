<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Enums\ChatConversationTypeEnum;
use App\Modules\Core\Models\ChatConversation;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Resources\MeetingChatConversationResource;
use App\Modules\Core\Services\ChatService;

/**
 * @group Core - Meeting Chat Conversation (Admin)
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Quản trị lịch sử chat nhóm nội bộ theo từng cuộc họp: xem danh sách, xem chi tiết,
 * xoá toàn bộ lịch sử (chỉ Super Admin có quyền xoá).
 */
class MeetingChatConversationController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    /**
     * Danh sách cuộc trò chuyện nhóm theo cuộc họp
     *
     * @queryParam limit int Số bản ghi/trang. Example: 20
     *
     * @response 200 {"success": true, "data": [{"id": 2, "meeting": {"id": 10, "title": "Họp giao ban tuần"}, "messages_count": 12, "last_message_at": "10:00:00 10/08/2026", "created_at": "09:00:00 10/08/2026"}]}
     */
    public function index(FilterRequest $request)
    {
        $conversations = $this->chatService->adminListMeetingConversations($request->validated());

        return $this->successCollection(MeetingChatConversationResource::collection($conversations));
    }

    /**
     * Chi tiết cuộc trò chuyện nhóm (kèm toàn bộ tin nhắn)
     *
     * @urlParam meetingChatConversation int required ID cuộc trò chuyện. Example: 2
     *
     * @response 200 {"success": true, "data": {"id": 2, "meeting": {"id": 10, "title": "Họp giao ban tuần"}, "messages": [{"id": 1, "sender_id": 3, "sender_name": "Trần Thị B", "content": "Xin chào", "created_at": "10:00:00 10/08/2026"}]}}
     */
    public function show(ChatConversation $meetingChatConversation)
    {
        if (! $this->isMeetingGroup($meetingChatConversation)) {
            return $this->notFound('Không tìm thấy cuộc trò chuyện.');
        }

        return $this->successResource(
            new MeetingChatConversationResource($this->chatService->adminShowMeetingConversation($meetingChatConversation))
        );
    }

    /**
     * Xoá toàn bộ lịch sử trò chuyện nhóm của cuộc họp
     *
     * Chỉ Super Admin có quyền này.
     *
     * @urlParam meetingChatConversation int required ID cuộc trò chuyện. Example: 2
     *
     * @response 200 {"success": true, "message": "Đã xoá lịch sử trò chuyện."}
     */
    public function destroy(ChatConversation $meetingChatConversation)
    {
        if (! $this->isMeetingGroup($meetingChatConversation)) {
            return $this->notFound('Không tìm thấy cuộc trò chuyện.');
        }

        $this->chatService->adminDestroyMeetingConversation($meetingChatConversation);

        return $this->success(null, 'Đã xoá lịch sử trò chuyện.');
    }

    private function isMeetingGroup(ChatConversation $conversation): bool
    {
        return $conversation->type === ChatConversationTypeEnum::MeetingGroup->value;
    }
}
