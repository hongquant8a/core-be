# API cho FE — Phòng họp không giấy

Doc reference cho FE implement **trang danh sách meeting + 8 tab trong chi tiết meeting**. Endpoint trong doc này **đã sẵn sàng trên BE**, FE có thể bắt đầu ngay.

> Endpoint runtime mới (Sprint 1: pause/checkin/approve attendance/start discussion/...) sẽ bổ sung sau — đánh dấu **🚧 Coming Soon** trong doc này. FE tạm bỏ Tab 7 (Điều hành) hoặc mock UI; các tab khác đủ API để chạy thật.
>
> Tham khảo thiết kế tổng thể: [docs/answer/meeting-runtime-design.md](../answer/meeting-runtime-design.md).
> Tham khảo API spec đầy đủ: [docs/api/meeting-runtime.md](./meeting-runtime.md) (organize theo resource).
> Doc này organize theo **screen/tab** để FE dễ map vào component.

## Common

**Headers**:
```
Authorization: Bearer {token}
X-Organization-Id: {organization_id}
Content-Type: application/json
```

**Response envelope chuẩn**:
```json
// Index (list)
{ "success": true, "data": { "items": [...], "pagination": {...} } }

// Show / Store / Update
{ "success": true, "message": "...", "data": {...} }

// Stats / Destroy / Bulk
{ "success": true, "message": "...", "data": {...}|null }
```

**Datetime format**: `H:i:s d/m/Y` (vd `08:30:00 15/05/2026`). Time-only (giờ agenda): `H:i:s`.

**Phase derived ở FE** (xem detail design doc):
```js
function getMeetingPhase(meeting) {
  if (meeting.status === 'cancelled') return 'cancelled'
  if (meeting.status === 'draft')     return 'draft'
  const now = new Date()
  const start = parseDateTime(meeting.start_time)
  const end = meeting.end_time ? parseDateTime(meeting.end_time) : null
  if (now < start)        return 'upcoming'
  if (end && now > end)   return 'finished'
  return 'in_progress'
}
```

**Identify role trong meeting** (tạm thời client-side cho đến khi BE thêm `current_user_role` field):
```js
function getMyMeetingRole(meeting, currentUser) {
  // chairperson/operator object hiện chỉ trả attendee.id — FE cần load thêm
  // hoặc check qua participants list nếu cần chính xác
  // SPRINT 1 sẽ có meeting.current_user_role trực tiếp
  return null  // placeholder — disable Tab 6/7 cho đến khi field này có
}
```

---

## Screen 0 — Danh sách cuộc họp (Index)

**URL FE đề xuất**: `/`

> ⚠️ **Trang index chung cho cả guest và auth user**. Dùng endpoint `/api/meetings/public` với token nếu có:
> - Guest (không token) → thấy meeting `is_public=true + status=published`
> - Auth user (có token) → thấy public meeting + meeting họ là chủ trì/thư ký/đã được mời tham gia
> - Admin scope (mọi meeting org) → trang riêng dùng `/api/meetings` (yêu cầu `meetings.index` permission)

### 0.1 Stats cards (top of page)

```
GET /api/meetings/public/stats?{filters}     (guest/auth - dùng cho trang chung)
GET /api/meetings/stats?{filters}             (admin - mọi meeting org)
```

Response public stats:
```json
{ "success": true, "data": { "total": 27, "upcoming": 5, "in_progress": 1, "finished": 21 } }
```

Response auth admin stats:
```json
{ "success": true, "data": { "total": 27, "published": 18, "draft": 9 } }
```

### 0.2 List + filter

```
GET /api/meetings/public        (trang chung - guest + auth)
GET /api/meetings               (admin scope - cần permission meetings.index)
```

**`/api/meetings/public`** — endpoint chính cho trang index FE:
- Guest: chỉ meeting `is_public=true + status=published`
- Auth (gửi token): union với meeting user là chair/operator/participant
- Header `Authorization` optional. Nếu có thì server expand scope tự động.

**`/api/meetings`** — chỉ dùng cho trang admin tổng thể (thấy mọi meeting của org). Không dùng cho trang index chung.

Query params (chung cho cả 2):
| Param | Kiểu | Mô tả |
|---|---|---|
| `search` | string | Tìm theo title |
| `meeting_type_id` | int | Filter loại |
| `status` | enum | `draft\|published\|cancelled` (admin scope only) |
| `is_public` | bool | (admin scope only) |
| `from_date` / `to_date` | date | Khoảng `created_at` |
| `sort_by` | enum | `id\|title\|start_time\|created_at\|updated_at` |
| `sort_order` | enum | `asc\|desc` |
| `limit` | int (1-100) | per page |

Response:
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "title": "Họp ban chỉ đạo cải cách hành chính",
        "meeting_type_name": "Cuộc họp định kỳ",
        "meeting_location_name": "Phòng họp UBND Phường Hoà Cường",
        "chairperson_meeting_attendee_id": 12,
        "chairperson": { "id": 12, "name": "Nguyễn Văn A", "position_name": "Chủ tịch UBND Phường", ... },
        "operator_meeting_attendee_id": 13,
        "operator": { "id": 13, "name": "Trần Thị B", ... },
        "start_time": "08:00:00 23/02/2026",
        "end_time": "11:30:00 23/02/2026",
        "status": "published",
        "is_public": true,
        "view_count": 145,
        "published_at": "08:00:00 08/02/2026",
        "created_by": { "id": 1, "name": "Admin", "media_url": "..." },
        "updated_by": { "id": 1, "name": "Admin", "media_url": "..." },
        "created_at": "08:00:00 01/02/2026",
        "updated_at": "08:00:00 08/02/2026"
      }
    ],
    "pagination": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27 }
  }
}
```

> Chú ý: `chairperson` / `operator` ở index chỉ trả basic info, không có nested `participants/agendas/documents/vote_topics` — chỉ có ở Show.

### 0.3 Bulk delete

```
DELETE /api/meetings/bulk-delete
Body: { "ids": [1, 2, 3] }
```

> Axios: `api.delete('/meetings/bulk-delete', { data: { ids: [...] } })`.

---

## Meeting detail header (chung cho 8 tab)

Trước khi vào tab, FE gọi 1 lần để lấy meta + permission gate:

```
GET /api/meetings/public/{id}     (trang chung - guest + auth)
GET /api/meetings/{id}            (admin scope - cần permission meetings.show)
```

> **Auth scoping behavior** (cả 2 endpoint):
> - Guest hit `public/{id}`: chỉ truy cập được meeting `is_public=true + status=published` (404 nếu khác)
> - Auth user hit `public/{id}`: truy cập được meeting công khai HOẶC meeting họ là chair/op/participant
> - Documents preload tự động filter `is_public=true` cho user **không tham gia**; participant thấy hết

Response (kèm nested):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "...",
    "start_time": "08:00:00 23/02/2026",
    "end_time": "11:30:00 23/02/2026",
    "status": "published",
    "chairperson": { "id": 12, "name": "Nguyễn Văn A", ... },
    "operator": { "id": 13, "name": "Trần Thị B", ... },
    "participants": [...],   // preload — show this user list
    "agendas": [...],        // preload — Tab 1 dùng
    "documents": [...],      // preload — Tab 2 dùng
    "vote_topics": [...],    // preload — Tab 3 dùng
    ...
  }
}
```

> Show preload 4 nested arrays để FE giảm số request. Phase/timer/role gating: FE compute theo Common.

---

## Tab 1 — Chương trình

**URL**: `/meetings/{id}#agenda`

### 1.1 Load chương trình + meeting context

Đã có sẵn từ `GET /api/meetings/{id}` (preload `agendas[]`).

Nếu muốn refresh riêng (hoặc phân trang nhiều agenda):
```
GET /api/meeting-agendas?meeting_id={id}
```

Query params: `search`, `parent_id`, `sort_by` (`id|sort_order|start_time|created_at|updated_at`), `sort_order`, `limit`.

Response item:
```json
{
  "id": 1,
  "meeting_id": 1,
  "start_time": "08:00:00",
  "end_time": "08:30:00",
  "content": "Khai mạc, tuyên bố lý do, giới thiệu đại biểu",
  "person_in_charge": "Chủ trì cuộc họp",
  "allow_discussion_registration": false,
  "allow_question_registration": false,
  "parent_id": null,
  "sort_order": 1,
  "created_at": "08:00:00 01/02/2026",
  "updated_at": "08:00:00 01/02/2026"
}
```

> "Đang diễn ra" badge → FE compute: `agenda.start_time <= now <= agenda.end_time`.
> Time hiển thị "10 phút" → FE compute: `(end_time - start_time) / 60`.

### 1.2 THAO TÁC NHANH (đại biểu)

4 action self-service. **Auto-derive participant từ auth user** — FE chỉ gửi `meeting_id` (hoặc `participant_id` cho `respond`).

| Action | Endpoint | Khi |
|---|---|---|
| **Xác nhận tham gia** | `PATCH /api/meeting-participants/{id}/respond` body `{ "response_status": "accepted" }` | Trước họp (response_status=`pending`) |
| **Từ chối tham gia** | `PATCH /api/meeting-participants/{id}/respond` body `{ "response_status": "declined", "absence_reason": "..." }` | Trước họp |
| **Điểm Danh** | `POST /api/meeting-attendances/checkin` body `{ "meeting_id": X }` | Tại họp — status=`pending` chờ operator duyệt |
| **Báo Vắng** | `POST /api/meeting-attendances/mark-absent` body `{ "meeting_id": X, "note": "..." }` | Tại họp — status=`absent` ngay |

> ❌ **Uỷ Quyền Tham Gia** — không có endpoint, không trong scope hiện tại.

#### Xác nhận / Từ chối tham gia

```http
PATCH /api/meeting-participants/12/respond
Content-Type: application/json

{ "response_status": "accepted" }
```

```http
PATCH /api/meeting-participants/12/respond
Content-Type: application/json

{ "response_status": "declined", "absence_reason": "Có công tác đột xuất" }
```

> **Ownership**: chỉ owner đại biểu (auth user trùng `attendee.user_id`) mới gọi được. User khác → 404.
> Set `responded_at = now()` tự động.
>
> FE biết `participant_id` của user hiện tại từ `meeting.participants[]` find theo `attendee.user.id == currentUser.id`.

#### Điểm Danh

```http
POST /api/meeting-attendances/checkin
Content-Type: application/json

{ "meeting_id": 1 }
```

> BE auto-derive participant từ `auth()->id()`. User không phải đại biểu của meeting → 404.
> Status mặc định = `pending` (chờ operator approve ở Tab 7 — Sprint 1).
> Idempotent: F5 hoặc click lần 2 không tạo trùng — update row hiện tại.

#### Báo Vắng

```http
POST /api/meeting-attendances/mark-absent
Content-Type: application/json

{ "meeting_id": 1, "note": "Bị ốm đột xuất" }
```

> Status set ngay = `absent`, không cần duyệt. Nếu đã có row checkin trước đó → update sang absent.
> `note` optional.

---

## Tab 2 — Tài liệu + Ghi chú cá nhân

**URL**: `/meetings/{id}#documents`

### 2.1 Danh sách tài liệu

Đã có sẵn từ `GET /api/meetings/public/{id}` hoặc `/api/meetings/{id}` (preload `documents[]`).

> **Document visibility (auto theo participation)** — BE filter `is_public` theo quan hệ user-meeting:
> - Guest hoặc auth-không-tham-gia → chỉ thấy doc `is_public=true`
> - Chủ trì / thư ký / đã được mời tham gia → thấy mọi doc (kể cả `is_public=false`)
>
> FE **không** gửi flag — BE detect từ `auth()->id()` so với chairperson/operator/participants của meeting. Trang chung dùng chung 1 endpoint cho mọi role.

Refresh riêng (khi cần search/sort/page độc lập):
```
GET /api/meeting-documents/public?meeting_id={id}     (trang chung - guest + auth)
GET /api/meeting-documents?meeting_id={id}            (auth scope, cùng filter participation)
```

Query params: `meeting_agenda_id`, `meeting_document_type_id`, `search` (title/document_number), `is_public`, `sort_by` (`id|sort_order|created_at|updated_at`), `sort_order`, `limit`.

Response item:
```json
{
  "id": 1,
  "meeting_id": 1,
  "meeting_agenda_id": null,
  "meeting_document_type_name": "Tờ trình",
  "title": "Tờ trình về kế hoạch phát triển kinh tế - xã hội tháng 02/2026",
  "document_number": "TT-UBND-02/2026",
  "summary": null,
  "media_id": 42,
  "file_url": "/storage/42/to-trinh.pdf",
  "file_name": "to-trinh.pdf",
  "is_public": true,
  "download_count": 12,
  "sort_order": 1,
  "created_by": { "id": 1, "name": "Admin", ... },
  "updated_by": { "id": 1, "name": "Admin", ... },
  "created_at": "08:00:00 01/02/2026",
  "updated_at": "08:00:00 01/02/2026"
}
```

**Action xem**: FE mở `file_url` trong tab mới (ví dụ `<a target="_blank" href={file_url}>`). BE không track view per-document.

**Action tải xuống** (track download_count + log):
```
GET /api/meeting-documents/{id}/download           (auth)
GET /api/meeting-documents/public/{id}/download    (public)
```

→ BE redirect 302 tới `file_url` + `Cache-Control: no-store` (đảm bảo mỗi click F5 đều hit BE để count). FE đổi nút "⬇ Tải":
```html
<a href={`/api/meeting-documents/${doc.id}/download`} download>Tải</a>
```

Increment `download_count` + log row vào `meeting_views`.

### 2.2 GHI CHÚ CÁ NHÂN

```
GET /api/meeting-personal-notes?meeting_id={id}
```

Trả note thuộc về current user trong meeting này. **Service auto-scope theo `auth()->id()`** — user không thể đọc/sửa/xóa note của người khác (404 nếu cố truy cập). Không cần FE gửi `meeting_participant_id` filter.

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 5,
        "meeting_id": 1,
        "content": "Cần làm rõ điểm về ngân sách 2026",
        "sort_order": 1,
        "created_at": "08:30:00 23/02/2026",
        "updated_at": "08:30:00 23/02/2026",
        "attachments": [...]
      }
    ],
    "pagination": {...}
  }
}
```

**Tạo note (Lưu Ghi Chú)**:
```http
POST /api/meeting-personal-notes
Content-Type: application/json

{
  "meeting_id": 1,
  "content": "Nội dung ghi chú (rich text/HTML)"
}
```

**Sửa**:
```http
PATCH /api/meeting-personal-notes/5
{ "content": "Nội dung đã sửa" }
```

**Xóa**:
```http
DELETE /api/meeting-personal-notes/5
```

> "LỊCH SỬ GHI CHÚ" trong screenshot = list các note đã lưu (chính là response 2.2 — kế thừa `created_at`).

### 2.3 Đính kèm file vào note (optional)

```http
POST /api/meeting-personal-note-attachments
Content-Type: multipart/form-data

meeting_personal_note_id=5
file=@audio.mp3
```

Hoặc list:
```
GET /api/meeting-personal-note-attachments?meeting_personal_note_id=5
```

---

## Tab 3 — Biểu quyết

**URL**: `/meetings/{id}#voting`

### 3.1 Danh sách topic (NỘI DUNG BIỂU QUYẾT)

Đã có sẵn từ `GET /api/meetings/{id}` (preload `vote_topics[]`).

Refresh riêng:
```
GET /api/meeting-vote-topics?meeting_id={id}
```

Query params: `status`, `search`, `sort_by` (`id|sort_order|created_at|updated_at`), `sort_order`, `limit`.

Response item:
```json
{
  "id": 1,
  "meeting_id": 1,
  "meeting_agenda_id": null,
  "title": "Thông qua kế hoạch phát triển kinh tế - xã hội tháng 02/2026",
  "vote_type": "agree_disagree_abstain",
  "ballot_mode": "public_named",
  "show_result_on_projector": true,
  "show_result_on_personal_device": true,
  "sort_order": 1,
  "status": "closed",
  "opened_at": "10:15:00 23/02/2026",
  "closed_at": "10:25:00 23/02/2026",
  "created_at": "...",
  "updated_at": "..."
}
```

### 3.2 TÓM TẮT KẾT QUẢ BIỂU QUYẾT (đã/đang biểu quyết)

```
GET /api/meeting-vote-responses/stats?meeting_vote_topic_id={topic_id}
```

Response:
```json
{
  "success": true,
  "data": {
    "total": 50,
    "agree": 45,
    "disagree": 2,
    "approve": 0,
    "reject": 0,
    "abstain": 3
  }
}
```

> Tuỳ `vote_type` của topic:
> - `agree_disagree_abstain` → hiện 3 con: agree / disagree / abstain
> - `approve_reject_abstain` → hiện 3 con: approve / reject / abstain

### 3.3 Đại biểu vote

Khi click topic có status `opened`:

```http
POST /api/meeting-vote-responses
Content-Type: application/json

{
  "meeting_vote_topic_id": 1,
  "option": "agree"
}
```

> **Auto-derive participant**: BE tự lookup participant của user trong meeting của topic. FE **không gửi** `meeting_participant_id` (tránh đại biểu A vote hộ B). User không phải đại biểu của meeting → 404.
>
> `option` ∈ `{ agree | disagree | approve | reject | abstain }` — phải khớp với `topic.vote_type`.
>
> **Idempotent**: vote lần 2 → update phiếu cũ (cùng row qua unique constraint).
>
> **Sửa phiếu**: dùng `PATCH /api/meeting-vote-responses/{id}` — owner-only (404 nếu user khác). Topic.status='closed' → 422 "không thể sửa phiếu".

**BE chặn**:
- Topic chưa mở hoặc đã đóng → 422 "Chương trình biểu quyết chưa mở hoặc đã đóng — không thể bỏ phiếu."
- User không phải đại biểu meeting → 404 "Bạn không phải đại biểu của cuộc họp này."

### 3.4 Liệt kê phiếu (cho admin/operator)

```
GET /api/meeting-vote-responses?meeting_vote_topic_id={topic_id}
```

> Tuỳ `topic.ballot_mode`:
> - `anonymous` → FE phải ẩn `participant_name` trong UI
> - `public_named` → chỉ admin/chair/operator xem detail per-person; vai trò khác chỉ xem aggregate (Section 3.2)

---

## Tab 4 — Kết luận (đã merge vào Tab 2 Tài liệu)

**Removed**: Module `meeting_conclusions` đã bỏ. Workflow mới:

1. **Sau cuộc họp**, thư ký vào dashboard CRUD meeting (admin compose page)
2. Mở section tài liệu → upload thêm record `meeting_documents`
3. Chọn loại tài liệu = **`Tài liệu kết luận cuộc họp`** (trong catalog `meeting_document_types`)
4. Citizen / đại biểu xem tài liệu kết luận tại **Tab 2 Tài liệu** runtime — auto hiện vì cùng table

→ **Tab 4 trên runtime UI có thể bỏ hoặc thay bằng filter view của Tab 2** (filter `meeting_document_type_id` = ID của catalog "Tài liệu kết luận cuộc họp"):

```
GET /api/meeting-documents?meeting_id={id}&meeting_document_type_id={conclusionTypeId}
```

FE phân loại bằng badge `meeting_document_type.name` thay vì cần field riêng.

---

## Tab 5 — Thảo luận & Chất vấn

**URL**: `/meetings/{id}#discussions`

### 5.1 Danh sách

```
GET /api/meeting-discussion-registrations?meeting_id={id}
```

Query params: `meeting_agenda_id`, `meeting_participant_id`, `type` (`discussion|question`), `status` (`registered|called|completed|cancelled`), `search`.

Response item:
```json
{
  "id": 1,
  "meeting_id": 1,
  "meeting_agenda_id": 2,
  "meeting_participant_id": 12,
  "participant_name": "Nguyễn Văn A",
  "participant_position": "Chủ tịch UBND Phường",
  "type": "discussion",         // discussion | question
  "content": "Đăng ký thảo luận về tiến độ giải ngân vốn đầu tư công.",
  "media_id": null,
  "file_url": null,
  "status": "registered",       // registered | called | completed | cancelled
  "called_at": null,
  "completed_at": null,
  "sort_order": 1,
  "created_at": "08:45:00 23/02/2026",
  "updated_at": "08:45:00 23/02/2026"
}
```

> FE chia 2 cột screenshot: `THẢO LUẬN` (filter `type=discussion`) + `CHẤT VẤN` (filter `type=question`). Có thể gọi 2 request riêng hoặc 1 request rồi groupBy client-side.

### 5.2 Đại biểu đăng ký thảo luận / chất vấn

```http
POST /api/meeting-discussion-registrations
Content-Type: application/json

{
  "meeting_id": 1,
  "meeting_agenda_id": 2,        # optional — gán vào chương trình
  "type": "discussion",          # discussion | question
  "content": "Nội dung thảo luận / câu hỏi chất vấn"
}
```

> **Auto-derive participant**: BE tự lookup `meeting_participant_id` từ `auth()->id()` so với `meeting.participants[].attendee.user_id`. FE **không gửi** `meeting_participant_id` (để tránh đại biểu A đăng ký hộ B). Nếu user không phải đại biểu của meeting → 404.
>
> **Update/Destroy**: chỉ owner đại biểu sửa/xóa được đăng ký của chính mình. User khác cố sửa → 404.
>
> **Bulk delete**: không có endpoint này theo design.

> Nếu agenda đó `allow_discussion_registration=false` → BE chặn validation. Tương tự `allow_question_registration` cho `type=question`.

### 5.3 Action điều khiển (operator)

🚧 **Coming Sprint 1**:
- `PATCH /api/meeting-discussion-registrations/{id}/start` → operator click ▶ (status=`called`)
- `PATCH /api/meeting-discussion-registrations/{id}/complete` → operator click ✓ (status=`completed`)

→ Tạm thời FE bỏ button ▶/✓ ở Tab 5/7, hoặc dùng `PATCH /api/meeting-discussion-registrations/{id}` body `{ "status": "called" }` (endpoint update generic) — nhưng không có timestamp tự set. Khuyến nghị **chờ Sprint 1**.

---

## Tab 6 — Chủ trì (Chair view)

**URL**: `/meetings/{id}#chair`

### 6.1 TỔNG QUAN HIỆN TẠI

```
GET /api/meeting-attendances/stats?meeting_id={id}
```

Response (hiện tại):
```json
{ "success": true, "data": { "total": 50, "active": 42, "inactive": 8 } }
```

> ⚠️ Stats hiện chỉ trả `total/active/inactive`. Sprint 1 sẽ mở rộng: `present / absent / late / pending / guest` để khớp screenshot ("Có mặt 42/50 84%"). FE hiện tại tạm hiển thị `active/total`.

### 6.2 Lượt thảo luận / chất vấn

```
GET /api/meeting-discussion-registrations/stats?meeting_id={id}
```

Response:
```json
{ "success": true, "data": { "total": 6, "registered": 3, "called": 1, "completed": 2 } }
```

> Card "Lượt thảo luận = 2", "Lượt chất vấn = 2" trong screenshot — hiện stats không phân theo `type`. **🚧 Sprint 1 sẽ phân theo type** (`{ discussion: { ... }, question: { ... } }`). FE tạm hiển thị tổng.

### 6.3 DANH SÁCH ĐĂNG KÝ THẢO LUẬN / ĐÃ THẢO LUẬN

Sử dụng filter `status` trên Tab 5 endpoint:

```
# Đang đăng ký + đang phát biểu
GET /api/meeting-discussion-registrations?meeting_id={id}&status=registered
GET /api/meeting-discussion-registrations?meeting_id={id}&status=called

# Đã hoàn thành
GET /api/meeting-discussion-registrations?meeting_id={id}&status=completed
```

---

## Tab 7 — Điều hành (Operator)

🚧 **Phần lớn endpoint điều khiển còn đang phát triển — Sprint 1**.

**URL**: `/meetings/{id}#operator`

| UI Section | Endpoint | Status |
|---|---|---|
| THAO TÁC NHANH > Tạm Dừng | — | 🚧 Defer (xem design doc) |
| THAO TÁC NHANH > Uỷ Quyền Điều Hành | `PATCH /api/meetings/{id}/delegate-operator` | 🚧 Sprint 1 |
| THAO TÁC NHANH > Kết Thúc Cuộc Họp | — | 🚧 Defer |
| THAO TÁC NHANH > Khoá Danh Sách Điểm Danh | `PATCH /api/meetings/{id}/lock-attendance` + `unlock-attendance` | 🚧 Sprint 1 (kèm migration) |
| THAO TÁC NHANH > Quản Trị Điều Hành | — | 🚧 Defer |
| THAO TÁC NHANH > Xuất Báo Cáo Nhanh | — | 🚧 Defer |
| DUYỆT ĐIỂM DANH > approve | `PATCH /api/meeting-attendances/{id}/approve` | 🚧 Sprint 1 |
| DUYỆT ĐIỂM DANH > reject | `PATCH /api/meeting-attendances/{id}/reject` | 🚧 Sprint 1 |
| QUẢN LÝ ĐIỂM DANH (stats) | `GET /api/meeting-attendances/stats?meeting_id=X` | ✅ (extended Sprint 1) |
| QUẢN LÝ THẢO LUẬN > start | `PATCH /api/meeting-discussion-registrations/{id}/start` | 🚧 Sprint 1 |
| QUẢN LÝ THẢO LUẬN > complete | `PATCH /api/meeting-discussion-registrations/{id}/complete` | 🚧 Sprint 1 |
| QUẢN LÝ CHẤT VẤN | (giống thảo luận, type=question) | 🚧 Sprint 1 |
| QUẢN LÝ BIỂU QUYẾT > open | `PATCH /api/meeting-vote-topics/{id}/open` | ✅ |
| QUẢN LÝ BIỂU QUYẾT > close | `PATCH /api/meeting-vote-topics/{id}/close` | ✅ |
| QUẢN LÝ CHƯƠNG TRÌNH HỌP | (chỉ display, agenda chạy theo time) | ✅ — FE compute |

**Đề xuất FE trong khi chờ Sprint 1**: build UI Tab 7 với mock data + button disabled, hoặc skip Tab 7 hoàn toàn cho phase hiện tại. Khi Sprint 1 ship → swap mock thành endpoint thật.

### 7.x Endpoint sẵn sàng dùng được ngay

- ✅ `PATCH /api/meeting-vote-topics/{id}/open` — Mở phiên biểu quyết
- ✅ `PATCH /api/meeting-vote-topics/{id}/close` — Đóng phiên
- ✅ `GET /api/meeting-discussion-registrations/stats?meeting_id={id}`
- ✅ `GET /api/meeting-attendances/stats?meeting_id={id}` (basic stats, mở rộng Sprint 1)
- ✅ `PATCH /api/meeting-discussion-registrations/{id}` (update generic) — set field tay nếu cần fallback

---

## Tab 8 — Màn chiếu (Projector)

**URL**: `/meetings/{id}#projector` hoặc `/meetings/{id}/projector` (full-screen)

### 8.1 Compose state từ existing endpoint

Không có composite endpoint — FE compose từ:

1. `GET /api/meetings/{id}` (lấy title + agenda hiện tại derived từ time)
2. `GET /api/meeting-vote-topics?meeting_id={id}&status=opened` (topic đang mở)
3. `GET /api/meeting-vote-responses/stats?meeting_vote_topic_id={topic_id}` (kết quả live nếu `show_result_on_projector=true`)
4. `GET /api/meeting-discussion-registrations?meeting_id={id}&status=called` (người đang phát biểu)

→ Polling **3s** ở Tab 8 để update slide.

### 8.2 Logic hiển thị slide hiện tại

```js
// Pseudocode
function getCurrentProjectorSlide(meeting, agendas, voteTopics, discussions) {
  const now = new Date()
  
  // Priority 1: Có biểu quyết đang mở → show vote slide
  const openVote = voteTopics.find(t => t.status === 'opened')
  if (openVote) return { type: 'vote', topic: openVote }
  
  // Priority 2: Có người đang phát biểu → show speaker slide
  const speaker = discussions.find(d => d.status === 'called')
  if (speaker) return { type: 'speaker', registration: speaker }
  
  // Priority 3: Agenda đang chạy theo time
  const currentAgenda = agendas.find(a => parseTime(a.start_time) <= now && now <= parseTime(a.end_time))
  if (currentAgenda) return { type: 'agenda', agenda: currentAgenda }
  
  // Default: meeting title slide
  return { type: 'idle', meeting }
}
```

### 8.3 🚧 Composite endpoint (optional Sprint 2)

Nếu polling 4 endpoint quá tốn → đề xuất Sprint 2 thêm `GET /api/meetings/{id}/projector-state` trả 1 lần đủ data cho Tab 8.

---

## Phụ lục — Polling strategy đề xuất

| Tab | Endpoint poll | Interval |
|---|---|---|
| 1. Chương trình | `GET /api/meetings/{id}` (chỉ khi cần phát hiện agenda chuyển) | 5s |
| 2. Tài liệu (kèm tài liệu kết luận sau họp) | static, không poll | — |
| 3. Biểu quyết | `GET /api/meeting-vote-topics?meeting_id=X` + stats nếu có topic opened | 3s khi opened, else 10s |
| ~~4. Kết luận~~ | merged vào Tab 2 (filter `meeting_document_type_id`) | — |
| 5. Thảo luận | `GET /api/meeting-discussion-registrations?meeting_id=X` | 5s |
| 6. Chủ trì | `GET /api/meeting-discussion-registrations/stats` + `attendances/stats` | 5s |
| 7. Điều hành | tất cả endpoint Tab 5/6 + Sprint 1 endpoints | 3s |
| 8. Màn chiếu | 4 endpoint ở 8.1 | 3s |

> **Tip**: dùng `setInterval` chỉ khi tab visible (`document.visibilityState === 'visible'`) để tiết kiệm request.

---

## Phụ lục — Validation lỗi

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "meeting_id": ["ID cuộc họp là trường bắt buộc."],
    "option": ["Lựa chọn không hợp lệ với loại biểu quyết."]
  }
}
```

Cross-org: 404 NOT_FOUND.
Permission: 403 FORBIDDEN.
Unauthenticated: 401.

---

## Tóm gọn cho FE

1. **Bắt đầu được ngay** với Tab 1-6 + Tab 8 (compose) — đủ API. Map vào component theo doc này.
2. **Tab 7 (Điều hành) bỏ qua hoặc mock UI** — chờ Sprint 1.
3. **Polling 3-5s** cho các tab có realtime concern. Dùng `document.visibilityState` để tiết kiệm.
4. **Identify role**: tạm dùng Spatie permission CASL cho admin (đã làm meetings.update → cho thấy Tab 7). Khi BE thêm `meeting.current_user_role` (Sprint 1) → switch sang field này, chính xác hơn.
5. **File `file_url`**: relative path `/storage/{id}/{file_name}` — FE prepend host (`api.host + file_url`) hoặc dùng axios baseURL.
