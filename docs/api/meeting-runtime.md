# API Cuộc họp + Chương trình + Tài liệu + Đại biểu tham dự

Tài liệu cho FE implement luồng tạo/quản lý cuộc họp:

| Resource | Base path | Tên hiển thị |
|---|---|---|
| Cuộc họp | `/api/meetings` | Meeting |
| Chương trình họp | `/api/meeting-agendas` | Meeting Agenda |
| Tài liệu họp | `/api/meeting-documents` | Meeting Document |
| Đại biểu tham dự | `/api/meeting-participants` | Meeting Participant |

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
| PATCH | `/api/meetings/bulk-status` | Body `{ "ids": [...], "status": "draft\|published\|in_progress\|completed\|cancelled" }`. |
| PATCH | `/api/meetings/{id}/status` | **Quan trọng**: khi `draft → published`, BE tự động tạo `meeting_invitations` cho tất cả participants + dispatch event `MeetingPublished` (gửi FCM/email theo cấu hình `meeting/notification-config`). |
| GET | `/api/meetings/export` | Tải Excel `meetings.xlsx`. Query giống `index`. Cột: `STT, Tiêu đề, Loại, Địa điểm, Công khai, Bắt đầu, Kết thúc, Trạng thái, Lượt xem, Phát hành, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID`. |

> Meetings **không hỗ trợ import** — bao gồm relationships phức tạp (agendas/documents/participants), tạo qua UI thay vì bulk-import.

### <a id="meeting-body"></a>1.3 Meeting body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `title` | string (≤255) | ✅ | Tiêu đề cuộc họp. |
| `meeting_type_id` | integer | — | FK `meeting_types.id`. |
| `meeting_location_id` | integer | — | FK `meeting_locations.id`. |
| `is_public` | boolean | ✅ | Có cho phép xem ở trang công khai không. |
| `start_time` | datetime `Y-m-d H:i:s` | ✅ | Thời gian bắt đầu. |
| `end_time` | datetime `Y-m-d H:i:s` | — | Phải `>= start_time`. |
| `content` | text | — | Nội dung cuộc họp. |
| `status` | enum | ✅ | `draft` \| `published` \| `in_progress` \| `completed` \| `cancelled`. |
| `published_at` | datetime | — | Tự set khi publish. |

### 1.4 Response (MeetingResource)

```json
{
  "id": 1,
  "organization_id": 1,
  "meeting_type_id": 1,
  "meeting_type_name": "HĐND thường kỳ",
  "meeting_location_id": 1,
  "meeting_location_name": "Hội trường lớn UBND TP Đà Nẵng",
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
  "updated_at": "08:00:00 08/05/2026"
}
```

> **Side-effects khi publish (`changeStatus` → `published`)**:
> - Tạo idempotent `meeting_invitations` cho từng participant (status=`pending`).
> - Dispatch event `MeetingPublished` → listener đọc `notification_event_configs` (module `meeting`) để gửi FCM/email/SMS. Nếu admin chưa enable event → không gửi.
> - Re-publish (đã từng publish trước đó) **không** tạo invitation trùng.

---

## 2. Chương trình cuộc họp — `/api/meeting-agendas`

Mỗi cuộc họp có nhiều chương trình (agenda) — sắp theo `sort_order`. Hỗ trợ phân cấp 2 tầng qua `parent_id`.

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
| POST | `/api/meeting-documents` | Tạo. Body: **multipart/form-data** (xem [Document body](#document-body)). |
| PUT \| PATCH | `/api/meeting-documents/{id}` | Cập nhật. Có thể upload file mới (replace) hoặc gửi `remove_file=true` để xóa file cũ. |
| DELETE | `/api/meeting-documents/{id}` | Xóa. |
| POST | `/api/meeting-documents/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-documents/bulk-status` | Body `{ "ids": [...], "status": "draft\|published" }`. |
| PATCH | `/api/meeting-documents/{id}/status` | Body `{ "status": "draft\|published" }`. |
| PATCH | `/api/meeting-documents/reorder` | Body `{ "items": [{ "id": 1, "sort_order": 1 }, ...] }`. |

### <a id="document-body"></a>3.3 Document body (multipart/form-data)

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_id` | integer | ✅ | FK `meetings.id`. |
| `meeting_agenda_id` | integer | — | FK `meeting_agendas.id` — gắn vào chương trình cụ thể. |
| `meeting_document_type_id` | integer | — | FK `meeting_document_types.id`. |
| `title` | string (≤255) | ✅ | Tiêu đề tài liệu. |
| `document_number` | string (≤255) | — | Số văn bản (vd `01/TTr-UBND`). |
| `summary` | text | — | Tóm tắt. |
| `file` | file (≤10 MB) | — | File đính kèm. Lưu vào collection `meeting-document-attachments` (disk `public`). |
| `is_public` | boolean | ✅ | Hiển thị ngoài trang công khai (kèm theo cờ public của meeting). |
| `status` | enum | ✅ | `draft` \| `published`. |
| `sort_order` | integer (≥0) | — | Auto-tăng nếu không truyền (last + 1 trong meeting). |
| `remove_file` | boolean | — | **Chỉ trên `update`** — xóa file hiện có. |

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

---

## 4. Danh sách đại biểu tham dự — `/api/meeting-participants`

`MeetingParticipant` = mapping giữa `meeting` và `meeting_attendee` (= user). Lưu **snapshot** thông tin đại biểu lúc mời (per spec — báo cáo/giấy mời không thay đổi khi user update profile sau).

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
| `role` | enum | — | `delegate` (mặc định) \| `chairperson` \| `operator` \| `guest`. |
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
  "role": "chairperson",
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
3. **Trang tài liệu** — list theo `meeting_id`, upload file qua `multipart/form-data`. Hiển thị `file_url` để FE tải file.
4. **Trang đại biểu tham dự** — chọn đại biểu qua dropdown từ `/api/meeting-attendees` (đã filter theo org). Snapshot tự động khi store.
5. **Publish cuộc họp** — gọi `PATCH /meetings/{id}/status` với `{"status":"published"}`. BE tự gửi giấy mời (FCM/email) cho participants. FE chỉ cần chờ response và hiển thị "Đã gửi giấy mời".
