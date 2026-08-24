# API Công việc (Task Assignment Item)

> Cập nhật lần cuối: 15:57:37 15/07/2026

Quản lý công việc trong hệ thống giao việc liên phòng ban: thống kê, danh sách (với bộ lọc nâng cao), chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, cập nhật tiến độ, luồng duyệt hoàn thành (mark-done/reject/reopen), timeline, xuất Excel. Mỗi công việc có thể giao cho nhiều phòng ban và nhiều người dùng (quan hệ nhiều-nhiều).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-items`

**Enum values:**
- `processing_status`: `todo` | `in_progress` | `pending_approval` | `done` | `paused` | `cancelled`
  - `overdue` **KHÔNG** phải giá trị enum. "Trễ hạn" nay là field tính toán `is_overdue` (boolean) trên response, không phải trạng thái lưu DB.
  - `pending_approval` (Chờ duyệt) và `done` (Hoàn thành) **không đặt tay được** qua các endpoint đổi trạng thái trực tiếp (`{id}/status`, `bulk-status`, `{id}/progress`, `POST`/`PUT`/`PATCH {id}` khi tạo mới) — xem chi tiết ở mục [Business Logic](#business-logic).
- `deadline_type`: `has_deadline` | `no_deadline`
- `priority`: `low` | `medium` | `high` | `urgent`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats` |
| **Auth** | Bắt buộc (permission `task-assignment-items.stats` hoặc `presentation.index`). |
| **Query** | `search`, `processing_status`, `priority`, `deadline_type`, `task_assignment_document_id`, `task_assignment_item_type_id`, `department_id`, `assignee_id` (alias `user_id`), `assigner_id` (match `assigned_by` hoặc `created_by`), `assignment_role` (main \| support), `assignment_status` (assigned \| done), `start_from`/`start_to`, `end_from`/`end_to`, `from_date`/`to_date` (lọc theo `created_at`). |
| **Response** | `{ "total": 18, "todo": 5, "in_progress": 8, "pending_approval": 2, "done": 3, "paused": 0, "cancelled": 1, "timing_stats": { "upcoming": 6, "overdue": 1, "late": 1, "early": 1, "on_time": 1, "cancelled": 1 } }` — `total` (sau lọc), 6 key trạng thái cộng lại = `total`. `timing_stats` là nhóm thống kê theo tiến độ thời gian (không phải theo `processing_status`): `overdue` = active + quá `end_at`; `late`/`early`/`on_time` chỉ tính trên task `done`. |

---

## Danh sách công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc (`viewAny` — permission `task-assignment-items.index`). |
| **Query** | `search` (tên công việc), `processing_status`, `priority`, `deadline_type`, `department_id` (ID phòng ban được giao), `assignee_id`/`user_id` (ID người được giao), `assigner_id` (match `assigned_by` hoặc `created_by`), `assignment_role` (main \| support), `assignment_status` (assigned \| done), `task_assignment_document_id`, `task_assignment_item_type_id`, `start_from`/`start_to` (YYYY-MM-DD — lọc `start_at`), `end_from`/`end_to` (YYYY-MM-DD — lọc `end_at`), `from_date`/`to_date` (YYYY-MM-DD — lọc `created_at`), `sort_by` (id \| name \| start_at \| end_at \| completion_percent \| priority \| created_at \| updated_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection (`ItemCollection`); mỗi item kèm `document`, `item_type`, `departments`, `users`. |

> Filter nâng cao chưa liệt kê trong PHPDoc controller nhưng tồn tại trong `TaskAssignmentItem::scopeFilter()`: `is_overdue=1` (chỉ trả task chưa `done`/`cancelled` mà đã quá `end_at`), `timing_status` (`upcoming`\|`overdue`\|`late`\|`early`\|`on_time`\|`cancelled`).

---

## Chi tiết công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc (`view` — permission `task-assignment-items.show`). |
| **UrlParam** | `id` — ID công việc. |
| **Response** | Object công việc (`ItemResource`), kèm `document`, `item_type`, `departments`, `users`, `reminders`, các trường báo cáo/duyệt (`rejection_reason`, `reported_at`, `reported_by`, `approved_by`). |

---

## Tạo công việc

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc (`create` — permission `task-assignment-items.store`). |
| **Status code** | 201 / 422 |

**Body** — có 2 cách chỉ định người thực hiện, chọn **một trong hai**: `users[]` (khai chi tiết từng người) hoặc `departments[]` (chỉ khai phòng ban, BE tự lấy người đại diện `is_representative=true` của từng phòng làm người thực hiện `assignment_role=main`).

**Cách 1 — `users[]`:**
```json
{
  "task_assignment_document_id": 10,
  "name": "Soạn thảo báo cáo tình hình nhân sự tháng 4",
  "description": "Yêu cầu tổng hợp số liệu...",
  "task_assignment_item_type_id": 1,
  "deadline_type": "has_deadline",
  "start_at": "2026-04-10 08:00:00",
  "end_at": "2026-04-30 17:00:00",
  "processing_status": "todo",
  "completion_percent": 0,
  "priority": "medium",
  "assigned_by": 1,
  "users": [
    {
      "user_id": 5,
      "department_id": 3,
      "department_role": "main",
      "assignment_role": "main"
    },
    {
      "user_id": 8,
      "department_id": 3,
      "department_role": "main",
      "assignment_role": "support"
    }
  ]
}
```

**Cách 2 — `departments[]` (rút gọn, tự resolve người đại diện):**
```json
{
  "task_assignment_document_id": 10,
  "name": "Soạn thảo báo cáo tình hình nhân sự tháng 4",
  "deadline_type": "has_deadline",
  "end_at": "2026-04-30 17:00:00",
  "assigned_by": 1,
  "departments": [
    { "department_id": 3, "department_role": "main" },
    { "department_id": 7, "department_role": "cooperate" }
  ]
}
```
> Mỗi phòng ban trong `departments[]` phải đã có người đại diện active (`task_assignment_employee_department.is_representative = true`, `status = active`), nếu không sẽ 422 `"Phòng ban ID {id} chưa có người đại diện."`. Nếu request gửi cả `users` lẫn `departments`, BE **ưu tiên `users`** và bỏ qua `departments`.

**Required**: `task_assignment_document_id`, `name`, `deadline_type`, `assigned_by`, và **một trong** `users` (mảng tối thiểu 1 phần tử) hoặc `departments` (mảng tối thiểu 1 phần tử).

**Field detail**:
- `assigned_by` (int, **required**) — ID người giao việc, phải là nhân viên module Task active (`task_assignment_employees.status = active`) trong tổ chức hiện tại.
- `users[].user_id` (int, required) — ID user trong `users` tổng. **Phải là nhân viên module Task active** **VÀ phải thuộc đúng `department_id`** đang gán (có bản ghi trong `task_assignment_employee_department`, hồ sơ nhân viên active).
- `users[].department_id` (int, required) — ID phòng ban đang gán user vào.
- `users[].department_role` (string, required) — `main` (chủ trì) \| `cooperate` (phối hợp). Vai trò của PHÒNG BAN trong công việc.
- `users[].assignment_role` (string, required) — `main` (chủ trì) \| `support` (hỗ trợ). Vai trò của RIÊNG user trong scope phòng ban đó.
- `departments[].department_id` (int, required) — ID phòng ban, phải tồn tại và có người đại diện active.
- `departments[].department_role` (string, required) — `main` \| `cooperate`.
- `end_at` bắt buộc nếu `deadline_type = has_deadline`.
- `processing_status` (optional) — chỉ nhận `todo` \| `in_progress` \| `paused` \| `cancelled`. **Không nhận `done`/`pending_approval`** khi tạo mới (422 nếu gửi).
- `attachments[]` (optional) — tối đa 10 tệp, mỗi tệp tối đa 20MB.

> **Lưu ý business** (không phải giới hạn kỹ thuật): schema `task_assignment_item_user` cho phép 1 task có nhiều `department_id` khác nhau trong cùng `users[]` (không có rule validate ép "cùng 1 dept"). Tuy nhiên nghiệp vụ khuyến nghị **tách 1 task cho 1 phòng ban** để cho phép edit độc lập từng dòng (đổi tên/hạn của dept A không ảnh hưởng dept B) — xem [Multi-department — Pattern FE duplicate](#multi-department--pattern-fe-duplicate).

**Validate gate 422** (nếu user chưa đăng ký nhân viên hoặc không thuộc dept):
```json
{
  "success": false,
  "code": "VALIDATION_ERROR",
  "message": "...",
  "errors": {
    "users.0": [
      "User ID 5 không phải nhân viên module Task hoặc đã bị vô hiệu hóa. Vui lòng đăng ký nhân viên trước."
    ],
    "users.1": [
      "User ID 8 không thuộc phòng ban ID 3 trong tổ chức này."
    ]
  }
}
```

**Response 201**: object công việc (`ItemResource`, kèm `users[]`/`departments[]`) + `"message": "Công việc đã được tạo thành công!"`.

---

## Cập nhật công việc

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc (`update` — permission `task-assignment-items.update` **và** người dùng phải là người giao (`assigned_by`) hoặc người được giao của task). Nếu body có `processing_status` = `paused`/`cancelled`, chỉ người giao (`assigned_by`) mới được phép. |
| **Body** | Mọi field optional (partial update, `sometimes`). `users[]` hoặc `departments[]` nếu có **sẽ ghi đè danh sách hiện tại** (`sync()` Eloquent — auto detach user cũ không có trong list, attach user mới). `processing_status` ở endpoint này chấp nhận **toàn bộ 6 giá trị enum** kể cả `done`/`pending_approval` (khác với `{id}/status`/`bulk-status` — hai endpoint đó từ chối 2 giá trị này). Không khuyến nghị set `done`/`pending_approval` qua update; nên dùng `mark-done`/luồng báo cáo. |
| **Validate** | Giống Tạo — `users[*].user_id` qua 2 gate (employee active + in dept); `departments[*].department_id` phải có người đại diện active. |
| **Response** | Object công việc đã cập nhật (kèm `users[]`). |

**Lưu ý sync behavior**: nếu gửi `users: []` (mảng rỗng) → BE validate fail (`min:1`). Muốn giữ nguyên danh sách hiện tại thì không gửi key `users`/`departments`.

---

## Multi-department — Pattern FE duplicate

**Yêu cầu nghiệp vụ**: 1 công việc giao cho nhiều phòng ban → sinh ra nhiều dòng công việc cùng tên (mỗi phòng 1 task), edit độc lập từng dòng.

**Cách BE xử lý**: BE **KHÔNG có batch endpoint** tự nhân bản. FE có thể gửi 1 POST với `users[]` chứa nhiều `department_id` khác nhau (BE không chặn), nhưng khi đó tất cả các dept sẽ **nằm chung 1 task** → edit tên/hạn sẽ ảnh hưởng toàn bộ dept trong task đó. Để giữ khả năng edit độc lập từng dept, **FE chủ động loop submit nhiều POST**, mỗi call 1 dept (dùng `departments[]` rút gọn hoặc `users[]` chi tiết).

### Flow FE đề xuất

```
[Form "Thêm công việc đa phòng ban"]

1. User nhập THÔNG TIN CHUNG 1 lần:
   - name, description, document_id, deadline_type, start_at, end_at,
     priority, item_type_id, attachments[], assigned_by

2. User thêm N hàng "Phân công" (mỗi hàng):
   { department_id, user_id, department_role, assignment_role }
   → cho phép nhiều user trong cùng 1 dept
   → cho phép nhiều dept khác nhau

3. FE GROUP các hàng theo department_id:
   group_3 = [{user 5, main, main}, {user 8, main, support}]
   group_7 = [{user 12, cooperate, main}]

4. FE LOOP N call POST (sequential hoặc parallel):
   POST /api/task-assignment-items   { ...common, users: group_3 }
   POST /api/task-assignment-items   { ...common, users: group_7 }

   Hoặc dùng departments[] rút gọn (mỗi call 1 phần tử) nếu chỉ cần gán người đại diện:
   POST /api/task-assignment-items   { ...common, departments: [{department_id: 3, department_role: "main"}] }
   POST /api/task-assignment-items   { ...common, departments: [{department_id: 7, department_role: "cooperate"}] }

5. Kết quả DB: 2 row trong `task_assignment_items` cùng tên, khác ID,
   mỗi row gắn 1 dept. List index sẽ hiện 2 dòng riêng.

6. Admin edit từng row độc lập:
   PATCH /api/task-assignment-items/{id}   { name?, dates?, users? }
   → chỉ ảnh hưởng task đó, không lan sang task của dept khác.
```

### Lưu ý implementation FE

- **Atomicity**: BE không bọc transaction xuyên-task. Nếu call thứ K fail (vd validate user) → các task 1..K-1 đã tạo vẫn tồn tại. FE nên:
  - **Cách A**: validate trước client-side đầy đủ (employee active + thuộc dept, hoặc phòng ban có người đại diện nếu dùng `departments[]`) trước khi loop POST.
  - **Cách B**: nếu fail giữa chừng, hiện modal "Đã tạo K task, còn N-K fail. Bạn muốn rollback (xóa K đã tạo) hay giữ?".

- **Attachment file upload**: nếu form có `attachments[]`, FE phải upload **cho từng POST** (mỗi task có copy attachment riêng). Cách tối ưu:
  - Upload file 1 lần lên `/api/media` (nếu có endpoint cache media độc lập), nhận `media_id`, gửi `media_id` reuse cho N POST. **HIỆN TẠI BE chưa có endpoint này** → FE phải upload N lần (multipart cho mỗi POST).

- **Performance**: N call POST có thể song song (`Promise.all`) — không có dependency giữa các task. Nhưng nếu N > 5, nên loading state "Đang tạo {k}/{n}" để user thấy progress.

- **UX gợi ý**: cuối flow show thông báo "Đã tạo {N} công việc cho {M} phòng ban" + link sang list (filter `task_assignment_document_id`) để admin xem ngay.

### Tại sao không 1 endpoint batch?

- Schema pivot `task_assignment_item_user` đã hỗ trợ multi-dept trong 1 task (không có rule chặn khác dept trong cùng `users[]`) → BE kỹ thuật có thể accept multi-dept trong 1 task.
- **Lý do dùng FE duplicate**: nghiệp vụ yêu cầu **edit độc lập từng dept**. Nếu lưu chung 1 task, edit name/dates sẽ lan sang tất cả dept. Tách thành N task → edit không ảnh hưởng nhau.
- BE không refactor thêm endpoint batch để tránh phá BC + giảm complexity.

---

## Xóa công việc

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc (`delete` — permission `task-assignment-items.destroy` **và** chỉ người giao (`assigned_by`) mới xóa được). |
| **Response** | `{ "message": "Công việc đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-items/bulk-delete` |
| **Auth** | Bắt buộc (`bulkDestroy` — permission `task-assignment-items.bulkDestroy`). |
| **Body** | `ids` (array, required, min 1) — danh sách ID công việc. |
| **Response** | `{ "message": "Đã xóa thành công các công việc được chọn!" }`. |

> Nếu user không có vai trò `Super Admin`, service chặn xóa các task không phải do chính user đó giao (`assigned_by`) — trả lỗi `RuntimeException` (500) *"Bạn chỉ được xóa công việc do chính bạn giao."* nếu có bất kỳ ID nào không thuộc quyền.

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/bulk-status` |
| **Auth** | Bắt buộc (`bulkUpdateStatus` — permission `task-assignment-items.bulkUpdateStatus`). |
| **Body** | `ids` (array, required, min 1), `processing_status` (required). |
| **Response** | `{ "message": "Cập nhật trạng thái hàng loạt thành công!" }`. |

> `processing_status` **chỉ nhận** `todo` \| `in_progress` \| `paused` \| `cancelled`. Gửi `done` hoặc `pending_approval` → **422** (không đặt tay được — chỉ đạt qua luồng báo cáo/duyệt, xem [Business Logic](#business-logic)). Gửi `overdue` cũng 422 (không phải giá trị enum).
> Nếu set `paused`/`cancelled` và user không phải `Super Admin`, service chặn nếu có task không do chính user đó giao (`assigned_by`) — lỗi *"Bạn chỉ được tạm dừng hoặc hủy công việc do chính bạn giao."*.

---

## Đổi trạng thái công việc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/status` |
| **Auth** | Bắt buộc (`changeStatus` — permission `task-assignment-items.changeStatus`/`.pause`/`.cancel` **và** người dùng là người giao hoặc người được giao; riêng set `paused`/`cancelled` chỉ người giao mới được phép). |
| **Body** | `processing_status` (required). |
| **Response** | `{ "message": "Đổi trạng thái thành công!", "data": ItemResource }`. |

> `processing_status` **chỉ nhận** `todo` \| `in_progress` \| `paused` \| `cancelled`. Gửi `done` hoặc `pending_approval` → **422**. Muốn hoàn thành, dùng `PATCH /{id}/mark-done` (sau khi task đã ở `pending_approval` qua báo cáo).

---

## Mở lại công việc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/reopen` |
| **Auth** | Bắt buộc (`changeStatus` — permission `task-assignment-items.changeStatus`/`.pause`/`.cancel`, người giao hoặc người được giao). |
| **UrlParam** | `id` — ID công việc. |
| **Response** | `{ "message": "Đã mở lại công việc!", "data": ItemResource }`. |

Mở lại công việc từ trạng thái đóng (`done`/`cancelled`/`paused`/`pending_approval`). Trạng thái mới **tự động suy từ `completion_percent` hiện tại**, không nhận body:
- `completion_percent = 0` → `todo`
- `1-99%` → `in_progress`
- `100%` → `pending_approval` (không tự nhảy thẳng về `done` — vẫn cần manager `mark-done` lại)

---

## Đánh dấu hoàn thành (mark-done)

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/mark-done` |
| **Auth** | Bắt buộc (`markDone` — permission `task-assignment-items.markDone` **và chỉ người giao** (`assigned_by`) mới thực hiện được; nhân viên thực hiện task không tự duyệt được). |
| **UrlParam** | `id` — ID công việc. |
| **Điều kiện** | Task phải đang ở `pending_approval` (đã báo cáo 100%, chờ duyệt). |
| **Response 200** | `{ "message": "Đã đánh dấu hoàn thành.", "data": ItemResource }`. Auto set `processing_status=done`, `completion_percent=100`, `completed_at=now()`, `approved_by=<user hiện tại>`, xóa `rejection_reason`. |
| **Response 422** | `{ "success": false, "message": "Công việc đang ở trạng thái \"Đang thực hiện\" — chỉ có thể đánh dấu hoàn thành khi đang chờ duyệt." }` (khi task không ở `pending_approval`). |

---

## Từ chối duyệt hoàn thành (reject)

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/reject` |
| **Auth** | Bắt buộc (`changeStatus` — permission `task-assignment-items.changeStatus`/`.pause`/`.cancel`, người giao hoặc người được giao). |
| **UrlParam** | `id` — ID công việc. |
| **Body** | `rejection_reason` (string, required, max 5000). |
| **Điều kiện** | Task phải đang ở `pending_approval`. |
| **Response 200** | `{ "message": "Đã từ chối duyệt.", "data": ItemResource }`. Chuyển `processing_status` về `todo`, `completion_percent=0`, lưu `rejection_reason`. |
| **Response 422** | `{ "success": false, "message": "Công việc đang ở trạng thái \"Đang thực hiện\" — chỉ có thể từ chối khi đang chờ duyệt." }` (khi task không ở `pending_approval`). |

---

## Cập nhật tiến độ

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/progress` |
| **Auth** | Bắt buộc (`updateProgress` — permission `task-assignment-items.updateProgress`, người giao hoặc người được giao). |
| **Body** | `processing_status` (optional — chỉ nhận `todo` \| `in_progress` \| `paused` \| `cancelled`, KHÔNG nhận `done`/`pending_approval`), `completion_percent` (optional, 0-100). Ít nhất một trong hai trường phải có giá trị (`sometimes`, không có rule bắt buộc cả 2 — có thể gửi trống nếu FE tự đảm bảo). |
| **Response** | `{ "message": "Cập nhật tiến độ thành công!", "data": ItemResource }`. |

**Auto-suy trạng thái theo `completion_percent`** khi request KHÔNG gửi kèm `processing_status`: `0%` → `todo`; `>0%` → `in_progress` (kể cả `100%` — endpoint này **không** tự chuyển `pending_approval`/`done`, xem [Business Logic](#business-logic)).

---

## Xuất báo cáo giao ban tháng (multi-sheet Excel)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/export-monthly-report` |
| **Auth** | Bắt buộc (permission `task-assignment-items.exportMonthlyReport`). |
| **Query** | `month` (Y-m, mặc định tháng hiện tại nếu bỏ trống). Example: `2026-04`. |
| **Response** | File Excel gồm nhiều sheet: Sheet 1 — Bảng tổng hợp (phòng ban x trạng thái x loại công việc); Sheet 2-8 — Chi tiết công việc từng phòng ban; Sheet cuối — Chương trình công tác tháng tiếp theo. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/export` |
| **Auth** | Bắt buộc (permission `task-assignment-items.export`). |
| **Query** | Cùng bộ lọc với index: `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `task_assignment_document_id`, `start_from`, `start_to`, `end_from`, `end_to`, `from_date`, `to_date`, `sort_by`, `sort_order`. |
| **Response** | File Excel (`ExportFilename::make('cong-viec-giao')`), cột theo `ItemsExport::headings()`: STT, Tên công việc, Mô tả, Văn bản, Loại công việc, Loại thời hạn, Ngày bắt đầu, Ngày kết thúc, Trạng thái xử lý, Hoàn thành (%), Lý do từ chối, Ngày báo cáo, Người báo cáo, Độ ưu tiên, Ngày duyệt, Người duyệt, Phòng ban, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID. |

> **Import Excel chưa được hỗ trợ cho Item.** `TaskAssignmentItemController`/route file không có action/route `import`/`import-template` — chỉ các module danh mục liên quan (Document, Department, Type) mới có import. Đừng gọi `POST /api/task-assignment-items/import` — endpoint này không tồn tại (404).

---

## Thống kê theo loại công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-item-type` |
| **Auth** | Bắt buộc (permission `task-assignment-items.statsByItemType` hoặc `presentation.index`). |
| **Query** | `department_id`, `priority`, `from_date` (YYYY-MM-DD), `to_date` (YYYY-MM-DD). |
| **Response** | Mảng `[{ "item_type_id": 1, "item_type_name": "TT Thành ủy giao", "total": 19, "todo": 5, "in_progress": 8, "pending_approval": 1, "paused": 1, "done": 3, "cancelled": 0, "timing_stats": { "upcoming": 10, "overdue": 2, "late": 1, "early": 1, "on_time": 1, "cancelled": 0 } }]`. |

---

## Thống kê theo văn bản giao việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-document` |
| **Auth** | Bắt buộc (permission `task-assignment-items.statsByDocument`). |
| **Query** | `department_id`, `task_assignment_type_id` (ID loại văn bản), `from_date` (YYYY-MM-DD — lọc theo ngày ban hành), `to_date` (YYYY-MM-DD). |
| **Response** | Mảng `[{ "document_id": 1, "document_name": "KH số 123", "issue_date": "2026-03-15", "total_items": 10, "done": 7, "in_progress": 2, "pending_approval": 1, "completion_rate": 70.0 }]`. `completion_rate` = `done / total_items * 100` (làm tròn 1 chữ số thập phân). |

---

## Thống kê theo phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-department` |
| **Auth** | Bắt buộc (permission `task-assignment-items.statsByDepartment` hoặc `presentation.index`). |
| **Query** | `department_id`, `processing_status`, `priority`, `deadline_type`, `task_assignment_item_type_id`, `from_date`, `to_date`. |
| **Response** | Mảng `[{ "department_id": 1, "department_name": "Phòng Kỹ thuật", "department_code": "KT", "total": 10, "todo": 2, "in_progress": 4, "pending_approval": 1, "paused": 0, "done": 3, "cancelled": 0, "timing_stats": { "upcoming": 5, "overdue": 1, "late": 0, "early": 1, "on_time": 2, "cancelled": 0 }, "new_in_period": 3, "done_in_period": 2 }]`. `new_in_period`/`done_in_period` chỉ tính khi có cả `from_date` và `to_date`, ngược lại trả `null`. |

---

## Thống kê theo người dùng

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-user` |
| **Auth** | Bắt buộc (permission `task-assignment-items.statsByUser`). |
| **Query** | `department_id`, `processing_status`, `priority`, `from_date`, `to_date`. |
| **Response** | Mảng `[{ "user_id": 2, "user_name": "Nguyễn Văn A", "total": 8, "todo": 1, "in_progress": 3, "pending_approval": 1, "done": 3, "paused": 0, "cancelled": 0, "assigned_count": 5, "accepted_count": 3, "new_in_period": 2, "done_in_period": 1 }]`. |

> Field `on_time_count`/`overdue_done_count` **không còn tồn tại** ở endpoint này — thay bằng `assigned_count` (số pivot có `assignment_status=assigned`) và `accepted_count` (số pivot có `assignment_status=done`). `new_in_period`/`done_in_period` chỉ có giá trị khi truyền cả `from_date` và `to_date`.

---

## Thống kê theo thời gian

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-time` |
| **Auth** | Bắt buộc (permission `task-assignment-items.statsByTime`). |
| **Query** | `from_date` (required, YYYY-MM-DD), `to_date` (required, YYYY-MM-DD — tối đa cách `from_date` 12 tháng), `department_id`, `user_id`, `processing_status`. |
| **Response** | Mảng theo từng tháng trong khoảng, `[{ "month": "2026-01", "total": 15, "done": 8, "pending_approval": 2, "new_tasks": 10 }]`. `total`/`pending_approval` là số lũy kế tính đến cuối tháng đó (`created_at <= endOfMonth`), `done`/`new_tasks` tính trong tháng. |

> Field `overdue` **không còn** trong response của endpoint này (khác với `stats`/`stats-by-item-type`/`stats-by-department` vẫn có `timing_stats.overdue`).

---

## Danh sách quá hạn

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/overdue` |
| **Auth** | Bắt buộc (permission `task-assignment-items.overdue` hoặc `presentation.index`). |
| **Query** | `department_id`, `user_id`, `priority`, `from_date`/`to_date` (lọc `end_at`), `sort_by` (end_at \| priority \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated `ItemCollection` — danh sách công việc `deadline_type=has_deadline`, `end_at < hôm nay`, và `processing_status` KHÔNG thuộc `done`/`cancelled` (tương đương filter `is_overdue=1`). **Không còn dựa trên `processing_status = overdue`** vì `overdue` đã bị loại khỏi enum. |

---

## Danh sách sắp đến hạn

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/upcoming-deadline` |
| **Auth** | Bắt buộc (permission `task-assignment-items.upcomingDeadline` hoặc `presentation.index`). |
| **Query** | `days` (1-30, mặc định 3 — số ngày sắp đến hạn), `department_id`, `user_id`, `priority`, `from_date`/`to_date` (lọc `end_at`, AND với khoảng `days`), `limit`. |
| **Response** | Paginated `ItemCollection` — công việc `deadline_type=has_deadline`, `end_at` trong khoảng `[hôm nay, hôm nay + days]`, và `processing_status` KHÔNG thuộc `done`/`cancelled`. |

---

## Timeline công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/{id}/timeline` |
| **Auth** | Bắt buộc (`view` — permission `task-assignment-items.show`). |
| **UrlParam** | `id` — ID công việc. |
| **Query** | `limit` (mặc định 20), `page` (mặc định 1). |
| **Response** | `TimelineCollection` — hợp nhất lịch sử trao đổi (notes) và lịch sử chuyển việc (transfers) thành 1 dòng thời gian, sắp xếp tăng dần (cũ nhất trước): `[{ "type": "note", "id": 1, "timestamp": "08:30:00 20/04/2026", "actor": { "id": 3, "name": "Nguyễn Văn A" }, "data": {} }, { "type": "transfer", "id": 1, "timestamp": "14:00:00 21/04/2026", "actor": { "id": 3, "name": "Nguyễn Văn A" }, "data": {} }]`. |

---

## Business Logic

**Chuỗi chuyển trạng thái thực tế** (`todo → in_progress → pending_approval → done`, cộng nhánh reject/reopen):

1. **Tạo mới** → mặc định `todo` (hoặc `in_progress`/`paused`/`cancelled` nếu FE truyền tay qua `store`; **không thể** tạo thẳng ở `done`/`pending_approval`).
2. **`todo → in_progress`**: xảy ra qua (a) `PATCH {id}/progress` với `completion_percent > 0` và không truyền `processing_status`, hoặc (b) submit báo cáo (`POST /task-assignment-item-reports`) kèm `completion_percent` trong khoảng `1-99` khi task đang `todo`, hoặc (c) đặt tay qua `{id}/status`/`bulk-status`/`update`.
3. **`in_progress → pending_approval`**: **CHỈ** xảy ra khi nhân viên **submit báo cáo** (`POST /task-assignment-item-reports` hoặc `PUT/PATCH` báo cáo) với `completion_percent = 100`. Khi đó BE tự set: `processing_status = pending_approval`, `reported_at = now()`, `reported_by = <người nộp>`, xóa `rejection_reason`. **Lưu ý quan trọng**: `PATCH {id}/progress` (endpoint cập nhật tiến độ trực tiếp, không qua báo cáo) dù nhận `completion_percent = 100` cũng **KHÔNG** tự chuyển sang `pending_approval` — nó chỉ giữ ở `in_progress` (chỉ bắn event `TaskCompleted`, không đổi status). Muốn vào `pending_approval` bắt buộc phải qua flow nộp báo cáo.
4. **`pending_approval → done`**: chỉ qua `PATCH {id}/mark-done`, và chỉ người giao (`assigned_by`) mới gọi được. Auto set `completion_percent=100`, `completed_at=now()`, `approved_by=<manager>`, xóa `rejection_reason`. Bắn 2 event: `TaskConfirmed` và `TaskCompleted`.
5. **`pending_approval → todo`** (từ chối): qua `PATCH {id}/reject` kèm `rejection_reason`. Reset `completion_percent=0`.
6. **Mở lại từ trạng thái đóng** (`done`/`cancelled`/`paused`/`pending_approval`): qua `PATCH {id}/reopen`, trạng thái mới suy theo `completion_percent` hiện tại (`0%→todo`, `1-99%→in_progress`, `100%→pending_approval`); **không** tự nhảy thẳng `done`.
7. **Không có auto-sync `done ↔ completion_percent=100`** theo kiểu 2 chiều như trước — cụ thể:
   - Set `processing_status=done` tay (qua `update`, hiếm khi nên dùng) → BE tự set `completion_percent=100` + `completed_at=now()` (`buildStatusUpdateData()`).
   - Set `completion_percent=100` qua `{id}/progress` → **KHÔNG** tự set `done` (như mục 3).
   - Đổi từ `done`/`pending_approval` sang trạng thái khác (qua `changeStatus`) → `completed_at` bị xóa (`null`).
8. **`paused`/`cancelled`**: chỉ người giao (`assigned_by`) mới đặt được (policy `checkPauseCancelRestriction`), qua `{id}/status`, `bulk-status`, hoặc `update`.

---

## Response mẫu (ItemResource)

```json
{
  "id": 1,
  "name": "Báo cáo tình hình nhân sự Q1/2026",
  "description": "Tổng hợp và báo cáo tình hình nhân sự của phòng ban trong quý 1",
  "document": { "id": 1, "name": "Quyết định số 01/QĐ-HĐQT" },
  "item_type": { "id": 1, "name": "Nhiệm vụ thường xuyên" },
  "deadline_type": "has_deadline",
  "start_at": "08:00:00 10/01/2026",
  "end_at": "17:00:00 31/03/2026",
  "processing_status": "pending_approval",
  "completion_percent": 100,
  "rejection_reason": null,
  "reported_at": "09:15:00 30/03/2026",
  "reported_by": { "id": 5, "name": "Nguyễn Văn B", "email": "b@example.com" },
  "priority": "high",
  "completed_at": null,
  "approved_by": null,
  "is_overdue": false,
  "timing_status": "upcoming",
  "departments": [
    { "id": 1, "name": "Phòng Nhân sự", "role": "main" }
  ],
  "users": [
    {
      "id": 2,
      "name": "Nguyễn Văn A",
      "email": "a@example.com",
      "avatar": null,
      "department_id": 1,
      "department_role": "main",
      "assignment_role": "main",
      "assignment_status": "done",
      "assigned_at": "08:00:00 10/01/2026",
      "accepted_at": null,
      "completed_at": "09:15:00 30/03/2026",
      "note": null
    }
  ],
  "attachments": [],
  "reports_count": 1,
  "transfers_count": 0,
  "notes_count": 0,
  "assigned_by": { "id": 1, "name": "Admin" },
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 10/01/2026",
  "updated_at": "09:15:00 30/03/2026",
  "reminders": []
}
```

> `departments`, `users`, `attachments`, `reminders` chỉ xuất hiện khi quan hệ tương ứng đã được `load()` (dùng `whenLoaded`). `reports_count`/`transfers_count`/`notes_count` chỉ xuất hiện khi đã `loadCount()` tương ứng: `show` load cả 3; `index`/`overdue`/`upcoming-deadline` chỉ `loadCount('reports')` (nên chỉ `reports_count` xuất hiện, `transfers_count`/`notes_count` bị ẩn ở các endpoint đó). `reminders[]` chỉ trả các bản ghi `source=CUSTOM` (loại `PRESET` nội bộ hệ thống). `timing_status` nhận 1 trong: `cancelled` \| `early` \| `on_time` \| `late` \| `upcoming` \| `overdue` — tính bởi `ItemResource::resolveTimingStatus()`, khác với `report.timing_status` (tính trên báo cáo).
