# Changelog – TaskAssignment Corrections

## 2026-04-21 – Multi-department users + Report confirm/lock + Remove task-level confirm + `done` is internal

**Breaking changes cho FE.** Đọc kỹ mục 8 (flow chuẩn) + mục 9 (migration guide).

---

## 1. `PATCH /api/task-assignment-item-reports/{id}/confirm` – **NEW**

Xác nhận báo cáo và khóa (1-step — confirm đồng thời lock).

**Request:**
```json
{
  "confirm_note": "Đạt quy định"   // optional
}
```

**Response 200** — sau khi confirm, nếu điều kiện đạt thì task **tự động chuyển `done`** (xem §6):
```json
{
  "success": true,
  "message": "Đã xác nhận báo cáo.",
  "data": {
    "id": 1,
    "manager_confirmed": true,
    "manager_confirmed_by": 42,
    "manager_confirmed_at": "2026-04-21T10:30:00.000000Z",
    "manager_confirm_note": "Đạt quy định",
    "is_locked": true,
    "locked_at": "2026-04-21T10:30:00.000000Z",
    "locked_by": 42,
    "item": { "processing_status": "done", "completed_at": "..." },
    "...": "..."
  }
}
```

**Response 422 — đã khóa:**
```json
{ "success": false, "message": "Báo cáo đã khóa, không thể xác nhận lại." }
```

**Permission:** `task-assignment-item-reports.confirm` (Super Admin + Admin auto có).

---

## 2. `PATCH /api/task-assignment-item-reports/{id}` và `DELETE .../{id}` – reject khi locked

Report đã `is_locked=true` → trả **422**:
```json
{ "success": false, "message": "Báo cáo đã khóa, không thể sửa." }
// hoặc "Báo cáo đã khóa, không thể xóa."
```

FE nên check `is_locked` trước khi hiển thị nút Sửa/Xóa.

---

## 3. Report resource shape – **thêm 7 field**

Mọi endpoint trả report (`index`, `show`, `store`, `update`, `confirm`) thêm:

| Field | Type | Nullable | Ghi chú |
|---|---|---|---|
| `manager_confirmed` | boolean | – | Default `false` |
| `manager_confirmed_by` | integer | ✅ | Null khi chưa confirm |
| `manager_confirmed_at` | datetime ISO | ✅ | |
| `manager_confirm_note` | string | ✅ | |
| `is_locked` | boolean | – | Default `false` |
| `locked_at` | datetime ISO | ✅ | |
| `locked_by` | integer | ✅ | |

---

## 4. Task-assignment departments users – multi-department

### 4.1 Delta sync (không destroy-all)

`POST /api/task-assignment-departments/{id}/sync-users` — nay **không xóa** user cũ ở phòng ban khác. Chỉ add/remove delta theo payload.

### 4.2 User có thể thuộc nhiều phòng ban

Cùng 1 user có thể xuất hiện ở nhiều department (cùng organization).

### 4.3 Field mới: `is_primary`

Record `task_assignment_users` thêm cờ `is_primary` (boolean).

`GET /api/task-assignment-departments/{id}/users` trả field này trong mỗi record.

**Rule tự động:**
- User lần đầu add vào dept → auto `is_primary=true` nếu user chưa có record primary nào trong cùng org.
- User đã có primary → record mới = `is_primary=false`.
- Remove primary → auto promote 1 record khác thành primary.

### 4.4 `setPrimary` (chưa expose endpoint)

BE có method `TaskAssignmentDepartmentService::setPrimary($userId, $deptId)`. Khi FE cần endpoint → liên hệ BE.

---

## 5. Task-level confirm — **REMOVED**

### 5.1 Endpoint bị gỡ

**`PATCH /api/task-assignment-items/{id}/confirm-done`** — **KHÔNG CÒN**. Trả 404.

FE đang gọi endpoint này → **phải thay bằng flow report-level** (xem §8).

### 5.2 Field bị gỡ khỏi response task

Item resource **không còn** 2 field:
- `confirmed_by`
- `confirmed_at`

Nếu FE đang dùng 2 field này để hiển thị "đã xác nhận" → chuyển sang dùng field trên report: `manager_confirmed`, `manager_confirmed_at`, `manager_confirmed_by`.

### 5.3 Status `reported` — **GIỮ**; `overdue` — **REMOVED** (chuyển thành flag); `done` — **INTERNAL**

Enum `processing_status` còn **6 giá trị**:
- `todo` (chưa bắt đầu)
- `in_progress` (đang làm)
- `reported` (assignee tự báo "tôi xong, mời quản lý duyệt")
- `done` ⚠️ INTERNAL — chỉ BE set qua endpoint `mark-done` (xem §6)
- `paused`
- `cancelled`

**`overdue` đã bị gỡ khỏi enum.** Thay bằng 2 computed flag trên Resource (xem §6.5):
- `is_overdue` (bool) = trễ hạn (chưa hoàn thành + quá `end_at`)
- `is_late_completed` (bool) = quá hạn (đã `done` + `completed_at > end_at`)

**Quyền update `processing_status`:**

| Role | Được set | Không được set |
|---|---|---|
| Assignee + Manager | `todo, in_progress, reported, paused, cancelled` (5) | `done` |
| Hệ thống (BE auto) | `done` (qua `/mark-done`) | – |

**Semantic:**
- `reported` = assignee đánh dấu "phần việc tôi đã xong, mời quản lý kiểm tra".
- `done` = task hoàn thành thực sự (manager đã đánh dấu hoàn thành).

### 5.4 Permission bị gỡ

`task-assignment-items.confirmDone` — **KHÔNG CÒN**.

CASL ability `$can('confirmDone', 'TaskAssignmentItem')` → luôn false.

---

## 6. `done` — INTERNAL status, qua endpoint `mark-done`

Status `done` **chỉ được set qua endpoint mới `PATCH /api/task-assignment-items/{id}/mark-done`** (manager bấm). KHÔNG có auto-close khi confirm report.

### 6.1 `PATCH /api/task-assignment-items/{id}/mark-done` — **NEW**

**Request:** không body.

**Response 200:**
```json
{
  "success": true,
  "message": "Đã đánh dấu hoàn thành.",
  "data": {
    "id": 1,
    "processing_status": "done",
    "completion_percent": 100,
    "completed_at": "21/04/2026 ...",
    "...": "..."
  }
}
```

**Response 422 — không đủ điều kiện:**
```json
{ "success": false, "message": "Phải có ít nhất 1 báo cáo đã được xác nhận trước khi đánh dấu hoàn thành." }
// hoặc
{ "success": false, "message": "Công việc đã đóng, không thể đánh dấu lại." }
```

**Validation server-side:**
- Task hiện tại **không** ở `done` hoặc `cancelled` (tránh đóng lại).
- Có **ít nhất 1 báo cáo** với `is_locked=true` (manager đã review ít nhất 1 báo cáo).

**Khi pass validation, BE tự set:**
- `processing_status = done`
- `completion_percent = 100` (sync spec §4.3)
- `completed_at = now()`
- Fire event `TaskConfirmed` → trigger notification sang assignees.

**Permission:** `task-assignment-items.markDone` (Super Admin + Admin + Quản trị auto).

### 6.2 Tương tác với `assignment_status`

Khi assignee **submit báo cáo** qua `POST /api/task-assignment-item-reports`, BE **auto set** record reporter: `assignment_status=done`, `completed_at=now()`.

Đây là metadata "phần việc cá nhân xong", không tự động đóng task. Manager phải bấm `/mark-done` riêng.

### 6.3 Endpoints reject `done`

Các endpoint user gửi `processing_status` **reject `done`** → **422** (giá trị `overdue` cũng reject vì không còn trong enum):

- `POST /api/task-assignment-items`
- `PATCH /api/task-assignment-items/{id}`
- `PATCH /api/task-assignment-items/{id}/change-status`
- `PATCH /api/task-assignment-items/{id}/update-progress`
- `POST /api/task-assignment-items/bulk-update-status`

### 6.4 FE dropdown

Dropdown cho user (assignee + manager) hiện **5 option**:
`todo, in_progress, reported, paused, cancelled`.

**KHÔNG hiện** `done` (internal). Status này chỉ hiển thị **read-only** ở badge trạng thái.

Khái niệm "trễ hạn"/"quá hạn" hiển thị qua **badge phụ** dựa vào 2 computed flag (§6.5).

### 6.5 Tách 2 khái niệm: Task workflow vs Report timing

Theo spec §5.2 + §7.1 (tỷ lệ hoàn thành đúng hạn/quá hạn là METRIC), concept timing **thuộc về báo cáo**, không phải task. Task chỉ keep workflow status.

**Task-level — chỉ 1 computed flag:**

| Field | Logic | Nghĩa |
|---|---|---|
| `is_overdue` | `deadline_type='has_deadline'` AND `end_at < now()` AND status NOT IN `[done, cancelled]` | **"Đang trễ hạn"** — task chưa hoàn thành mà đã quá `end_at` |

**Report-level — computed `timing_status`:**

| Field | Values | Logic |
|---|---|---|
| `timing_status` | `'on_time'` | `report.completed_at ≤ task.end_at` |
| | `'late'` | `report.completed_at > task.end_at` |
| | `null` | thiếu `completed_at` hoặc task `no_deadline` |

**Response example — Item:**
```json
{
  "id": 1,
  "processing_status": "in_progress",
  "end_at": "2026-04-20",
  "is_overdue": true
}
```

**Response example — Report:**
```json
{
  "id": 1,
  "completed_at": "25/04/2026 10:30",
  "timing_status": "late",
  "manager_confirmed": true,
  ...
}
```

**FE rendering:**
- Task list: check `is_overdue === true` → badge phụ "Đang trễ hạn" (cảnh báo).
- Report list: check `timing_status` để hiển thị "Đúng hạn" (xanh) / "Trễ hạn" (cam).
- KHÔNG còn `is_late_completed` — muốn biết task hoàn thành trễ hay không, xem `timing_status` của report cuối cùng.

### 6.6 Stats đơn giản hóa

**Bỏ** 2 field khỏi response stats:
- `on_time_count` — ĐÃ GỠ
- `overdue_done_count` — ĐÃ GỠ

Stats còn:
- `overdue` = số task đang trễ hạn (chưa done).
- Các counter theo `processing_status` (`todo`, `in_progress`, `done`, ...).

Nếu FE cần KPI "tỷ lệ báo cáo đúng hạn/trễ hạn" → tính từ report list + `timing_status`.

### 6.7 Filter index `is_overdue`

`GET /api/task-assignment-items?is_overdue=1` → chỉ trả task đang trễ hạn (chưa hoàn thành + quá `end_at`).

`is_late_completed` filter **ĐÃ GỠ**. Muốn lấy report trễ hạn → query report list + filter `timing_status=late` ở FE, hoặc thêm endpoint riêng sau.

**FE migration breaking:**
- Code cũ `?processing_status=overdue` → trả empty (status không còn).
- Code cũ `?is_late_completed=1` → trả empty (filter không còn).

---

## 7. Permission mới

| Name | Guard | Resource label | Action label | Roles |
|---|---|---|---|---|
| `task-assignment-item-reports.confirm` | web | Báo cáo công việc | Xác nhận | Super Admin + Admin |
| `task-assignment-items.markDone` | web | Công việc | Đánh dấu hoàn thành | Super Admin + Admin + Quản trị |

CASL:
```js
$can('confirm', 'TaskAssignmentItemReport')
$can('markDone', 'TaskAssignmentItem')
```

---

## 8. Flow chuẩn (E2E) sau thay đổi

### Giai đoạn 1 – Giao việc (quản lý)
1. `POST /api/task-assignment-documents` → tạo văn bản `draft`.
2. `POST /api/task-assignment-items` → tạo công việc (`processing_status: todo` mặc định).
3. `PATCH /api/task-assignment-documents/{id}/change-status` → `issued`.

### Giai đoạn 2 – Thực hiện (assignee)
4. Assignee nhận view màn "Công việc của tôi" qua `GET /api/task-assignment-items?user_id=me`.
5. Assignee cập nhật tiến độ: `PATCH /api/task-assignment-items/{id}/update-progress`
   - Body: `{ processing_status: "in_progress", completion_percent: 50 }`
   - **KHÔNG được** set `processing_status: "done"` — 422 (done là internal).
   - Có thể set `reported` khi xong: `{ processing_status: "reported", completion_percent: 100 }`.

### Giai đoạn 3 – Submit báo cáo (assignee)
6. Assignee submit báo cáo: `POST /api/task-assignment-item-reports`
   - Body: `{ task_assignment_item_id, report_document_content, report_document_number, ... }`
   - + files đính kèm.
   - **BE auto** set `task_assignment_item_user.assignment_status='done'` cho reporter (signal "phần việc của mình đã xong"). FE **không cần** gọi endpoint nào để đổi trạng thái phân công.
   - Optional: assignee có thể đồng thời set task `processing_status=reported` qua endpoint update-progress (bước 5).
7. Assignee có thể sửa nhiều lần trước khi được confirm: `PATCH /api/task-assignment-item-reports/{id}`.

### Giai đoạn 4 – Duyệt báo cáo (quản lý)
8. Manager review báo cáo → `PATCH /api/task-assignment-item-reports/{id}/confirm`
   - Body: `{ confirm_note: "..." }` (optional).
   - BE set: `manager_confirmed=true`, `is_locked=true` cho **report đó**.
   - **KHÔNG tự động đóng task** — task vẫn ở status hiện tại.
9. Sau khi confirmed, report **không sửa/xóa** được nữa.

### Giai đoạn 5 –  Đánh dấu hoàn thành task (manager)
10. Manager đánh giá đã xong xuôi → bấm "Đánh dấu hoàn thành" → `PATCH /api/task-assignment-items/{id}/mark-done`.
    - BE check: task chưa done/cancelled + ≥1 report đã locked → 422 nếu không đạt.
    - BE set: `processing_status=done`, `completion_percent=100`, `completed_at=now()`.
    - Fire event `TaskConfirmed`.
11. Task `processing_status=done` → FE hiển thị badge "Hoàn thành".
12. Sau khi done: cập nhật tiến độ / submit report mới → tùy phía BE handle (chưa enforce).

### Các flow phụ
- **Đổi trạng thái khác** (`in_progress`, `paused`, `cancelled`…) → `PATCH .../change-status` hoặc `.../update-progress`. User được phép, KHÔNG truyền `done`.
- **Assign user nhiều phòng ban**: Gọi `sync-users` nhiều lần với department khác nhau (§4).
- **Manager override đóng task sớm** (không qua confirm report): chưa có endpoint, cần V2 nếu business yêu cầu.

---

## 9. FE migration guide (action items)

### 9.1 Gỡ khỏi code FE
- Gọi `PATCH .../confirm-done` → **xóa**.
- Gọi `PATCH /items/{id}/...` với `processing_status: "done"` → **xóa** (sẽ trả 422). Task tự `done` qua report-confirm flow.
- Đọc `item.confirmed_by` / `item.confirmed_at` → **xóa** hoặc thay bằng `report.manager_confirmed_by` / `...at`.
- CASL `$can('confirmDone', ...)` → **xóa**.

### 9.1b Status `reported` — vẫn dùng được

Assignee có thể đặt `processing_status = "reported"` qua `PATCH /items/{id}/update-progress` để báo "tôi xong, mời quản lý duyệt". UI giữ nút "Đánh dấu đã báo cáo".

Khác biệt với flow cũ:
- Trước: chỉ assignee đổi `reported` + manager bấm `confirmDone` → done.
- Giờ: assignee đổi `reported` (optional) + submit report → manager confirm report → BE auto done.

### 9.2 Thêm vào code FE
- Màn chi tiết báo cáo:
  - Nút **"Xác nhận"** → `PATCH /reports/{id}/confirm`. Show/hide theo CASL `$can('confirm', 'TaskAssignmentItemReport')` và `is_locked === false`.
  - Badge **"Đã xác nhận"** / **"Đã khóa"** — đọc `manager_confirmed` / `is_locked`.
  - Nút **Sửa/Xóa** report disabled khi `is_locked=true`.
- Màn chi tiết task:
  - Nút **"Đánh dấu hoàn thành"** → `PATCH /api/task-assignment-items/{id}/mark-done`. Show theo CASL `$can('markDone', 'TaskAssignmentItem')` và task status NOT IN `[done, cancelled]`. Disable nếu chưa có report locked (FE check sơ bộ, BE re-validate).
- Dropdown `processing_status`:
  - Render **5 option** (loại `done` và `overdue`): `todo, in_progress, reported, paused, cancelled`.
  - Khi task ở `done` hoặc `overdue` → dropdown disabled, chỉ hiển thị badge.
- Panel phòng ban (multi-dept):
  - Mỗi user có thể xuất hiện ở nhiều phòng ban.
  - Hiển thị `is_primary` badge cho phòng ban chính.
  - (Nếu FE cần đổi primary) → chờ BE expose endpoint.

### 9.3 Enum `TaskReminderStatusEnum` breaking

| Trước (stale) | Giờ |
|---|---|
| `sent` | `fired` |
| `failed` | `cancelled` |

Nếu FE đang check status reminder → update map. Giá trị `failed` không còn.

---

## 10. Quick summary (cho PM/QA)

| Thay đổi | Tóm tắt |
|---|---|
| Multi-dept | User có thể thuộc nhiều phòng, có 1 phòng "chính" (`is_primary`). |
| Report confirm/lock | Manager duyệt **từng báo cáo** → lock riêng báo cáo đó. |
| Task confirmDone | **Xóa**. Task tự `done` khi báo cáo cuối được duyệt + không còn assignment mở. |
| Status `done` | **Không cho user chọn** trong dropdown. BE validate reject. BE auto set. |
| Status `reported` | **Xóa**. Data cũ migrate → `in_progress`. |

---

## Related documents

- **Correction spec:** [docs/superpowers/specs/2026-04-21-task-assignment-corrections.md](../superpowers/specs/2026-04-21-task-assignment-corrections.md)
- **Implementation plan:** [docs/superpowers/plans/2026-04-21-task-assignment-corrections.md](../superpowers/plans/2026-04-21-task-assignment-corrections.md)
- **Module spec gốc:** [phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md](../../phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md) (§9.3.D state machine, §8.1 flow)
