# API Cuộc họp + Chương trình + Tài liệu + Đại biểu tham dự

Tài liệu cho FE implement luồng tạo/quản lý cuộc họp:

| Resource | Base path | Tên hiển thị |
|---|---|---|
| Cuộc họp | `/api/meetings` | Meeting |
| Chương trình họp | `/api/meeting-agendas` | Meeting Agenda |
| Tài liệu họp | `/api/meeting-documents` | Meeting Document |
| Đại biểu tham dự | `/api/meeting-participants` | Meeting Participant |
| Chương trình biểu quyết | `/api/meeting-vote-topics` | Meeting Vote Topic |
| Phiếu biểu quyết | `/api/meeting-vote-responses` | Meeting Vote Response |

**Header bắt buộc (auth):** `Authorization: Bearer {token}` + `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** mọi endpoint có xác thực chỉ thao tác bản ghi của org hiện tại (middleware `ensure.route.org` chặn cross-org).

**Permission:** mỗi action check `permission:{resource}.{action}`.

**Response envelope:**

```json
// Index
{ "success": true, "data": { "items": [...], "pagination": { "current_page": 1, "last_page": 3, "per_page": 10, "total": 27 } } }

// Show / Store / Update / ChangeStatus
{ "success": true, "message": "...", "data": { ... } }

// Stats / Destroy / Bulk
{ "success": true, "message": "...", "data": null | {...} }
```

Datetime: `H:i:s d/m/Y` (vd `08:30:00 01/05/2026`). Time-only (giờ chương trình họp): `H:i:s`.

---

## 1. Tạo cuộc họp — `/api/meetings`

### 1.1 Public (không cần auth)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meetings/public` | Danh sách cuộc họp công khai (`is_public=true` + `status=published`). Query: `search`, `meeting_type_id`, `from_date`, `to_date`, `sort_by`, `sort_order`, `limit`. |
| GET | `/api/meetings/public/{id}` | Chi tiết cuộc họp công khai. Tự tăng `view_count` + ghi log vào `meeting_views`. Chặn 404 nếu không public/published. |

### 1.2 Authenticated CRUD

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meetings/stats` | `{ total, published, draft }`. Query giống `index`. |
| GET | `/api/meetings` | Danh sách phân trang. Query: `search`, `meeting_type_id`, `status`, `from_date`, `to_date`, `sort_by` (`id\|title\|start_time\|created_at\|updated_at`), `sort_order`, `limit` (1-100). |
| GET | `/api/meetings/{id}` | Chi tiết. |
| POST | `/api/meetings` | Tạo. Body: [Meeting body](#meeting-body). |
| PUT \| PATCH | `/api/meetings/{id}` | Cập nhật. |
| DELETE | `/api/meetings/{id}` | Xóa. |
| POST | `/api/meetings/bulk-delete` | Body `{ "ids": [1,2,3] }`. |
| PATCH | `/api/meetings/bulk-status` | Body `{ "ids": [...], "status": "draft\|published\|cancelled" }`. |
| PATCH | `/api/meetings/{id}/status` | **Quan trọng**: khi `draft → published`, BE tự động (1) tạo `meeting_invitations` cho tất cả participants, (2) dispatch event `MeetingPublished` (gửi FCM/email), (3) set `published_at = now()` lần đầu publish (republish không ghi đè). |
| GET | `/api/meetings/export` | Tải Excel `meetings.xlsx`. Query giống `index`. Cột: `STT, Tiêu đề, Loại, Địa điểm, Công khai, Bắt đầu, Kết thúc, Trạng thái, Lượt xem, Phát hành, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID`. |

> Meetings **không hỗ trợ import** — bao gồm relationships phức tạp (agendas/documents/participants), tạo qua UI thay vì bulk-import.

### <a id="meeting-body"></a>1.3 Meeting body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `title` | string (≤255) | ✅ | Tiêu đề cuộc họp. |
| `meeting_type_id` | integer | — | FK `meeting_types.id`. |
| `meeting_location_id` | integer | — | FK `meeting_locations.id`. |
| `chairperson_meeting_attendee_id` | integer | — | FK `meeting_attendees.id` — chủ trì. **BE auto-tạo participant nếu chưa có.** |
| `operator_meeting_attendee_id` | integer | — | FK `meeting_attendees.id` — thư ký/điều hành. **BE auto-tạo participant nếu chưa có.** |
| `is_public` | boolean | ✅ | Có cho phép xem ở trang công khai không. |
| `start_time` | datetime `Y-m-d H:i:s` | ✅ | Thời gian bắt đầu. |
| `end_time` | datetime `Y-m-d H:i:s` | — | Phải `>= start_time`. |
| `content` | text | — | Nội dung cuộc họp. |
| `status` | enum | ✅ | `draft` \| `published` \| `cancelled`. **Không có `in_progress`/`completed`** — FE tự derive từ `start_time`/`end_time` vs `now()`. |
| `published_at` | datetime | — | Tự set khi publish. |

> **Chủ trì + Thư ký** lưu trực tiếp trên `meetings` qua 2 FK (singular cardinality). FE chọn từ `/api/meeting-attendees/user-options` rồi gửi `chairperson_meeting_attendee_id` + `operator_meeting_attendee_id` lúc tạo/update meeting. BE tự động tạo `meeting_participants` row cho 2 attendee đó nếu chưa có (role=`delegate`) — đảm bảo họ có mặt trong danh sách điểm danh + nhận giấy mời khi publish.

### 1.4 Response (MeetingResource)

`show` (`GET /api/meetings/{id}`) **preload nested**: `participants` (kèm role), `agendas`, `documents`. `index` không load để giảm payload.

```json
{
  "id": 1,
  "organization_id": 1,
  "meeting_type_id": 1,
  "meeting_type_name": "HĐND thường kỳ",
  "meeting_location_id": 1,
  "meeting_location_name": "Hội trường lớn UBND TP Đà Nẵng",
  "chairperson_meeting_attendee_id": 12,
  "chairperson": { "id": 12, "name": "Nguyễn Văn Hùng", "email": "nvhung@...", "position_name": "Chủ tịch HĐND", "department_name": "HĐND TP" },
  "operator_meeting_attendee_id": 13,
  "operator": { "id": 13, "name": "Trần Thị Mai", "email": "ttmai@...", "position_name": "Phó CT HĐND", "department_name": "HĐND TP" },
  "title": "Kỳ họp HĐND thường kỳ tháng 5/2026",
  "is_public": true,
  "content": "...",
  "start_time": "08:00:00 15/05/2026",
  "end_time": "16:00:00 15/05/2026",
  "status": "published",
  "view_count": 145,
  "published_at": "08:00:00 08/05/2026",
  "created_by": "Admin",
  "updated_by": "Admin",
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 08/05/2026",
  "participants": [
    { "id": 12, "role": "delegate", "display_name": "Nguyễn Văn Hùng", "email": "...", "phone": "...", "response_status": "accepted", ... },
    { "id": 13, "role": "delegate", "display_name": "Trần Thị Mai", ... },
    { "id": 14, "role": "guest", "display_name": "TS. Bình", ... }
  ],
  "agendas": [
    { "id": 1, "sort_order": 1, "start_time": "08:00:00", "end_time": "08:30:00", "content": "Khai mạc kỳ họp.", ... }
  ],
  "documents": [
    { "id": 1, "title": "Tờ trình ngân sách", "media_id": 42, "file_url": "https://...", "is_public": true, "status": "published", ... }
  ],
  "vote_topics": [
    { "id": 1, "title": "Biểu quyết thông qua nghị quyết", "vote_type": "agree_disagree_abstain", "ballot_mode": "public_named", "status": "draft", "sort_order": 1, ... }
  ]
}
```

> Trả ở `show` only; `index` (list view) **không** trả 4 mảng này — gọi `/api/meeting-{participants,agendas,documents,vote-topics}?meeting_id=X` riêng nếu cần phân trang/filter.

> **Side-effects khi publish (`changeStatus` → `published`)**:
> - Tạo idempotent `meeting_invitations` cho từng participant (status=`pending`).
> - Dispatch event `MeetingPublished` → listener đọc `notification_event_configs` (module `meeting`) để gửi FCM/email/SMS. Nếu admin chưa enable event → không gửi.
> - Set `published_at = now()` **chỉ lần đầu** publish (`published_at` đang null). Republish sau đó giữ nguyên giá trị cũ.
> - Re-publish (đã từng publish trước đó) **không** tạo invitation trùng.

> **Phase derived ở FE** (BE chỉ giữ 3 status: `draft / published / cancelled`):
> ```js
> function getMeetingPhase(meeting) {
>   if (meeting.status === 'cancelled') return 'cancelled'   // "Đã hủy"
>   if (meeting.status === 'draft')     return 'draft'       // "Bản nháp"
>   const now = new Date()
>   const start = parseDateTime(meeting.start_time)
>   const end = meeting.end_time ? parseDateTime(meeting.end_time) : null
>   if (now < start)                  return 'upcoming'      // "Sắp diễn ra"
>   if (end && now > end)             return 'finished'      // "Đã kết thúc"
>   return 'in_progress'                                      // "Đang diễn ra"
> }
> ```

---

## 2. Chương trình cuộc họp — `/api/meeting-agendas`

Mỗi cuộc họp có nhiều chương trình (agenda) — sắp theo `sort_order`. Hỗ trợ **phân cấp tối đa 2-3 tầng** qua `parent_id` (theo spec line 102: "giới hạn 2-3 cấp phân cấp để dễ theo dõi trên màn chiếu").

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-agendas` | Danh sách phân trang. **Query bắt buộc** `meeting_id` (lọc theo cuộc họp). Query khác: `search` (theo content), `parent_id`, `sort_by` (`id\|sort_order\|start_time\|created_at\|updated_at`), `sort_order`, `limit`. |
| GET | `/api/meeting-agendas/{id}` | Chi tiết. |
| POST | `/api/meeting-agendas` | Tạo. Body: [Agenda body](#agenda-body). |
| PUT \| PATCH | `/api/meeting-agendas/{id}` | Cập nhật. |
| DELETE | `/api/meeting-agendas/{id}` | Xóa. |
| POST | `/api/meeting-agendas/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-agendas/reorder` | Body `{ "items": [{ "id": 1, "sort_order": 1 }, ...] }`. Reorder kéo thả. Trong transaction. |

### <a id="agenda-body"></a>2.1 Agenda body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_id` | integer | ✅ | FK `meetings.id`. |
| `content` | text | ✅ | Nội dung chương trình. |
| `start_time` | time `H:i:s` | — | Giờ bắt đầu (chỉ giờ, không có ngày). |
| `end_time` | time `H:i:s` | — | Phải `>= start_time`. |
| `person_in_charge` | string (≤255) | — | Người phụ trách (text tự do, không link user). |
| `allow_discussion_registration` | boolean | — | Cho phép đại biểu đăng ký thảo luận trong chương trình này. |
| `allow_question_registration` | boolean | — | Cho phép đại biểu đăng ký chất vấn. |
| `parent_id` | integer | — | FK `meeting_agendas.id` — chương trình cha (mục con). |
| `sort_order` | integer (≥0) | — | Thứ tự hiển thị. |

```json
{
  "meeting_id": 1,
  "start_time": "08:00:00",
  "end_time": "08:30:00",
  "content": "Khai mạc kỳ họp.",
  "person_in_charge": "Chủ tịch HĐND",
  "allow_discussion_registration": false,
  "allow_question_registration": false,
  "sort_order": 1
}
```

### 2.2 Response (MeetingAgendaResource)

```json
{
  "id": 1,
  "organization_id": 1,
  "meeting_id": 1,
  "start_time": "08:00:00",
  "end_time": "08:30:00",
  "content": "Khai mạc kỳ họp.",
  "person_in_charge": "Chủ tịch HĐND",
  "allow_discussion_registration": false,
  "allow_question_registration": false,
  "parent_id": null,
  "sort_order": 1,
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 01/05/2026"
}
```

### 2.3 Cấu trúc phân cấp (parent/child)

Spec cho phép tới 2-3 cấp. FE quản lý tree client-side từ flat list.

**Tạo chương trình con**:
```json
POST /api/meeting-agendas
{
  "meeting_id": 1,
  "parent_id": 5,            // ID chương trình cha
  "content": "Mục con của chương trình 5",
  "sort_order": 1            // thứ tự trong cùng cấp (anh em với các con khác của parent_id=5)
}
```

**Cách lấy data**:
- **(A) Flat all** (recommend cho FE tự build tree): `GET /api/meeting-agendas?meeting_id=X` → trả tất cả mixed (cả root + child). FE group theo `parent_id` để build tree.
- **(B) Theo cấp**: `GET /api/meeting-agendas?meeting_id=X&parent_id=null` → root level only; sau đó từng node `?parent_id=5` để lấy con.

**Ví dụ flat response**:
```json
{
  "data": {
    "items": [
      { "id": 1, "parent_id": null, "sort_order": 1, "content": "Khai mạc" },
      { "id": 2, "parent_id": null, "sort_order": 2, "content": "Báo cáo KT-XH" },
      { "id": 3, "parent_id": 2, "sort_order": 1, "content": "  Tình hình ngân sách" },
      { "id": 4, "parent_id": 2, "sort_order": 2, "content": "  Đầu tư công" },
      { "id": 5, "parent_id": null, "sort_order": 3, "content": "Bế mạc" }
    ]
  }
}
```

FE build tree:
```js
function buildAgendaTree(items) {
  const map = new Map(items.map(it => [it.id, { ...it, children: [] }]))
  const roots = []
  for (const node of map.values()) {
    if (node.parent_id) {
      map.get(node.parent_id)?.children.push(node)
    } else {
      roots.push(node)
    }
  }
  return roots  // sort theo sort_order ở mỗi level
}
```

**Reorder + đẩy vào mục con**: `PATCH /api/meeting-agendas/reorder` chỉ update `sort_order`. Để **đổi parent** (drag từ level A sang level B), gửi `PATCH /api/meeting-agendas/{id}` body `{ "parent_id": 5, "sort_order": 1 }`.

> **Validate cycle**: BE chưa enforce "không cho 1 node là cha của chính nó hoặc tạo vòng lặp". FE tự ngăn drag-drop tạo cycle (không cho thả parent vào dưới một trong các child của nó).

---

## 3. Tài liệu đính kèm — `/api/meeting-documents`

Tài liệu đính kèm vào cuộc họp (có thể gắn với 1 chương trình cụ thể). Hỗ trợ upload file qua Spatie Media Library.

### 3.1 Public (không cần auth)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-documents/public` | Danh sách tài liệu công khai (cuộc họp + tài liệu đều `is_public=true`, `status=published`). Query: `meeting_id`, `search`, `limit`. |
| GET | `/api/meeting-documents/public/{id}` | Chi tiết tài liệu công khai. Tự tăng `view_count` + log `meeting_views`. Chặn 404 nếu không công khai. |

### 3.2 Authenticated CRUD

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-documents` | Danh sách phân trang. Query: `meeting_id`, `meeting_agenda_id`, `meeting_document_type_id`, `search` (title/document_number), `status`, `is_public`, `sort_by` (`id\|sort_order\|created_at\|updated_at`), `sort_order`, `limit`. |
| GET | `/api/meeting-documents/{id}` | Chi tiết. |
| POST | `/api/meeting-documents` | Tạo (1 tài liệu = 1 file). Body: **multipart/form-data** với `file` (xem [Document body](#document-body)). |
| PUT \| PATCH | `/api/meeting-documents/{id}` | Cập nhật metadata + thay/xóa file. `file` upload mới (replace file cũ), `remove_file=true` xóa file hiện tại. |
| DELETE | `/api/meeting-documents/{id}` | Xóa. |
| POST | `/api/meeting-documents/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-documents/bulk-status` | Body `{ "ids": [...], "status": "draft\|published" }`. |
| PATCH | `/api/meeting-documents/{id}/status` | Body `{ "status": "draft\|published" }`. |
| PATCH | `/api/meeting-documents/reorder` | Body `{ "items": [{ "id": 1, "sort_order": 1 }, ...] }`. |

### <a id="document-body"></a>3.3 Document body (multipart/form-data)

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_id` | integer | ✅ (store) | FK `meetings.id`. |
| `meeting_agenda_id` | integer | — | FK `meeting_agendas.id` — gắn vào chương trình cụ thể. |
| `meeting_document_type_id` | integer | — | FK `meeting_document_types.id`. |
| `title` | string (≤255) | ✅ (store) | Tiêu đề tài liệu. |
| `document_number` | string (≤255) | — | Số văn bản (vd `01/TTr-UBND`). |
| `summary` | text | — | Tóm tắt. |
| `file` | file (≤10 MB) | — | Tệp đính kèm. Lưu vào collection `meeting-document-attachments` (Spatie Media). |
| `remove_file` | boolean | — | **Chỉ trên `update`** — xóa file hiện có. |
| `is_public` | boolean | ✅ (store) | Hiển thị ngoài trang công khai (kèm theo cờ public của meeting). |
| `status` | enum | ✅ (store) | `draft` \| `published`. |
| `sort_order` | integer (≥0) | — | Auto-tăng nếu không truyền (last + 1 trong meeting).

> **Mỗi tài liệu = 1 file**. Cuộc họp có nhiều tài liệu → tạo nhiều record `meeting_documents`. Trên `update`, upload `file` mới sẽ tự xóa file cũ trước khi gắn file mới (BE handle).

### 3.4 Response (MeetingDocumentResource)

```json
{
  "id": 1,
  "meeting_id": 1,
  "meeting_agenda_id": 2,
  "meeting_document_type_id": 1,
  "meeting_document_type_name": "Tờ trình",
  "title": "Tờ trình về phân bổ ngân sách bổ sung 2026",
  "document_number": "01/TTr-UBND",
  "summary": "...",
  "media_id": 42,
  "file_url": "https://example.com/storage/.../to-trinh.pdf",
  "is_public": true,
  "status": "published",
  "view_count": 56,
  "download_count": 12,
  "sort_order": 1,
  "created_by": "Admin",
  "updated_by": "Admin",
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 01/05/2026"
}
```

> Để xóa file: `PATCH /api/meeting-documents/{id}` với body `{ "remove_file": true }`. Để thay thế: gửi `file` mới (BE xóa file cũ + gắn file mới trong cùng transaction).

---

## 4. Danh sách đại biểu tham dự — `/api/meeting-participants`

`MeetingParticipant` = mapping giữa `meeting` và `meeting_attendee` (= user). Lưu **snapshot** thông tin đại biểu lúc mời (per spec — báo cáo/giấy mời không thay đổi khi user update profile sau).

> **Chủ trì + Thư ký KHÔNG ở đây** — đã chuyển lên FK trên `meetings` (`chairperson_meeting_attendee_id`, `operator_meeting_attendee_id`) vì cardinality 1-1. Xem [Section 1.3](#meeting-body).
>
> Role enum của participant chỉ còn 2 giá trị:
> - `delegate` (mặc định) — đại biểu thường
> - `guest` — khách mời (không có quyền biểu quyết)

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-participants/stats` | `{ total, accepted, declined }`. Query: `meeting_id`, `response_status`, `role`, `search`. |
| GET | `/api/meeting-participants` | Danh sách phân trang. Query: `meeting_id`, `response_status`, `role`, `search` (display_name), `sort_by` (`id\|display_name\|responded_at\|created_at`), `sort_order`, `limit`. |
| GET | `/api/meeting-participants/{id}` | Chi tiết. |
| POST | `/api/meeting-participants` | Tạo (mời thêm 1 đại biểu vào cuộc họp). Body: [Participant body](#participant-body). |
| PUT \| PATCH | `/api/meeting-participants/{id}` | Cập nhật role/response_status/absence_reason (snapshot fields KHÔNG đổi). |
| DELETE | `/api/meeting-participants/{id}` | Xóa khỏi danh sách. |
| POST | `/api/meeting-participants/bulk-delete` | Body `{ "ids": [...] }`. |

### <a id="participant-body"></a>4.1 Participant body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_id` | integer | ✅ | FK `meetings.id`. |
| `meeting_attendee_id` | integer | ✅ | FK `meeting_attendees.id`. Snapshot `display_name/email/phone` được copy từ `attendee.user`. |
| `role` | enum | — | `delegate` (mặc định) \| `guest`. (chair/operator → set qua FK trên meeting). |
| `response_status` | enum | — | `pending` (mặc định) \| `accepted` \| `declined`. |
| `absence_reason` | text | — | Lý do vắng (khi `declined`). |

```json
{
  "meeting_id": 1,
  "meeting_attendee_id": 12,
  "role": "delegate",
  "response_status": "pending"
}
```

> **Snapshot logic**: khi `store`, BE tự copy `display_name = attendee.user.name`, `email = attendee.user.email`, `phone = attendee.user.profile.phone`, `position_name = attendee.position_name`, `department_name = attendee.department_name`. Sau đó user đổi tên/email cũng không ảnh hưởng record participant.
> **Unique constraint**: `(meeting_id, meeting_attendee_id)` — 1 đại biểu chỉ tham gia 1 lần / cuộc họp.

### 4.2 Response (MeetingParticipantResource)

```json
{
  "id": 1,
  "meeting_id": 1,
  "meeting_attendee_id": 12,
  "attendee_name": "Nguyễn Văn A",
  "role": "delegate",
  "display_name": "Nguyễn Văn A",
  "position_name": "Chủ tịch HĐND",
  "department_name": "HĐND TP",
  "email": "nva@snvdn.gov.vn",
  "phone": "0901234567",
  "response_status": "accepted",
  "absence_reason": null,
  "responded_at": "10:30:00 12/05/2026",
  "created_at": "08:00:00 08/05/2026",
  "updated_at": "10:30:00 12/05/2026"
}
```

---

## 5. Chương trình biểu quyết — `/api/meeting-vote-topics`

`MeetingVoteTopic` = chủ đề biểu quyết trong meeting (vd: "Thông qua nghị quyết phân bổ ngân sách"). Mỗi topic có vote_type (loại lựa chọn) + ballot_mode (ẩn danh / công khai). Đại biểu vote vào `meeting_vote_responses`.

### 5.1 CRUD

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-vote-topics/stats` | `{ total, draft, opened, closed }`. Query: `meeting_id`. |
| GET | `/api/meeting-vote-topics` | Danh sách phân trang. Query: `meeting_id`, `status`, `search`, `sort_by` (`id\|sort_order\|created_at\|updated_at`), `sort_order`, `limit`. |
| GET | `/api/meeting-vote-topics/{id}` | Chi tiết. |
| POST | `/api/meeting-vote-topics` | Tạo (thường ở giai đoạn soạn meeting). Body: [Vote topic body](#vote-topic-body). |
| PUT \| PATCH | `/api/meeting-vote-topics/{id}` | Cập nhật (chỉ khi `status=draft`). |
| DELETE | `/api/meeting-vote-topics/{id}` | Xóa. |
| POST | `/api/meeting-vote-topics/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-vote-topics/reorder` | Body `{ "items": [{ "id": 1, "sort_order": 1 }, ...] }`. |
| **PATCH** | `/api/meeting-vote-topics/{id}/open` | **Mở phiếu** — set `status=opened`, `opened_at=now()`, đại biểu mới vote được. Permission `meeting-vote-topics.update`. |
| **PATCH** | `/api/meeting-vote-topics/{id}/close` | **Đóng phiếu** — set `status=closed`, `closed_at=now()`, không cho vote thêm. |

### <a id="vote-topic-body"></a>5.2 Vote topic body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_id` | integer | ✅ | FK `meetings.id`. |
| `meeting_agenda_id` | integer | — | FK `meeting_agendas.id` — gắn vào chương trình họp cụ thể. |
| `title` | string (≤255) | ✅ | Tên chương trình biểu quyết. |
| `vote_type` | enum | ✅ | `agree_disagree_abstain` (Đồng ý/Không đồng ý/Không ý kiến) \| `approve_reject_abstain` (Tán thành/Không tán thành/Không ý kiến). |
| `ballot_mode` | enum | ✅ | `anonymous` (ẩn danh) \| `public_named` (công khai danh tính). |
| `show_result_on_projector` | boolean | — | Hiển thị tổng hợp trên màn chiếu. |
| `show_result_on_personal_device` | boolean | — | Hiển thị tổng hợp trên thiết bị cá nhân của đại biểu. |
| `sort_order` | integer (≥0) | — | Thứ tự hiển thị. |
| `status` | enum | — | `draft` (mặc định) \| `opened` \| `closed`. Nên để BE tự đổi qua `/open` `/close`. |

```json
{
  "meeting_id": 1,
  "meeting_agenda_id": 4,
  "title": "Biểu quyết thông qua nghị quyết phân bổ ngân sách 2026",
  "vote_type": "agree_disagree_abstain",
  "ballot_mode": "public_named",
  "show_result_on_projector": true,
  "show_result_on_personal_device": true,
  "sort_order": 1
}
```

### 5.3 Response (MeetingVoteTopicResource)

```json
{
  "id": 1,
  "meeting_id": 1,
  "meeting_agenda_id": 4,
  "title": "Biểu quyết thông qua nghị quyết phân bổ ngân sách 2026",
  "vote_type": "agree_disagree_abstain",
  "ballot_mode": "public_named",
  "show_result_on_projector": true,
  "show_result_on_personal_device": true,
  "sort_order": 1,
  "status": "opened",
  "opened_at": "10:15:00 15/05/2026",
  "closed_at": null,
  "created_at": "08:00:00 08/05/2026",
  "updated_at": "10:15:00 15/05/2026"
}
```

### 5.4 Phiếu biểu quyết — `/api/meeting-vote-responses`

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-vote-responses/stats` | `{ total, agree, disagree, approve, reject, abstain }`. Query: `meeting_vote_topic_id`. |
| GET | `/api/meeting-vote-responses` | Danh sách phiếu. Query: `meeting_vote_topic_id`, `limit`. **Tôn trọng `ballot_mode`**: nếu `anonymous` thì FE phải ẩn `participant_name`. |
| POST | `/api/meeting-vote-responses` | Đại biểu gửi phiếu. Body: [Response body](#vote-response-body). |
| PATCH | `/api/meeting-vote-responses/{id}` | Sửa phiếu (chỉ khi topic chưa `closed`). |
| DELETE | `/api/meeting-vote-responses/{id}` | Xóa phiếu (admin). |
| POST | `/api/meeting-vote-responses/bulk-delete` | Body `{ "ids": [...] }`. |

### <a id="vote-response-body"></a>5.5 Vote response body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_vote_topic_id` | integer | ✅ | FK `meeting_vote_topics.id`. |
| `meeting_participant_id` | integer | ✅ | FK `meeting_participants.id`. **Phải thuộc cùng meeting với topic** — BE check. |
| `option` | enum | ✅ | `agree \| disagree \| approve \| reject \| abstain`. Phải hợp lệ với `topic.vote_type`. |

```json
{
  "meeting_vote_topic_id": 1,
  "meeting_participant_id": 12,
  "option": "agree"
}
```

> **Unique** `(meeting_vote_topic_id, meeting_participant_id)` — 1 đại biểu chỉ 1 phiếu / topic. Re-submit sẽ update phiếu cũ (khi topic chưa closed).
> **Snapshot `voted_at`** = `now()` lúc tạo/update.

### 5.6 Workflow đầy đủ (theo spec mục 2.5 + Giai đoạn C)

```
[Soạn meeting]              [Trong họp]                    [Sau khi đóng]
    │                            │                                │
    ▼                            ▼                                ▼
status=draft  ──open()──▶  status=opened  ──close()──▶  status=closed
                                │
                                ▼
                        Đại biểu vote (POST /vote-responses)
                        - Phải status=opened
                        - 1 phiếu / participant / topic
                        - Validate option ∈ vote_type
```

**Rules quan trọng:**
1. Vote chỉ accept khi `topic.status = 'opened'`
2. Sau `closed`, FE phải block UI sửa phiếu
3. `anonymous` → FE/BE ẩn danh tính trong list responses
4. `public_named` → chỉ admin/chủ trì xem detail per-person; vai trò khác chỉ xem aggregate
5. Aggregate hiển thị theo `show_result_on_*` flags

### 5.7 FE flow điều hành phiên họp

1. Lúc soạn meeting: `POST /meeting-vote-topics` (nhiều lần) — tạo các chủ đề biểu quyết, status=`draft`
2. Trong họp, đến phần biểu quyết:
   - `PATCH /meeting-vote-topics/{id}/open` → đại biểu thấy modal vote
3. Đại biểu vote: `POST /meeting-vote-responses` với `option`
4. Điều hành đóng: `PATCH /meeting-vote-topics/{id}/close`
5. FE hiển thị kết quả tổng hợp từ `GET /meeting-vote-responses/stats?meeting_vote_topic_id=X`

---

## Patterns dùng chung

### Bulk delete
- `POST /api/{resource}/bulk-delete`, body `{ "ids": [1,2,3] }`. Scope theo org hiện tại.

### Bulk update status (khi resource có)
- `PATCH /api/{resource}/bulk-status`, body `{ "ids": [...], "status": "..." }`.

### Reorder (chỉ cho agendas + documents)
- `PATCH /api/{resource}/reorder`, body `{ "items": [{ "id": 1, "sort_order": 1 }, { "id": 2, "sort_order": 2 }] }`.
- Bọc trong DB transaction; chỉ update `sort_order` cho những id thuộc org hiện tại.

### Validation lỗi (422)

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ.",
  "code": "VALIDATION_ERROR",
  "errors": {
    "title": ["Tiêu đề là trường bắt buộc."],
    "start_time": ["Thời gian bắt đầu phải là ngày hợp lệ."]
  }
}
```

### Cross-org (404)

Truy cập qua `{id}` cho bản ghi của tổ chức khác → middleware `ensure.route.org` trả `404 NOT_FOUND`.

---

## Tóm tắt cho FE

1. **Trang tạo cuộc họp** — form đơn giản, chọn `meeting_type_id`/`meeting_location_id` từ dropdown public-options của các catalog.
2. **Trang chương trình họp** — list theo `meeting_id`, hỗ trợ kéo thả qua `/reorder`.
3. **Trang tài liệu** — list theo `meeting_id`, upload `multipart/form-data` field `file` (1 tài liệu = 1 file). Hiển thị `file_url` để FE tải. Cuộc họp nhiều file → tạo nhiều record document.
4. **Trang đại biểu tham dự** — chọn đại biểu qua dropdown từ `/api/meeting-attendees` (đã filter theo org). Snapshot tự động khi store.
5. **Publish cuộc họp** — gọi `PATCH /meetings/{id}/status` với `{"status":"published"}`. BE tự gửi giấy mời (FCM/email) cho participants. FE chỉ cần chờ response và hiển thị "Đã gửi giấy mời".
