<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\User;
use App\Modules\Core\Requests\FilterRequest;
use App\Modules\Core\Requests\SendChatMessageRequest;
use App\Modules\Core\Resources\ChatConversationResource;
use App\Modules\Core\Resources\ChatMessageResource;
use App\Modules\Core\Services\ChatService;
use App\Modules\Meeting\Models\Meeting;

/**
 * @group Core - Chat
 * @header X-Organization-Id ID tổ chức cần làm việc (bắt buộc). Example: 1
 *
 * Chat nội bộ: nhắn tin riêng (direct message) giữa 2 user và chat nhóm theo cuộc họp
 * (khi meeting.internal_chat_enabled = true). Realtime qua Reverb, không hỗ trợ sửa/xoá
 * tin nhắn, không đính kèm file ở phiên bản này.
 */
class ChatController extends Controller
{
    public function __construct(private ChatService $chatService) {}

    /**
     * Danh sách cuộc trò chuyện riêng của tôi
     *
     * @queryParam limit int Số bản ghi/trang. Example: 20
     *
     * @response 200 {"success": true, "data": [{"id": 1, "counterpart": {"id": 5, "name": "Nguyễn Văn A"}, "last_message": {"content": "Chào bạn", "sender_id": 5, "created_at": "10:00:00 10/08/2026"}, "created_at": "09:00:00 10/08/2026"}]}
     */
    public function directConversations(FilterRequest $request)
    {
        $conversations = $this->chatService->listDirectConversations((int) $request->user()->id, $request->validated());

        return $this->successCollection(ChatConversationResource::collection($conversations));
    }

    /**
     * Lịch sử tin nhắn riêng với 1 user
     *
     * @urlParam user int required ID người nhận. Example: 5
     * @queryParam limit int Số bản ghi/trang. Example: 30
     *
     * @response 200 {"success": true, "data": [{"id": 1, "chat_conversation_id": 1, "sender_id": 5, "sender_name": "Nguyễn Văn A", "content": "Chào bạn", "created_at": "10:00:00 10/08/2026"}]}
     */
    public function directMessages(FilterRequest $request, User $user)
    {
        $messages = $this->chatService->listDirectMessages((int) $request->user()->id, (int) $user->id, $request->validated());

        return $this->successCollection(ChatMessageResource::collection($messages));
    }

    /**
     * Gửi tin nhắn riêng cho 1 user
     *
     * Tự động tạo cuộc trò chuyện nếu chưa có. 2 user phải cùng tổ chức hiện tại.
     *
     * @urlParam user int required ID người nhận. Example: 5
     *
     * @response 201 {"success": true, "message": "Đã gửi tin nhắn.", "data": {"id": 1, "chat_conversation_id": 1, "sender_id": 1, "sender_name": "Tôi", "content": "Chào bạn", "created_at": "10:00:00 10/08/2026"}}
     */
    public function sendDirectMessage(SendChatMessageRequest $request, User $user)
    {
        $message = $this->chatService->sendDirectMessage(
            (int) $request->user()->id,
            (int) $user->id,
            $request->validated('content')
        );

        return $this->successResource(new ChatMessageResource($message), 'Đã gửi tin nhắn.', 201);
    }

    /**
     * Lịch sử tin nhắn nhóm của cuộc họp
     *
     * Yêu cầu cuộc họp đã bật internal_chat_enabled và user là chủ trì/thư ký/đại biểu.
     *
     * @urlParam meeting int required ID cuộc họp. Example: 10
     * @queryParam limit int Số bản ghi/trang. Example: 30
     *
     * @response 200 {"success": true, "data": [{"id": 1, "chat_conversation_id": 2, "sender_id": 3, "sender_name": "Trần Thị B", "content": "Xin chào cả phòng họp", "created_at": "10:00:00 10/08/2026"}]}
     */
    public function meetingMessages(FilterRequest $request, Meeting $meeting)
    {
        $messages = $this->chatService->listMeetingMessages((int) $meeting->id, $request->validated());

        return $this->successCollection(ChatMessageResource::collection($messages));
    }

    /**
     * Gửi tin nhắn nhóm trong cuộc họp
     *
     * @urlParam meeting int required ID cuộc họp. Example: 10
     *
     * @response 201 {"success": true, "message": "Đã gửi tin nhắn.", "data": {"id": 1, "chat_conversation_id": 2, "sender_id": 1, "sender_name": "Tôi", "content": "Xin chào", "created_at": "10:00:00 10/08/2026"}}
     */
    public function sendMeetingMessage(SendChatMessageRequest $request, Meeting $meeting)
    {
        $message = $this->chatService->sendMeetingMessage(
            (int) $meeting->id,
            (int) $request->user()->id,
            $request->validated('content')
        );

        return $this->successResource(new ChatMessageResource($message), 'Đã gửi tin nhắn.', 201);
    }
}
