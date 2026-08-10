# Chat nội bộ — Chat nhóm theo cuộc họp & Nhắn tin riêng (DM)

> Ngày tạo: 13:47:38 10/08/2026
> Cập nhật lần cuối: 13:47:38 10/08/2026

Tính năng mới: chat realtime gồm 2 loại — **chat nhóm** trong tab "Trao đổi" của cuộc họp (khi bật), và **nhắn tin riêng (DM)** giữa 2 user, không giới hạn theo cuộc họp cụ thể. Không hỗ trợ đính kèm file/ảnh, không sửa/xoá tin nhắn lẻ ở v1.

---

## 1. Field mới — `Meeting.internal_chat_enabled`

| Field | Kiểu | Mặc định |
|---|---|---|
| `internal_chat_enabled` | boolean | `false` |

- `bodyParam` của `POST /meetings` và `PUT/PATCH /meetings/{id}`.
- Trả về trong `MeetingResource` ở mọi endpoint show/index/store/update.
- Khi `true` → FE hiện tab "Trao đổi" (chat nhóm) cho chủ trì/thư ký/đại biểu của cuộc họp đó. Khi `false` → toàn bộ endpoint chat nhóm bên dưới trả lỗi 422 `"Cuộc họp chưa bật trao đổi nội bộ."`.

---

## 2. API — Chat nhóm theo cuộc họp

| Method | Path | Mô tả | Quyền |
|---|---|---|---|
| `GET` | `/api/chat/meetings/{meeting}/messages` | Lịch sử tin nhắn nhóm (phân trang, `?limit=`) | Chủ trì/thư ký/đại biểu (`can:participate,meeting`) |
| `POST` | `/api/chat/meetings/{meeting}/messages` | Gửi tin nhắn nhóm — `body: { content }` | như trên |

Response 1 message (`ChatMessageResource`):
```json
{
  "id": 1,
  "chat_conversation_id": 2,
  "sender_id": 3,
  "sender_name": "Trần Thị B",
  "content": "Xin chào cả phòng họp",
  "created_at": "10:00:00 10/08/2026"
}
```

## 3. API — Nhắn tin riêng (DM, toàn hệ thống)

| Method | Path | Mô tả |
|---|---|---|
| `GET` | `/api/chat/conversations` | Danh sách cuộc trò chuyện riêng của tôi (kèm tin nhắn cuối) |
| `GET` | `/api/chat/conversations/{userId}/messages` | Lịch sử tin nhắn với 1 user (phân trang) |
| `POST` | `/api/chat/conversations/{userId}/messages` | Gửi tin nhắn riêng — `body: { content }`, tự tạo cuộc trò chuyện nếu chưa có |

- **Không gắn với meeting_id** — dù 2 người bắt đầu nhắn nhau từ trong 1 cuộc họp, lịch sử vẫn còn khi gặp lại ở cuộc họp khác hoặc ngoài context họp.
- 2 user phải cùng tổ chức hiện tại (`X-Organization-Id`), nếu không → 422.
- `content` bắt buộc, tối đa 2000 ký tự (cả 2 loại chat).

Response `GET /chat/conversations` (`ChatConversationResource`):
```json
[
  {
    "id": 1,
    "counterpart": { "id": 5, "name": "Nguyễn Văn A" },
    "last_message": { "content": "Chào bạn", "sender_id": 5, "created_at": "10:00:00 10/08/2026" },
    "created_at": "09:00:00 10/08/2026"
  }
]
```

---

## 4. Realtime (Reverb) — event `chat-message.created`

| Loại chat | Channel | Ghi chú |
|---|---|---|
| Nhóm (meeting) | `private-meeting.{meetingId}` | Kênh **có sẵn**, đang dùng cho các event điểm danh/biểu quyết — không cần subscribe thêm kênh mới nếu FE đã ở trong meeting. |
| Riêng (DM) | `private-org.{organizationId}.user.{userId}` | Kênh **mới** — FE cần subscribe kênh này (theo `user.id` của chính mình) ngay khi đăng nhập/vào app để nhận toast dù không ở trong màn hình chat cụ thể nào. |

Payload (giống nhau cho cả 2 loại):
```json
{
  "id": 10,
  "chat_conversation_id": 2,
  "type": "meeting_group",
  "meeting_id": 5,
  "sender_id": 3,
  "sender_name": "Trần Thị B",
  "content": "Xin chào",
  "created_at": "2026-08-10T10:00:00+07:00"
}
```
`type` = `"direct"` hoặc `"meeting_group"`; `meeting_id` chỉ có giá trị khi `type=meeting_group`.

- Auth endpoint: `POST /api/broadcasting/auth` (Bearer token, giống các channel khác — xem `docs/api/meeting-websocket.md`).
- Toast: FE tự quyết định hiện toast khi nhận `chat-message.created` mà tab/màn hình chat tương ứng đang không mở. BE **không** tích hợp FCM/Notification engine cho chat ở v1 — nếu app đóng/không mở kết nối WS thì sẽ không nhận được tin nhắn realtime (chỉ thấy khi mở lại và gọi API list).

---

## 5. Admin — quản lý lịch sử chat nhóm theo cuộc họp

Chỉ áp dụng cho chat **nhóm** (không áp dụng DM).

| Method | Path | Quyền | Mô tả |
|---|---|---|---|
| `GET` | `/api/meeting-chat-conversations` | `meeting-chat-conversations.index` | Danh sách cuộc trò chuyện nhóm theo từng meeting (kèm `messages_count`, `last_message_at`) |
| `GET` | `/api/meeting-chat-conversations/{id}` | `meeting-chat-conversations.show` | Chi tiết + toàn bộ tin nhắn |
| `DELETE` | `/api/meeting-chat-conversations/{id}` | `meeting-chat-conversations.destroy` | Xoá vĩnh viễn toàn bộ lịch sử chat của 1 meeting |

**Quan trọng**: quyền `destroy` chỉ gán cho role **Super Admin** — role Admin có `index`/`show` nhưng KHÔNG xoá được, dù các resource admin khác Admin thường có full quyền. FE cần ẩn nút xoá nếu user không phải Super Admin (check qua `permissions` trả về lúc login).

---

## Việc cần làm sau khi BE deploy

- [ ] Chạy migration: `meetings.internal_chat_enabled`, bảng `chat_conversations`, `chat_messages`.
- [ ] Chạy lại `PermissionSeeder` để có 3 quyền `meeting-chat-conversations.{index,show,destroy}`.
- [ ] FE subscribe thêm kênh cá nhân `org.{organizationId}.user.{userId}` cho DM (không dùng lại kênh `meeting.{id}` cho phần này).
- [ ] `sail artisan scribe:generate` để có docs API chi tiết từng endpoint.
