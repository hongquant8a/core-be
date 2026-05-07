# API cho FE — Phòng họp không giấy

Doc reference cho FE implement **trang danh sách meeting + 8 tab trong chi tiết meeting**. Endpoint trong doc này **đã sẵn sàng trên BE**, FE có thể bắt đầu ngay.

> Sprint 1 đã ship (2026-05-07): Tab 1 self-actions (respond/checkin/mark-absent), Tab 7 THAO TÁC NHANH (lock/unlock-attendance + end-early) + DUYỆT ĐIỂM DANH (approve/reject) + highlight pointers. FE phase derive theo `start_time`/`end_time` vs `now()` (không có runtime state field riêng).
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

**BE đã enforce ballot_mode + role (2026-05-07)** — FE không cần ẩn thủ công:

| Mode | Caller | Hành vi BE |
|---|---|---|
| `anonymous` | bất kỳ ai | **403** — không có list detail. Dùng `/stats` để xem tổng hợp |
| `public_named` | chair / operator của meeting hoặc Spatie role `Super Admin`/`Admin`/`Thư ký họp` | full data với `meeting_participant_id` + `participant_name` |
| `public_named` | đại biểu thường / vai trò khác | **403** — chỉ aggregate được phép qua `/stats` |

**Stats endpoint (`GET /api/meeting-vote-responses/stats?meeting_vote_topic_id=X`)**:
- Privileged (chair/op/secretary/admin) → luôn xem được.
- Đại biểu thường → 403 nếu `topic.show_result_on_personal_device=false`. Cho phép nếu true (bất kể anonymous hay public_named — aggregate không lộ danh tính).

→ FE hiển thị panel kết quả thì dựa vào response status: 200 = render aggregate, 403 = ẩn panel hoặc show "Phiên này không hiển thị kết quả".

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

Hỗ trợ đính kèm file (spec line 506-507): "Đại biểu có thể đính kèm file khi đăng ký để chủ trì/điều hành mở trình chiếu ngay trong phiên họp. File đi qua `MediaService`."

**Form data (multipart/form-data)** vì có thể có file đính kèm:

```http
POST /api/meeting-discussion-registrations
Content-Type: multipart/form-data

meeting_id=1
meeting_agenda_id=2          # required - đăng ký luôn gắn với 1 chương trình cụ thể
type=discussion              # discussion | question
content=Nội dung thảo luận / câu hỏi chất vấn
attachment=@slide.pdf        # optional, ≤10MB, đi qua MediaService
```

Khi update, dùng cùng field `attachment` (thay file cũ) hoặc `remove_attachment=true` (xóa).

Response trả `media_id` + `file_url` nếu có tệp đính kèm:
```json
{
  "id": 12,
  "type": "discussion",
  "content": "...",
  "media_id": 105,
  "file_url": "/storage/105/slide.pdf",
  "status": "registered",
  ...
}
```

> **Auto-derive participant**: BE tự lookup `meeting_participant_id` từ `auth()->id()` so với `meeting.participants[].attendee.user_id`. FE **không gửi** `meeting_participant_id` (để tránh đại biểu A đăng ký hộ B). Nếu user không phải đại biểu của meeting → 404.
>
> **Agenda required + flag check** (spec line 99-100, 276):
> - `meeting_agenda_id` **bắt buộc** — đăng ký luôn gắn với 1 chương trình cụ thể.
> - `type=discussion` → agenda phải có `allow_discussion_registration=true`
> - `type=question` → agenda phải có `allow_question_registration=true`
> - Vi phạm → 422 "Chương trình họp này không cho phép đăng ký thảo luận/chất vấn."
> - Agenda không thuộc meeting → 404.
>
> **Update**: dùng `PATCH /api/meeting-discussion-registrations/{id}` (multipart cũng được nếu thay file). Owner-only; user khác → 404.
> **Destroy**: owner-only.
> **Bulk delete**: không có endpoint này theo design.

→ FE chỉ hiện nút "Đăng ký thảo luận" trên agenda có `allow_discussion_registration=true`; tương tự với chất vấn (BE đã enforce, FE bonus UX để user không bị 422 lúc submit).

#### ⚠️ Field "Gửi tới ai" — KHÔNG có ở BE

Screenshot Tab 5 hiển thị `"Gửi tới: Phạm Văn Long"` cho chất vấn — nghiệp vụ "chất vấn nhắm tới người cụ thể". **Schema BE không có field này** (`target_user_id`, `directed_to`, ...).


> Nếu agenda đó `allow_discussion_registration=false` → BE chặn validation. Tương tự `allow_question_registration` cho `type=question`.

### 5.3 Action điều khiển (operator)

**Action điều hành** (operator):

```
PATCH /api/meeting-discussion-registrations/{id}/complete
```

State machine **nhị phân**: chỉ 2 status `registered` (chưa phát biểu) + `completed` (đã phát biểu).

| Action | Endpoint | Status transition | Spec |
|---|---|---|---|
| Operator đánh dấu xong | `PATCH /{id}/complete` | `registered → completed` + `completed_at = now()` | Section 7.3: "Người điều hành đánh dấu 'Đã thảo luận' / 'Đã chất vấn'" |

**Validation**: `complete` chỉ chạy khi đăng ký đang `registered`. Đã `completed` → 422.

**Đại biểu rút đăng ký TRƯỚC khi được đánh dấu**: dùng `DELETE /{id}` (xoá row hẳn).

**Permission**: hiện dùng Spatie `meeting-discussion-registrations.complete`. Sprint 1 sẽ wrap thêm middleware `meeting.role:operator` để scope per-meeting.

---

## Tab 6 — Chủ trì (Chair view)

**URL**: `/meetings/{id}#chair`

Read-only dashboard tổng hợp theo spec section 4.3. FE compose từ 2 endpoint stats hiện có — không cần BE work mới.

### 6.1 TỔNG QUAN HIỆN TẠI — Tỷ lệ có mặt

```
GET /api/meeting-attendances/stats?meeting_id={id}
```

Response:
```json
{
  "success": true,
  "data": {
    "total_invited": 50,   // tổng đại biểu được mời (count meeting_participants)
    "total": 8,            // tổng attendance records (số người đã có thao tác checkin/báo vắng)
    "present": 6,          // status='present'
    "absent": 1,           // status='absent'
    "pending": 1           // status='pending' (đã checkin chờ duyệt)
  }
}
```

→ FE compute "Tỷ lệ có mặt": `present / total_invited * 100` (vd `6 / 50 = 12%`).

> **Phân biệt `total` vs `total_invited`**:
> - `total_invited` = đại biểu được mời (luôn lớn nhất, = `meeting.participants` count)
> - `total` = đã có attendance row (≤ total_invited; gồm pending + present + absent)
> - Đại biểu chưa thao tác checkin/báo vắng → không có attendance row, không tính vào `total` nhưng vẫn trong `total_invited`

### 6.2 Lượt thảo luận / chất vấn (phân theo type)

```
GET /api/meeting-discussion-registrations/stats?meeting_id={id}
```

Response (đã breakdown theo type):
```json
{
  "success": true,
  "data": {
    "total": 6,
    "registered": 3,
    "completed": 3,
    "discussion": { "total": 3, "registered": 1, "completed": 2 },
    "question": { "total": 3, "registered": 2, "completed": 1 }
  }
}
```

→ FE map vào card (theo spec 4.3 — "Danh sách **đăng ký**"):
- `Lượt thảo luận = data.discussion.total` (tổng đăng ký type=discussion, gồm cả chưa và đã phát biểu)
- `Lượt chất vấn = data.question.total`
- `Tổng đăng ký = data.total`
- Button "Danh sách đăng ký thảo luận (1)" → badge = `data.discussion.registered` (số đang chờ)
- Button "Danh sách đăng ký chất vấn (1)" → badge = `data.question.registered`

> Nếu FE muốn hiển thị "đã/tổng" trong card (vd `0/1`), dùng combo `discussion.completed / discussion.total`.

### 6.3 DANH SÁCH ĐĂNG KÝ THẢO LUẬN / ĐÃ THẢO LUẬN

Filter trên endpoint Tab 5:

```
# Đang chờ phát biểu
GET /api/meeting-discussion-registrations?meeting_id={id}&type=discussion&status=registered

# Đã thảo luận xong
GET /api/meeting-discussion-registrations?meeting_id={id}&type=discussion&status=completed

# Tương tự cho chất vấn — đổi type=question
```

→ FE render 2 list section:
- "DANH SÁCH ĐĂNG KÝ THẢO LUẬN" — filter `status=registered` (chưa thảo luận / đang chờ)
- "DANH SÁCH ĐÃ THẢO LUẬN" — filter `status=completed`

### 6.4 Tỷ lệ biểu quyết từng chương trình (theo spec 4.3)

FE loop qua `meeting.vote_topics[]` (preload từ show meeting), với mỗi topic call:
```
GET /api/meeting-vote-responses/stats?meeting_vote_topic_id={topic_id}
```

→ Hiển thị bar chart aggregate. Polling 5s khi tab visible.

---

## Tab 7 — Điều hành (Operator)

✅ **THAO TÁC NHANH + DUYỆT ĐIỂM DANH đã có endpoint thực tế** (Sprint 1 ship trong commits ngày 2026-05-07).

**URL**: `/meetings/{id}#operator`

| UI Section | Endpoint | Status |
|---|---|---|
| THAO TÁC NHANH > Kết Thúc Cuộc Họp | `PATCH /api/meetings/{id}/end-early` (set `end_time = now()`, FE phase tự chuyển sang `finished`) | ✅ |
| THAO TÁC NHANH > Khoá Danh Sách Điểm Danh | `PATCH /api/meetings/{id}/lock-attendance` + `unlock-attendance` | ✅ |
| THAO TÁC NHANH > Bắt Đầu / Tạm Dừng | — | ❌ Bỏ (FE phase derive từ `start_time`/`end_time` vs `now()`, không cần điều khiển runtime) |
| THAO TÁC NHANH > Uỷ Quyền Điều Hành | — | ❌ Bỏ scope (giữ FK `operator_meeting_attendee_id` cố định trên meeting) |
| THAO TÁC NHANH > Quản Trị Điều Hành | (link điều hướng, không phải API) | ✅ — FE link |
| THAO TÁC NHANH > Xuất Báo Cáo Nhanh | — | ❌ Bỏ scope (chưa có spec rõ) |
| DUYỆT ĐIỂM DANH > approve | `PATCH /api/meeting-attendances/{id}/approve` | ✅ |
| DUYỆT ĐIỂM DANH > reject | `PATCH /api/meeting-attendances/{id}/reject` | ✅ |
| QUẢN LÝ ĐIỂM DANH (stats) | `GET /api/meeting-attendances/stats?meeting_id=X` | ✅ |
| QUẢN LÝ THẢO LUẬN > complete | `PATCH /api/meeting-discussion-registrations/{id}/complete` (registered → completed) | ✅ |
| QUẢN LÝ CHẤT VẤN | (giống thảo luận, type=question) | ✅ |
| QUẢN LÝ BIỂU QUYẾT > open | `PATCH /api/meeting-vote-topics/{id}/open` (body optional `description` + `duration_minutes`) | ✅ |
| QUẢN LÝ BIỂU QUYẾT > close | `PATCH /api/meeting-vote-topics/{id}/close` | ✅ |
| HIGHLIGHT chương trình lên màn chiếu | `PATCH /api/meetings/{id}/highlight-agenda` | ✅ |
| HIGHLIGHT phát biểu/chất vấn lên màn chiếu | `PATCH /api/meetings/{id}/highlight-discussion` | ✅ |
| QUẢN LÝ CHƯƠNG TRÌNH HỌP | (chỉ display + click highlight) | ✅ |

### 7.1 Phase derive ở FE (đã đơn giản hoá)

BE **không** giữ runtime state field riêng. FE phase chạy theo `start_time` + `end_time` vs `now()`:

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

- Operator bấm **"Kết thúc cuộc họp"** trước `end_time` dự kiến → BE set `end_time = now()` → FE phase ngay sau đó derive `finished`.
- Sau `lock-attendance` BE trả 422 khi đại biểu gọi `checkin`/`mark-absent` — FE bắt error message để hiển thị.
- Countdown biểu quyết đếm ở FE (theo `duration_minutes` của vote topic).

### 7.2 Highlight chương trình + phát biểu lên màn chiếu

| Action | Endpoint | Body |
|---|---|---|
| Click chương trình → highlight lên màn chiếu | `PATCH /api/meetings/{id}/highlight-agenda` | `{ "agenda_id": int|null }` |
| Click đăng ký phát biểu → highlight lên màn chiếu | `PATCH /api/meetings/{id}/highlight-discussion` | `{ "discussion_registration_id": int|null }` |

- Hai pointer này **độc lập** — operator có thể highlight đồng thời 1 chương trình + 1 phát biểu trong chương trình đó.
- Truyền key với value `null` để bỏ highlight (ví dụ kết thúc chương trình → set `agenda_id=null`).
- BE preload `current_agenda` + `current_discussion_registration` trong response của `GET /meetings/{id}` (Tab 8 polling), FE dùng trực tiếp 2 object này.

### 7.3 Mở biểu quyết + popup realtime

| Action | Endpoint | Body |
|---|---|---|
| Operator bấm "Bắt đầu" trên 1 vote topic | `PATCH /api/meeting-vote-topics/{id}/open` | `{ "description": string|null, "duration_minutes": int|null }` |
| Operator bấm "Đóng" | `PATCH /api/meeting-vote-topics/{id}/close` | — |

**Popup biểu quyết bên đại biểu**: FE app đại biểu polling `GET /api/meeting-vote-topics?meeting_id={id}&status=opened` mỗi 3-5s. Khi detect topic mới có `status=opened` → tự bật popup, dùng `description` + `duration_minutes` cho UI countdown. BE **không** auto-close khi hết giờ — operator vẫn phải bấm `/close`.

---

## Tab 8 — Màn chiếu (Projector)

**URL**: `/meetings/{id}#projector` hoặc `/meetings/{id}/projector` (full-screen)

### 8.1 Compose state từ existing endpoint

Không có composite endpoint — FE compose từ:

1. `GET /api/meetings/{id}` (preload `current_agenda` + `current_discussion_registration` — operator highlight) + meeting title.
2. `GET /api/meeting-vote-topics?meeting_id={id}&status=opened` (topic đang mở).
3. `GET /api/meeting-vote-responses/stats?meeting_vote_topic_id={topic_id}` (kết quả live nếu `show_result_on_projector=true`).

→ Polling **3s** ở Tab 8 để update slide.

### 8.2 Logic hiển thị slide hiện tại

```js
// Pseudocode — ưu tiên highlight do operator chọn (tab 7), không phụ thuộc time-derive nữa.
function getCurrentProjectorSlide(meeting, voteTopics, voteStats) {
  // Priority 1: Có biểu quyết đang mở → show vote slide (popup full-screen)
  const openVote = voteTopics.find(t => t.status === 'opened')
  if (openVote) return { type: 'vote', topic: openVote, stats: voteStats }

  // Priority 2: Operator đang highlight 1 phát biểu → show speaker slide
  if (meeting.current_discussion_registration) {
    return { type: 'speaker', registration: meeting.current_discussion_registration }
  }

  // Priority 3: Operator đang highlight 1 chương trình → show agenda slide
  if (meeting.current_agenda) {
    return { type: 'agenda', agenda: meeting.current_agenda }
  }

  // Default: meeting title slide
  return { type: 'idle', meeting }
}
```

### 8.3 🚧 Composite endpoint (optional Sprint 2)

Nếu polling 3 endpoint quá tốn → đề xuất Sprint 2 thêm `GET /api/meetings/{id}/projector-state` trả 1 lần đủ data cho Tab 8.

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
