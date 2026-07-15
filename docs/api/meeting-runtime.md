# API Cuộc họp + Chương trình + Tài liệu + Đại biểu tham dự

> Cập nhật lần cuối: 16:36:35 15/07/2026

Tài liệu cho FE implement luồng tạo/quản lý cuộc họp:

| Resource | Base path | Tên hiển thị |
|---|---|---|
| Cuộc họp | `/api/meetings` | Meeting |
| Chương trình họp | `/api/meeting-agendas` | Meeting Agenda |
| Tài liệu họp | `/api/meeting-documents` | Meeting Document |
| Đại biểu tham dự | `/api/meeting-participants` | Meeting Participant |
| Chương trình biểu quyết | `/api/meeting-vote-topics` (soạn/CRUD) + `/api/meetings/{meeting}/vote-topics` (runtime open/close/cast) | Meeting Vote Topic |
| Phiếu biểu quyết | `/api/meetings/{meeting}/vote-responses` (**không còn route phẳng**, đã gộp nested — commit `b8a3100`) | Meeting Vote Response |

> ⚠️ Ngoài các resource ở bảng trên, nhiều resource in-meeting khác (điểm danh, thảo luận/chất vấn, ghi chú cá nhân, respond invitation) **chỉ tồn tại dưới dạng nested** `/api/meetings/{meeting}/...` — không có base path phẳng riêng. Xem chi tiết theo tab ở [docs/api/meeting-room-fe.md](./meeting-room-fe.md).

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

> ⚠️ **Đổi prefix (2026-05-14)**: toàn bộ endpoint public của module Meeting đã chuyển từ `/api/meetings/public*` sang `/api/public/meetings*` (đồng bộ với quy ước chung `/api/public/{resource}` toàn hệ thống — xem `routes/api.php`). Cũng đổi tương tự cho catalog: `meeting-types`, `meeting-locations`, `meeting-document-types` → `/api/public/meeting-types`, `/api/public/meeting-locations`, `/api/public/meeting-document-types` (+ `/options` thay vì `/public-options`).

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/public/meetings` | "Visible" index — endpoint chung cho cả guest + auth user. Guest: chỉ meeting `is_public=true + status=published`. Auth (gửi token): union với meeting user là chủ trì/thư ký/đã được mời tham gia. Query: `search`, `meeting_type_id`, `from_date`, `to_date`, `sort_by`, `sort_order`, `limit`. |
| GET | `/api/public/meetings/stats` | Stats công khai phái sinh từ start_time/end_time: `{total, upcoming, in_progress, finished}`. Cùng scope visibility với `public` index. |
| GET | `/api/public/meetings/{id}` | Chi tiết cuộc họp công khai. Tự tăng `view_count` + ghi log vào `meeting_views`. Chặn 404 nếu không public/published. |
| GET | `/api/public/meetings/document-tree` | Cây tài liệu công khai (group theo meeting/agenda) — dùng cho trang tra cứu tài liệu công khai. |
| GET | `/api/public/meetings/{meeting}/agendas` | Chương trình họp công khai (Tab 1 guest). Gate `MeetingPolicy::viewPublic`. |
| GET | `/api/public/meetings/{meeting}/documents` | Tài liệu công khai (Tab 2 guest). Cùng gate. |
| GET | `/api/public/meetings/{meeting}/documents/export` | Export Excel tài liệu — auth-optional, tự resolve scope theo Bearer/cookie. |
| GET | `/api/public/meetings/{meeting}/documents/export-views` | Export Excel lượt xem tài liệu. |

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
| POST | `/api/meetings/{id}/duplicate` | Sao chép cuộc họp — tiêu đề thêm hậu tố "(sao chép)". Copy hết trừ **tài liệu** và **chương trình biểu quyết**. Bản sao luôn ở trạng thái `draft`. Permission `meetings.store`. |
| PATCH | `/api/meetings/{id}/reopen` | Mở lại cuộc họp — đổi `status` từ `completed` về `published`. Permission `meetings.changeStatus`. |
| PATCH | `/api/meetings/{id}/lock-attendance` | Operator khoá danh sách điểm danh — đại biểu không thể tự `checkin`/`mark-absent` nữa (service trả 422). Set `attendance_locked=true`. Gate `can:manageAttendance,meeting` (chair/op FK). |
| PATCH | `/api/meetings/{id}/unlock-attendance` | Operator mở khoá điểm danh. Set `attendance_locked=false`. Gate `can:manageAttendance,meeting`. |
| PATCH | `/api/meetings/{id}/end-early` | Kết thúc cuộc họp — BE set trực tiếp `status = completed` (không chỉ set `end_time`). Nếu meeting chưa có `end_time` thì tự set `end_time = now()` (dùng cho biên bản họp). Broadcast `MeetingEndedEarly`. Gate `can:endEarly,meeting`. |
| PATCH | `/api/meetings/{id}/highlight-agenda` | Highlight chương trình lên màn chiếu (Tab 8). Body `{ "agenda_id": int\|null }`. Null = bỏ highlight. Validate agenda thuộc đúng meeting. Gate `can:highlight,meeting`. |
| PATCH | `/api/meetings/{id}/highlight-discussion` | Highlight đăng ký phát biểu/chất vấn lên màn chiếu. Body `{ "discussion_registration_id": int\|null }`. Null = bỏ highlight. Validate registration thuộc đúng meeting. Gate `can:highlight,meeting`. |
| PATCH | `/api/meetings/{id}/toggle-projector-file` | Tín hiệu bật/tắt hiển thị 1 file lên màn chiếu. Body: `file_url`, `file_name`, `file_type`, `is_open` (bool). Gate `can:highlight,meeting`. |
| GET | `/api/meetings/{id}/qr-token` | Lấy `checkin_token` để FE tự gen QR (`{origin}/checkin/{token}`) — đại biểu scan → gọi `POST /api/meetings/{meeting}/attendances/checkin-by-token`. Chỉ privileged role (chair/operator/`qr_manager_user_id`). Gate `can:showQrCode,meeting`. |
| POST | `/api/meetings/{id}/export-minutes` | Xuất biên bản `.docx` từ template — **method POST** (không phải PATCH), controller **`MeetingMinutesTemplateController::exportMinutes`** (không phải `MeetingController`). Body `{ "template_id": int required }`. Auth-only, gate `exportReports` thuần FK (chair/op meeting, không Spatie bypass). Trả file download trực tiếp. |
| GET | `/api/meetings/{id}/minutes-templates` | List template biên bản cho dialog "Chọn template" trước export. Gate `can:exportReports,meeting`. |
| GET | `/api/meetings/export` | Tải Excel `meetings.xlsx`. Query giống `index`. Cột: `STT, Tiêu đề, Loại, Địa điểm, Công khai, Bắt đầu, Kết thúc, Trạng thái, Lượt xem, Phát hành, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID`. |

> Meetings **không hỗ trợ import** — bao gồm relationships phức tạp (agendas/documents/participants), tạo qua UI thay vì bulk-import.

### <a id="meeting-body"></a>1.3 Meeting body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `title` | string (≤255) | ✅ | Tiêu đề cuộc họp. |
| `meeting_type_id` | integer | — | FK `meeting_types.id`. |
| `meeting_location_id` | integer | — | FK `meeting_locations.id`. |
| `chairperson_meeting_attendee_id` | integer | — | FK `meeting_attendees.id` — chủ trì. **Không tự tạo participant** (chair/operator riêng biệt với participants). |
| `operator_meeting_attendee_id` | integer | — | FK `meeting_attendees.id` — thư ký/điều hành. |
| `qr_manager_user_id` | integer | — | FK `users.id` — user được giao quyền bật QR điểm danh, không cần là chair/op. Phải cùng tổ chức (custom rule validate qua `model_has_roles`). |
| `is_public` | boolean | — | Có cho phép xem ở trang công khai không. **Nullable trong request thực tế** (không required). |
| `has_online_room` | boolean | — | Có phòng họp trực tuyến hay không. |
| `allow_host_management` | boolean | — | Cho phép chủ trì (không chỉ operator) quản lý cuộc họp. |
| `start_time` | datetime `Y-m-d H:i:s` | — | Thời gian bắt đầu. **Nullable trong request thực tế** (không required). |
| `end_time` | datetime `Y-m-d H:i:s` | — | Phải `>= start_time`. |
| `attendance_open_at` | datetime | — | Thời điểm mở điểm danh — đại biểu chỉ `checkin` được trong khoảng `[attendance_open_at, attendance_close_at]`. Null = không giới hạn. |
| `attendance_close_at` | datetime | — | Phải `>= attendance_open_at`. |
| `content` | text | — | Nội dung cuộc họp. |
| `status` | enum | — | `draft` \| `published` \| `cancelled` \| `completed` (4 giá trị — `completed` được set trực tiếp bởi BE khi gọi `end-early`, không phải chỉ derive ở FE). |
| `published_at` | datetime | — | Tự set khi publish. |
| `projector_image` | file (jpg/jpeg/png/webp, ≤10MB) | — | Ảnh nền màn chiếu riêng cho meeting. Null → FE fallback `MeetingSetting.projector_image` của tổ chức. |
| `guests[]` | array | — | Khách mời nhập trực tiếp (không qua `meeting_attendees`). Mỗi item: `{ name, position_name, phone, email, zalo_user_id, organization_name }` — `name`/`phone`/`email` required nếu có item. BE tạo `MeetingGuest` + invitation cho từng item. Trên `update`: không gửi key `guests` = giữ nguyên, gửi `[]` = xoá hết. |
| `reminders[]` | array | — | Mốc nhắc lịch tuỳ chỉnh. Mỗi item: `{ id?, reminder_type: instant\|scheduled, moment: before\|on\|after, offset_minutes, channels: [mail\|sms\|zalo\|zalo_zns\|fcm] }`. Trên `update`: không gửi = giữ nguyên, gửi `[]` = xoá hết `CUSTOM` reminder. |

> **Chủ trì + Thư ký** lưu trực tiếp trên `meetings` qua 2 FK (singular cardinality). FE chọn từ `/api/meeting-attendees` rồi gửi `chairperson_meeting_attendee_id` + `operator_meeting_attendee_id` lúc tạo/update meeting. **Không tự tạo participant** — chair/operator độc lập với danh sách participants. Nếu muốn họ tham gia điểm danh/nhận giấy mời, FE thêm thủ công qua `POST /meeting-participants`.
>
> **Upload đa phần (multipart/form-data)**: `store`/`update` chấp nhận multipart vì có field file `projector_image`. Nếu không cần đổi ảnh, gửi JSON thường vẫn được (field optional).

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
  "meeting_location_google_maps_url": "https://maps.google.com/?q=...",
  "chairperson_meeting_attendee_id": 12,
  "chairperson": { "id": 12, "name": "Nguyễn Văn Hùng", "email": "nvhung@...", "position_name": "Chủ tịch HĐND", "department_name": "HĐND TP", "user_id": 8 },
  "operator_meeting_attendee_id": 13,
  "operator": { "id": 13, "name": "Trần Thị Mai", "email": "ttmai@...", "position_name": "Phó CT HĐND", "department_name": "HĐND TP", "user_id": 9 },
  "title": "Kỳ họp HĐND thường kỳ tháng 5/2026",
  "is_public": true,
  "has_online_room": false,
  "content": "...",
  "start_time": "08:00:00 15/05/2026",
  "end_time": "16:00:00 15/05/2026",
  "attendance_open_at": "2026-05-15T07:30:00+07:00",
  "attendance_close_at": "2026-05-15T08:15:00+07:00",
  "status": "published",
  "view_count": 145,
  "documents_count": 3,
  "published_at": "08:00:00 08/05/2026",
  "attendance_locked": false,
  "allow_host_management": false,
  "current_user_meeting_role": "chairperson",
  "is_attendance_confirmed": false,
  "qr_manager_user_id": null,
  "qr_manager": null,
  "projector_image_media_id": null,
  "projector_image_url": null,
  "current_meeting_agenda_id": null,
  "current_meeting_discussion_registration_id": null,
  "current_agenda": null,
  "current_discussion_registration": null,
  "created_by": { "id": 1, "name": "Admin", "avatar": null },
  "updated_by": { "id": 1, "name": "Admin", "avatar": null },
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 08/05/2026",
  "participants": [
    { "id": 12, "meeting_attendee_id": 12, "display_name": "Nguyễn Văn Hùng", "email": "...", "phone": "...", "response_status": "accepted", ... },
    { "id": 13, "meeting_attendee_id": 13, "display_name": "Trần Thị Mai", ... },
    { "id": 14, "meeting_attendee_id": 14, "display_name": "Lê Hoàng Nam", ... }
  ],
  "agendas": [
    { "id": 1, "sort_order": 1, "start_time": "08:00:00", "end_time": "08:30:00", "content": "Khai mạc kỳ họp.", ... }
  ],
  "documents": [
    { "id": 1, "title": "Tờ trình ngân sách", "media_id": 42, "file_url": "/storage/42/to-trinh.pdf", "file_name": "to-trinh.pdf", "is_public": true, ... }
  ],
  "vote_topics": [
    { "id": 1, "title": "Biểu quyết thông qua nghị quyết", "vote_type": "agree_disagree_abstain", "ballot_mode": "public_named", "opened_at": null, "closed_at": null, "sort_order": 1, ... }
  ],
  "reminders": [
    { "id": 5, "reminder_type": "scheduled", "moment": "before", "offset_minutes": 30, "channels": ["mail", "zalo"], ... }
  ],
  "guests": [
    { "id": 1, "name": "Nguyễn Văn Khách", "position_name": "Giám đốc", "phone": "0901234567", "email": "khach@...", "zalo_user_id": null, "organization_name": "Sở Y tế", "invited_at": "08:00:00 08/05/2026" }
  ]
}
```

> **`created_by`/`updated_by` là object** (qua `formatUserSummary()`), **không phải string đơn giản** — luôn trả `{ id, name, avatar }` (avatar là url ảnh hoặc null).
>
> Trả ở `show` only; `index` (list view) **không** trả `participants/agendas/documents/vote_topics/reminders/guests`— gọi `/api/meeting-{participants,agendas,documents,vote-topics}?meeting_id=X` riêng nếu cần phân trang/filter (xem lưu ý ở mục tương ứng — 1 số resource này đã đổi sang route nested `/api/meetings/{meeting}/...`, xem phần dưới).

> **Side-effects khi publish (`changeStatus` → `published`)**:
> - Tạo idempotent `meeting_invitations` cho từng participant (status=`pending`).
> - Dispatch event `MeetingPublished` → listener đọc `notification_event_configs` (module `meeting`) để gửi FCM/email/SMS. Nếu admin chưa enable event → không gửi.
> - Set `published_at = now()` **chỉ lần đầu** publish (`published_at` đang null). Republish sau đó giữ nguyên giá trị cũ.
> - Re-publish (đã từng publish trước đó) **không** tạo invitation trùng.

> **`current_user_meeting_role`** (thay thế hoàn toàn cho placeholder `getMyMeetingRole()` client-side trước đây) — BE trả trực tiếp vai trò CHÍNH của user hiện tại trong meeting, ưu tiên: FK chair > FK operator > có participant entry (`participant`) > `null` (không liên quan). Chair có cả participant entry vẫn trả `"chairperson"`. Dùng `Auth::guard('sanctum')` fallback nên hoạt động cả trên route public không có middleware `auth:sanctum`. FE dùng field này để show/hide nút điều hành (end-early, lock-attendance, highlight, vote open/close) thay vì tự so sánh `user.id` với `chairperson`/`operator`.
>
> **`is_attendance_confirmed`** — true nếu đại biểu hiện tại đã điểm danh `present` (được phép vote). Field mới, đi kèm `current_user_meeting_role`.

> **Trạng thái `status` (Tab 7 Điều hành)** — `MeetingStatusEnum` có **4 giá trị**: `draft \| published \| cancelled \| completed`. Gọi `PATCH /meetings/{id}/end-early` → BE set **trực tiếp `status = completed`** (kèm `end_time = now()` nếu chưa có) — **không** còn kiểu "chỉ set `end_time` rồi FE tự derive phase" như thiết kế ban đầu. FE có thể đọc thẳng `meeting.status === 'completed'` thay vì so sánh `now()` với `end_time`.
> - `attendance_locked`: thư ký/operator có thể khoá để chốt danh sách điểm danh (`lock-attendance`/`unlock-attendance`).
> - `end-early` broadcast `MeetingEndedEarly`. Không có nút Bắt đầu/Tạm dừng — operator không điều khiển runtime `in_progress`, phase "đang diễn ra" vẫn suy ra từ `start_time`/`end_time` khi `status=published`; chỉ trạng thái kết thúc là literal.
> - `reopen`: đưa meeting từ `completed` về lại `published` (mở lại cuộc họp).

> **Highlight pointers cho Tab 8 màn chiếu** — operator click chương trình hoặc đăng ký phát biểu, BE lưu 2 FK trên meeting, FE màn chiếu polling `GET /api/meetings/{id}` mỗi 3-5s đọc `current_agenda` / `current_discussion_registration` (đã preload) để render khối tương ứng. Cả 2 độc lập — có thể highlight đồng thời 1 chương trình + 1 phát biểu trong chương trình đó. Truyền body với key `null` để bỏ highlight.

> **Phase derived ở FE** (BE giữ 4 status: `draft / published / cancelled / completed` — `completed` là literal do BE set khi `end-early`, các phase còn lại vẫn suy ra từ thời gian):
> ```js
> function getMeetingPhase(meeting) {
>   if (meeting.status === 'cancelled') return 'cancelled'   // "Đã hủy"
>   if (meeting.status === 'draft')     return 'draft'       // "Bản nháp"
>   if (meeting.status === 'completed') return 'finished'    // "Đã kết thúc" — BE set trực tiếp, không cần so now()
>   const now = new Date()
>   const start = parseDateTime(meeting.start_time)
>   const end = meeting.end_time ? parseDateTime(meeting.end_time) : null
>   if (now < start)                  return 'upcoming'      // "Sắp diễn ra"
>   if (end && now > end)             return 'finished'      // "Đã kết thúc" (quá end_time nhưng chưa ai bấm end-early)
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

> ⚠️ Đã đổi prefix (2026-05-14): `/api/meeting-documents/public*` → `/api/public/meeting-documents*`.

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/public/meeting-documents` | "Visible" document index. Guest hoặc auth-không-tham-gia: doc `is_public=true` + meeting `is_public=true + status=published`. Auth participant của meeting (truyền `meeting_id`): thấy mọi doc. Query: `meeting_id`, `search`, `limit`. |
| GET | `/api/public/meeting-documents/{id}/download` | Track + redirect 302 tới file. Citizen download không cần auth. Increment `download_count` + log `meeting_views`. |
| GET | `/api/public/meeting-documents/{id}` | Chi tiết tài liệu công khai. Tự tăng `view_count` + log `meeting_views`. Chặn 404 nếu không công khai. |

### 3.2 Authenticated CRUD

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-documents` | Danh sách phân trang. Auth scope theo participation: nếu user không phải chủ trì/thư ký/participant của meeting (truyền `meeting_id`) → tự động filter `is_public=true`. Query: `meeting_id`, `meeting_agenda_id`, `meeting_document_type_id`, `search` (title/document_number), `is_public`, `sort_by` (`id\|sort_order\|created_at\|updated_at`), `sort_order`, `limit`. |
| GET | `/api/meeting-documents/{id}/download` | Track + redirect 302. Yêu cầu auth + permission `meeting-documents.show`. |
| GET | `/api/meeting-documents/{id}` | Chi tiết. |
| POST | `/api/meeting-documents` | Tạo (1 tài liệu = 1 file). Body: **multipart/form-data** với `file` (xem [Document body](#document-body)). |
| PUT \| PATCH | `/api/meeting-documents/{id}` | Cập nhật metadata + thay/xóa file. `file` upload mới (replace file cũ), `remove_file=true` xóa file hiện tại. |
| DELETE | `/api/meeting-documents/{id}` | Xóa. |
| POST | `/api/meeting-documents/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-documents/reorder` | Body `{ "items": [{ "id": 1, "sort_order": 1 }, ...] }`. |

> **Không có workflow status** — tài liệu chỉ dùng `is_public` để hiển thị/ẩn ở trang công khai (bên cạnh `is_public` + `status=published` của meeting cha).

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
  "file_url": "/storage/42/to-trinh.pdf",
  "file_name": "to-trinh.pdf",
  "is_public": true,
  "view_count": 56,
  "download_count": 12,
  "sort_order": 1,
  "created_by": { "id": 1, "name": "Admin", "avatar": null },
  "updated_by": { "id": 1, "name": "Admin", "avatar": null },
  "created_at": "08:00:00 01/05/2026",
  "updated_at": "08:00:00 01/05/2026"
}
```

> `created_by`/`updated_by` là object `{ id, name, avatar }` (qua `formatUserSummary()`), không phải string.
>
> Để xóa file: `PATCH /api/meeting-documents/{id}` với body `{ "remove_file": true }`. Để thay thế: gửi `file` mới (BE xóa file cũ + gắn file mới trong cùng transaction).

---

## 4. Danh sách đại biểu tham dự — `/api/meeting-participants`

`MeetingParticipant` = mapping giữa `meeting` và `meeting_attendee` (= user). Lưu **snapshot** thông tin đại biểu lúc mời (per spec — báo cáo/giấy mời không thay đổi khi user update profile sau).

> **Chủ trì + Thư ký KHÔNG ở đây** — đã chuyển lên FK trên `meetings` (`chairperson_meeting_attendee_id`, `operator_meeting_attendee_id`) vì cardinality 1-1. Xem [Section 1.3](#meeting-body).
>
> Participant **không có** field `role` — tất cả chỉ là "người tham gia". Để check chủ trì/thư ký, FE so sánh `meeting_attendee_id` với `meeting.chairperson_meeting_attendee_id` / `operator_meeting_attendee_id`.

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-participants/stats` | `{ total, accepted, declined }`. Query: `meeting_id`, `response_status`, `search`. |
| GET | `/api/meeting-participants` | Danh sách phân trang. Query: `meeting_id`, `response_status`, `search` (display_name), `sort_by` (`id\|display_name\|responded_at\|created_at`), `sort_order`, `limit`. |
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
| `response_status` | enum | — | `pending` (mặc định) \| `accepted` \| `declined`. |
| `absence_reason` | text | — | Lý do vắng (khi `declined`). |

```json
{
  "meeting_id": 1,
  "meeting_attendee_id": 12,
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

> ⚠️ **Route bị gộp lồng nhau (commit `b8a3100`, 2026-06-09)**: `open`/`close` đã bị **xóa khỏi route phẳng** `/api/meeting-vote-topics/{id}/open|close` — controller vẫn còn method (`open`/`close`) nhưng route không đăng ký nữa (dead code). Endpoint thật giờ chỉ có dạng nested `PATCH /api/meetings/{meeting}/vote-topics/{id}/open|close` — xem [mục 5.4](#5-4-nested-vote-topics-vote-responses-runtime).

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meeting-vote-topics/stats` | `{ total, draft, opened, closed }`. Query: `meeting_id`. Admin/Spatie permission `meeting-vote-topics.stats`. |
| GET | `/api/meeting-vote-topics` | Danh sách phân trang (admin catalog/setup). Query: `meeting_id`, `status` (BE derive từ opened_at/closed_at — accepted: `draft`, `opened`, `closed`), `search`, `sort_by` (`id\|sort_order\|created_at\|updated_at`), `sort_order`, `limit`. Permission `meeting-vote-topics.index`. |
| GET | `/api/meeting-vote-topics/{id}` | Chi tiết. |
| POST | `/api/meeting-vote-topics` | Tạo (thường ở giai đoạn soạn meeting). Body: [Vote topic body](#vote-topic-body). |
| PUT \| PATCH | `/api/meeting-vote-topics/{id}` | Cập nhật (FE tự gate UI theo phase derived). |
| DELETE | `/api/meeting-vote-topics/{id}` | Xóa. |
| POST | `/api/meeting-vote-topics/bulk-delete` | Body `{ "ids": [...] }`. |
| PATCH | `/api/meeting-vote-topics/reorder` | Body `{ "items": [{ "id": 1, "sort_order": 1 }, ...] }`. |

> Bảng trên là route phẳng **chỉ dùng cho admin catalog/setup** (Spatie permission `meeting-vote-topics.{action}`) — soạn topic lúc tạo meeting. Runtime trong phiên họp (view/open/close/cast vote) dùng route nested, xem 5.4.

### <a id="vote-topic-body"></a>5.2 Vote topic body

| Field | Type | Required | Ghi chú |
|---|---|---|---|
| `meeting_id` | integer | ✅ | FK `meetings.id`. |
| `meeting_agenda_id` | integer | — | FK `meeting_agendas.id` — gắn vào chương trình họp cụ thể. |
| `title` | string (≤255) | ✅ | Tên chương trình biểu quyết. |
| `description` | text | — | Nội dung diễn giải hiển thị trên popup biểu quyết của đại biểu + màn chiếu. Có thể đặt sẵn lúc tạo hoặc nhập tại `/open`. |
| `duration_minutes` | int (1..600) | — | Thời lượng phiên biểu quyết (phút). FE tự đếm ngược. |
| `vote_type` | enum | ✅ | `agree_disagree_abstain` (Đồng ý/Không đồng ý/Không ý kiến) \| `approve_reject_abstain` (Tán thành/Không tán thành/Không ý kiến). |
| `ballot_mode` | enum | ✅ | `anonymous` (ẩn danh) \| `public_named` (công khai danh tính). |
| `show_result_on_projector` | boolean | — | Hiển thị tổng hợp trên màn chiếu. |
| `show_result_on_personal_device` | boolean | — | Hiển thị tổng hợp trên thiết bị cá nhân của đại biểu. |
| `sort_order` | integer (≥0) | — | Thứ tự hiển thị. |
| ~~`status`~~ | — | — | **Đã bỏ field 2026-05-07**. Resource trả thêm `phase` (BE compute) + `expires_at_iso` (ISO 8601 cho countdown). Phase logic: opened_at NULL → `draft`; opened_at NOT NULL + closed_at NULL + chưa hết `duration_minutes` → `opened`; closed_at NOT NULL HOẶC `opened_at + duration_minutes <= now` (timeout) → `closed`. Lifecycle qua endpoint `/open` + `/close`. |

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
  "description": "Biểu quyết thông qua nghị quyết phân bổ ngân sách năm 2026 cho các đơn vị trực thuộc.",
  "duration_minutes": 5,
  "vote_type": "agree_disagree_abstain",
  "ballot_mode": "public_named",
  "show_result_on_projector": true,
  "show_result_on_personal_device": true,
  "sort_order": 1,
  "phase": "opened",
  "opened_at": "10:15:00 15/05/2026",
  "closed_at": null,
  "expires_at_iso": "2026-05-15T10:20:00+07:00",
  "created_at": "08:00:00 08/05/2026",
  "updated_at": "10:15:00 15/05/2026"
}
```

### <a id="5-4-nested-vote-topics-vote-responses-runtime"></a>5.4 Nested runtime — vote topics + vote responses trong phiên họp

> ⚠️ **Route bị gộp lồng nhau (commit `b8a3100`, 2026-06-09)**: `meeting-vote-responses` **không còn route phẳng nào** (`/api/meeting-vote-responses*` đã bị xóa hoàn toàn khỏi `routes/`, dù controller vẫn còn method `store/show/update/destroy/export/exportSummary` — dead code, không route tới được). Toàn bộ thao tác runtime (view topic, mở/đóng phiếu, cast vote, xem kết quả) giờ nằm dưới `/api/meetings/{meeting}/...` với Gate Policy (không phải Spatie permission).

| Method | Path | Mô tả |
|---|---|---|
| GET | `/api/meetings/{meeting}/vote-topics` | Danh sách topic trong meeting (đại biểu xem). Gate `can:viewParticipant,meeting`. |
| GET | `/api/meetings/{meeting}/vote-topics/{id}` | Chi tiết 1 topic. Gate `can:view,meetingVoteTopic`. |
| **PATCH** | `/api/meetings/{meeting}/vote-topics/{id}/open` | **Mở phiếu** — set `phase=opened` (qua `opened_at=now()`), đại biểu mới vote được. Body optional `{ "description": string\|null, "duration_minutes": int\|null }`. FE tự đếm ngược — BE **không** auto-close khi hết giờ, operator vẫn phải bấm `/close`. Gate `can:open,meetingVoteTopic`. |
| **PATCH** | `/api/meetings/{meeting}/vote-topics/{id}/close` | **Đóng phiếu** — set `closed_at=now()`, không cho vote thêm. Gate `can:close,meetingVoteTopic`. |
| **POST** | `/api/meetings/{meeting}/vote-topics/{topicId}/responses` | **Đại biểu gửi phiếu** (cast vote) — nằm **dưới topic**, không phải collection phẳng `vote-responses`. Body **chỉ cần** `{ "option": "agree\|disagree\|approve\|reject\|abstain" }` — `meeting_vote_topic_id` tự inject từ URL, `meeting_participant_id` auto-derive từ `auth()->id()` (FE **không gửi**, tránh vote hộ). Gate `can:cast,meetingVoteTopic` (participant HOẶC chair, **KHÔNG** operator). |
| GET | `/api/meetings/{meeting}/vote-responses/stats` | `{ total, agree, disagree, approve, reject, abstain }`. Query: `meeting_vote_topic_id`. Gate `can:viewParticipant,meeting` (service tự áp rule ẩn/hiện theo `show_result_on_personal_device`, xem rule 5 dưới). |
| GET | `/api/meetings/{meeting}/vote-responses` | Danh sách phiếu (chair/op dashboard). Query: `meeting_vote_topic_id`. Gate `can:operate,meeting`. |
| GET | `/api/meetings/{meeting}/vote-responses/export` | Chi tiết từng phiếu (Excel) — sensitive, giữ `operate`-only. |
| GET | `/api/meetings/{meeting}/vote-responses/export-summary` | Tổng hợp theo option (Excel). Gate `can:operate,meeting`. |

> Không còn endpoint `PATCH /vote-responses/{id}` reachable — **sửa phiếu = gọi lại `POST .../responses` lần nữa** (idempotent qua unique `(meeting_vote_topic_id, meeting_participant_id)`, service tự update phiếu cũ khi topic chưa `closed`).

### 5.5 Workflow đầy đủ (theo spec mục 2.5 + Giai đoạn C)

```
[Soạn meeting]              [Trong họp]                    [Sau khi đóng]
    │                            │                                │
    ▼                            ▼                                ▼
phase=draft  ──open()──▶  phase=opened  ──close() / timeout──▶  phase=closed
(opened_at NULL)        (opened_at NOT NULL,                  (closed_at NOT NULL
                         closed_at NULL,                       HOẶC opened_at +
                         chưa hết duration)                    duration <= now)
                                │
                                ▼
                đại biểu vote: POST .../vote-topics/{id}/responses
                        - Phải phase=opened (BE derive — block timeout)
                        - 1 phiếu / participant / topic
                        - Validate option ∈ vote_type
```

> Field `status` trong DB **đã bỏ** (2026-05-07). Resource trả `phase` (BE compute, kèm timeout) + `expires_at_iso` cho countdown FE. Filter `?status=draft|opened|closed` BE tự convert sang query derived (có check timeout). Xem [docs/changelogs/2026-05-07-meeting-vote-topic-status-removed-fe.md](../changelogs/2026-05-07-meeting-vote-topic-status-removed-fe.md).

**Rules quan trọng:**
1. Vote chỉ accept khi topic đang `phase=opened`.
2. Sau `closed`, service chặn ghi phiếu mới (422); FE block UI tương ứng.
3. **Anonymous mode** → BE trả 403 cho `GET .../vote-responses` (index) bất kể caller là ai (kể cả privileged, per spec: "không hiển thị danh tính người bỏ phiếu trong mọi màn hình nghiệp vụ thông thường"). Caller chỉ dùng được `/stats`.
4. **Public_named mode** → `GET .../vote-responses?meeting_vote_topic_id=X` chỉ trả khi caller là **privileged** (chair/operator của meeting, hoặc Spatie role `Super Admin` / `Admin` / `Thư ký họp`). Đại biểu thường → 403 (gate `can:operate,meeting` chặn từ route).
5. **Stats** (`GET .../vote-responses/stats?meeting_vote_topic_id=X`):
   - Privileged → luôn xem được (mọi mode + flag).
   - Đại biểu thường / non-privileged → service chỉ trả khi `topic.show_result_on_personal_device = true`. Else 403.
6. Tab 8 màn chiếu: hiện tại chạy under auth (operator/chair) → quyền privileged → `/stats` luôn OK. Chưa có public projector endpoint riêng (defer khi cần guest projector).
7. Cast vote: gate `can:cast,meetingVoteTopic` là participant HOẶC chair — **operator không cast được** (per policy, để tách vai trò điều hành khỏi vai trò biểu quyết).

### 5.6 FE flow điều hành phiên họp

1. Lúc soạn meeting: `POST /meeting-vote-topics` (route phẳng, nhiều lần) — tạo các chủ đề biểu quyết, `phase=draft`.
2. Trong họp, đến phần biểu quyết: `PATCH /meetings/{meeting}/vote-topics/{id}/open` → đại biểu thấy modal vote.
3. Đại biểu vote: `POST /meetings/{meeting}/vote-topics/{id}/responses` với `{ "option": "..." }`.
4. Điều hành đóng: `PATCH /meetings/{meeting}/vote-topics/{id}/close`.
5. FE hiển thị kết quả tổng hợp từ `GET /meetings/{meeting}/vote-responses/stats?meeting_vote_topic_id=X`.

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

## Luồng lưu form (FE) — trang tạo/sửa cuộc họp

Form tạo/sửa meeting chia theo section (Thông tin chung / Đại biểu / Chương trình / Biểu quyết / Tài liệu), mỗi section batch nhiều call song song để giảm round-trip. Thứ tự tổng thể vẫn giữ nguyên logic cũ:

1. **Lưu Meeting trước** — `POST /api/meetings` (create) hoặc `PATCH /api/meetings/{id}` (update). Response trả `data.id` — mọi call ở bước sau cần `meeting_id` này.
2. **Đại biểu** — `POST`/`DELETE /api/meeting-participants` (song song cho từng row add/remove). Không có endpoint sync 1-shot; FE tự diff và gọi từng row.
3. **Chương trình (agendas)** — `POST`/`PATCH`/`DELETE /api/meeting-agendas` cho từng row, sau đó `PATCH /api/meeting-agendas/reorder` 1 lần cuối để chốt `sort_order`/`parent_id`.
4. **Biểu quyết (vote topics)** — `POST`/`PATCH`/`DELETE /api/meeting-vote-topics` (route phẳng, giai đoạn soạn — chỉ áp dụng cho topic còn `phase=draft`), rồi `PATCH /api/meeting-vote-topics/reorder`.
5. **Tài liệu (documents)** — `POST`/`PATCH` (multipart/form-data) / `DELETE /api/meeting-documents`, rồi `PATCH /api/meeting-documents/reorder`.

**Lý do thứ tự này quan trọng**: agenda phải tồn tại trước khi vote topic / document gắn `meeting_agenda_id` vào nó.

**Edit mode**: tải toàn bộ data hiện tại khi `onMounted` — `GET /api/meetings/{id}` (đã kèm `participants`), cộng thêm `GET /api/meeting-agendas?meeting_id=`, `GET /api/meeting-vote-topics?meeting_id=`, `GET /api/meeting-documents?meeting_id=` (đều `sort_by=sort_order&sort_order=asc&limit=100`) để fill từng section.

> Vote topic chỉ sửa/xóa được khi còn `phase=draft` (chưa từng `/open`) — service nên reject nếu topic đã `opened`/`closed`/có response.

---

## Tóm tắt cho FE

1. **Trang tạo cuộc họp** — form đơn giản, chọn `meeting_type_id`/`meeting_location_id` từ dropdown `/api/public/meeting-types/options`, `/api/public/meeting-locations/options` (đã đổi từ `public-options` → `/public/{resource}/options`, xem mục 1.1 ở trên).
2. **Trang chương trình họp** — list theo `meeting_id`, hỗ trợ kéo thả qua `/reorder`.
3. **Trang tài liệu** — list theo `meeting_id`, upload `multipart/form-data` field `file` (1 tài liệu = 1 file). Hiển thị `file_url` để FE tải. Cuộc họp nhiều file → tạo nhiều record document.
4. **Trang đại biểu tham dự** — chọn đại biểu qua dropdown từ `/api/meeting-attendees` (đã filter theo org). Snapshot tự động khi store.
5. **Publish cuộc họp** — gọi `PATCH /meetings/{id}/status` với `{"status":"published"}`. BE tự gửi giấy mời (FCM/email) cho participants. FE chỉ cần chờ response và hiển thị "Đã gửi giấy mời".
6. **Runtime trong phiên họp** (điểm danh, thảo luận/chất vấn, biểu quyết, ghi chú cá nhân, respond invitation) — toàn bộ đã chuyển sang route nested `/api/meetings/{meeting}/...` (commit `b8a3100`, 2026-06-09), xem [docs/api/meeting-room-fe.md](./meeting-room-fe.md) cho danh sách đầy đủ theo tab.
