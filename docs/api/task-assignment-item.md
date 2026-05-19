# API Công việc (Task Assignment Item)

Quản lý công việc trong hệ thống giao việc liên phòng ban: thống kê, danh sách (với bộ lọc nâng cao), chi tiết, CRUD, xóa/cập nhật trạng thái hàng loạt, đổi trạng thái, cập nhật tiến độ, xuất/nhập Excel. Mỗi công việc có thể giao cho nhiều phòng ban và nhiều người dùng (quan hệ nhiều-nhiều).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** tất cả endpoint chỉ thao tác dữ liệu thuộc tổ chức hiện tại (`organization_id` theo `X-Organization-Id`).

**Base path:** `/api/task-assignment-items`

**Enum values:**
- `processing_status`: `todo` | `in_progress` | `done` | `overdue` | `paused` | `cancelled`
- `deadline_type`: `has_deadline` | `no_deadline`
- `priority`: `low` | `medium` | `high` | `urgent`

---

## Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats` |
| **Auth** | Bắt buộc. |
| **Query** | `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `task_assignment_document_id`, `start_from` / `start_to`, `end_from` / `end_to`, `from_date` / `to_date` (lọc theo `created_at`), `sort_by`, `sort_order`, `limit` (1-100). |
| **Response** | `{ "total": 100, "todo": 20, "in_progress": 30, "done": 25, "overdue": 10, "paused": 10, "cancelled": 5 }` — total (sau lọc), các key còn lại là số lượng theo từng trạng thái. |

---

## Danh sách công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc. |
| **Query** | `search` (tên công việc), `processing_status` (todo \| in_progress \| done \| overdue \| paused \| cancelled), `priority` (low \| medium \| high \| urgent), `deadline_type` (has_deadline \| no_deadline), `department_id` (ID phòng ban được giao), `user_id` (ID người dùng được giao), `assignment_role` (main \| cooperate — vai trò phòng ban), `assignment_status` (lọc theo trạng thái phân công người dùng), `task_assignment_document_id` (ID văn bản giao việc), `task_assignment_item_type_id`, `start_from` / `start_to` (YYYY-MM-DD — lọc theo `start_at`), `end_from` / `end_to` (YYYY-MM-DD — lọc theo `end_at`), `from_date` / `to_date` (YYYY-MM-DD — lọc theo `created_at`), `sort_by` (id \| name \| start_at \| end_at \| priority \| completion_percent \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item kèm `departments`, `users`, tên văn bản và loại công việc. |

---

## Chi tiết công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc. |
| **UrlParam** | `id` — ID công việc. |
| **Response** | Object công việc (TaskAssignmentItemResource), kèm `departments`, `users`, `task_assignment_document`, `task_assignment_item_type`. |

---

## Tạo công việc

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items` |
| **Auth** | Bắt buộc. |
| **Status code** | 201 / 422 |

**Body** (1 task = 1 phòng ban):
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

> **Lưu ý**: Endpoint này tạo **1 task tương ứng 1 phòng ban**. Toàn bộ `users[].department_id` trong cùng 1 request phải là **cùng giá trị** (cùng dept). Muốn giao cho nhiều phòng ban → FE loop N call POST, mỗi call 1 dept (xem section [Multi-department — Pattern FE duplicate](#multi-department--pattern-fe-duplicate) bên dưới).

**Required**: `task_assignment_document_id`, `name`, `deadline_type`, `users` (mảng tối thiểu 1 phần tử).

**Field detail**:
- `users[].user_id` (int, required) — ID user trong `users` tổng. **Phải là nhân viên module Task active** (`task_assignment_employees.status = active`) **VÀ phải thuộc đúng `department_id`** đang gán (`task_assignment_users` row tồn tại).
- `users[].department_id` (int, required) — ID phòng ban đang gán user vào.
- `users[].department_role` (string, required) — `main` (chủ trì) \| `cooperate` (phối hợp). Vai trò của PHÒNG BAN trong công việc.
- `users[].assignment_role` (string, required) — `main` (chủ trì) \| `support` (hỗ trợ). Vai trò của RIÊNG user trong scope phòng ban đó.
- `end_at` bắt buộc nếu `deadline_type = has_deadline`.
- `assigned_by` (optional) — ID người giao việc, phải là nhân viên module Task active.

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

**Response 201**: object công việc (kèm `users[]`) + `"message": "Công việc đã được tạo thành công!"`.

---

## Cập nhật công việc

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc. |
| **Body** | Mọi field optional (partial update). `users[]` nếu có **sẽ ghi đè danh sách hiện tại** (`sync()` Eloquent — auto detach user cũ không có trong list, attach user mới). |
| **Validate** | Giống Tạo — `users[*].user_id` qua 2 gate (employee active + in dept). |
| **Response** | Object công việc đã cập nhật (kèm `users[]`). |

**Lưu ý sync behavior**: nếu gửi `users: []` (mảng rỗng) → BE validate fail (`min:1`). Muốn detach tất cả thì phải không gửi key `users` (hoặc gửi `users: null`).

---

## Multi-department — Pattern FE duplicate

**Yêu cầu nghiệp vụ**: 1 công việc giao cho nhiều phòng ban → sinh ra nhiều dòng công việc cùng tên (mỗi phòng 1 task), edit độc lập từng dòng.

**Cách BE xử lý**: BE **KHÔNG có batch endpoint** tự nhân bản. Mỗi POST tạo đúng 1 task gắn 1 dept. **FE chủ động loop submit nhiều POST** dựa trên số dept người dùng chọn — tránh phải bấm "Thêm mới" lặp lại.

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

5. Kết quả DB: 2 row trong `task_assignment_items` cùng tên, khác ID,
   mỗi row gắn 1 dept. List index sẽ hiện 2 dòng riêng.

6. Admin edit từng row độc lập:
   PATCH /api/task-assignment-items/{id}   { name?, dates?, users? }
   → chỉ ảnh hưởng task đó, không lan sang task của dept khác.
```

### Lưu ý implementation FE

- **Atomicity**: BE không bọc transaction xuyên-task. Nếu call thứ K fail (vd validate user) → các task 1..K-1 đã tạo vẫn tồn tại. FE nên:
  - **Cách A**: validate trước client-side đầy đủ (employee active + thuộc dept) trước khi loop POST.
  - **Cách B**: nếu fail giữa chừng, hiện modal "Đã tạo K task, còn N-K fail. Bạn muốn rollback (xóa K đã tạo) hay giữ?".

- **Attachment file upload**: nếu form có `attachments[]`, FE phải upload **cho từng POST** (mỗi task có copy attachment riêng). Cách tối ưu:
  - Upload file 1 lần lên `/api/media` (nếu có endpoint cache media độc lập), nhận `media_id`, gửi `media_id` reuse cho N POST. **HIỆN TẠI BE chưa có endpoint này** → FE phải upload N lần (multipart cho mỗi POST).

- **Performance**: N call POST có thể song song (`Promise.all`) — không có dependency giữa các task. Nhưng nếu N > 5, nên loading state "Đang tạo {k}/{n}" để user thấy progress.

- **UX gợi ý**: cuối flow show thông báo "Đã tạo {N} công việc cho {M} phòng ban" + link sang list (filter `task_assignment_document_id`) để admin xem ngay.

### Tại sao không 1 endpoint batch?

- Schema pivot `task_assignment_item_user` đã hỗ trợ multi-dept trong 1 task → BE có thể accept multi-dept luôn.
- **Lý do dùng FE duplicate**: nghiệp vụ yêu cầu **edit độc lập từng dept**. Nếu lưu chung 1 task, edit name/dates sẽ lan sang tất cả dept. Tách thành N task → edit không ảnh hưởng nhau.
- BE không refactor thêm endpoint batch để tránh phá BC + giảm complexity.

---

## Xóa công việc

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-items/{id}` |
| **Auth** | Bắt buộc. |
| **Response** | `{ "message": "Công việc đã được xóa thành công!" }`. |

---

## Xóa hàng loạt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items/bulk-delete` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array) — danh sách ID công việc. |
| **Response** | `{ "message": "Đã xóa thành công các công việc được chọn!" }`. |

---

## Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/bulk-status` |
| **Auth** | Bắt buộc. |
| **Body** | `ids` (array), `processing_status` (required: todo \| in_progress \| done \| overdue \| paused \| cancelled). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công các công việc được chọn!" }`. |

---

## Đổi trạng thái công việc

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/status` |
| **Auth** | Bắt buộc. |
| **Body** | `processing_status` (required: todo \| in_progress \| done \| overdue \| paused \| cancelled). |
| **Response** | `{ "message": "Cập nhật trạng thái thành công!", "data": TaskAssignmentItemResource }`. |

---

## Cập nhật tiến độ

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-items/{id}/progress` |
| **Auth** | Bắt buộc. |
| **Body** | `processing_status` (optional: todo \| in_progress \| done \| overdue \| paused \| cancelled), `completion_percent` (optional, 0-100). Ít nhất một trong hai trường phải có giá trị. |
| **Response** | `{ "message": "Cập nhật tiến độ thành công!", "data": TaskAssignmentItemResource }`. |

---

## Xuất báo cáo giao ban tháng (multi-sheet Excel)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/export-monthly-report` |
| **Auth** | Bắt buộc. |
| **Query** | `month` (required, Y-m). Example: `2026-04`. |
| **Response** | File Excel gồm nhiều sheet: Sheet 1 — Bảng tổng hợp (phòng ban x trạng thái x loại công việc); Sheet 2-8 — Chi tiết công việc từng phòng ban; Sheet cuối — Chương trình công tác tháng tiếp theo. |

---

## Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/export` |
| **Auth** | Bắt buộc. |
| **Query** | Cùng bộ lọc với index: `search`, `processing_status`, `priority`, `deadline_type`, `department_id`, `user_id`, `task_assignment_document_id`, `start_from`, `start_to`, `end_from`, `end_to`, `from_date`, `to_date`, `sort_by`, `sort_order`. |
| **Response** | File `task-assignment-items.xlsx`. |

---

## Nhập Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-items/import` |
| **Auth** | Bắt buộc. |
| **Body** | `file` (required) — xlsx, xls, csv. Cột theo chuẩn export. |
| **Response** | `{ "message": "Import công việc thành công." }`. |

---

## Tải mẫu import

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/import-template` |
| **Auth** | Bắt buộc (permission: import). |
| **Response** | File `import-items-template.xlsx` — chỉ có header row: `name`, `description`, `deadline_type`, `start_at`, `end_at`, `processing_status`, `completion_percent`, `priority`. |

---

## Thống kê theo loại công việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-item-type` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `priority`, `from_date` (YYYY-MM-DD), `to_date` (YYYY-MM-DD). |
| **Response** | Mảng `[{ "item_type_id": 1, "item_type_name": "TT Thành ủy giao", "total": 19, "todo": 5, "in_progress": 8, "done": 3, "overdue": 2, "paused": 1, "cancelled": 0 }]`. |

---

## Thống kê theo văn bản giao việc

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-document` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `task_assignment_type_id` (ID loại văn bản), `from_date` (YYYY-MM-DD — lọc theo ngày ban hành), `to_date` (YYYY-MM-DD). |
| **Response** | Mảng `[{ "document_id": 1, "document_name": "KH số 123", "issue_date": "2026-03-15", "total_items": 10, "done": 7, "in_progress": 2, "overdue": 1, "completion_rate": 70.0 }]`. |

---

## Thống kê theo phòng ban

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-department` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `processing_status`, `priority`, `deadline_type`, `task_assignment_item_type_id`, `from_date`, `to_date`. |
| **Response** | Mảng `[{ "department_id": 1, "department_name": "Phòng Kỹ thuật", "department_code": "KT", "total": 10, "todo": 2, "in_progress": 4, "done": 3, "overdue": 1, "paused": 0, "cancelled": 0 }]`. |

---

## Thống kê theo người dùng

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-user` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `processing_status`, `priority`, `from_date`, `to_date`. |
| **Response** | Mảng `[{ "user_id": 2, "user_name": "Nguyễn Văn A", "total": 8, "todo": 1, "in_progress": 3, "done": 3, "overdue": 1, "on_time_count": 2, "overdue_done_count": 1 }]`. |

---

## Thống kê theo thời gian

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/stats-by-time` |
| **Auth** | Bắt buộc. |
| **Query** | `from_date` (required, YYYY-MM-DD), `to_date` (required, YYYY-MM-DD — tối đa cách `from_date` 12 tháng), `department_id`, `user_id`, `processing_status`. |
| **Response** | Mảng `[{ "month": "2026-01", "total": 15, "done": 10, "overdue": 2, "new_tasks": 5 }]`. |

---

## Danh sách quá hạn

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/overdue` |
| **Auth** | Bắt buộc. |
| **Query** | `department_id`, `user_id`, `priority`, `sort_by`, `sort_order`, `limit`. |
| **Response** | Paginated ItemCollection — danh sách công việc có `processing_status = overdue`. |

---

## Danh sách sắp đến hạn

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-items/upcoming-deadline` |
| **Auth** | Bắt buộc. |
| **Query** | `days` (1-30, mặc định 3 — số ngày sắp đến hạn), `department_id`, `user_id`, `priority`, `sort_by`, `sort_order`, `limit`. |
| **Response** | Paginated ItemCollection — danh sách công việc có `end_at` trong vòng `days` ngày tới. |

---

## Business Logic

**Đồng bộ tiến độ và trạng thái (done ↔ 100%):**
- Khi `processing_status = done` → hệ thống tự động set `completion_percent = 100` và ghi nhận `completed_at`.
- Khi `completion_percent = 100` → hệ thống tự động chuyển `processing_status = done` và ghi nhận `completed_at`.
- Khi mở lại công việc từ trạng thái `done` (chuyển sang trạng thái khác) → `completed_at` được xóa (clear).

---

## Response mẫu (TaskAssignmentItemResource)

```json
{
  "id": 1,
  "name": "Báo cáo tình hình nhân sự Q1/2026",
  "description": "Tổng hợp và báo cáo tình hình nhân sự của phòng ban trong quý 1",
  "task_assignment_document_id": 1,
  "task_assignment_document": { "id": 1, "name": "Quyết định số 01/QĐ-HĐQT" },
  "task_assignment_item_type_id": 1,
  "task_assignment_item_type": { "id": 1, "name": "Nhiệm vụ thường xuyên" },
  "deadline_type": "has_deadline",
  "start_at": "2026-01-10 08:00:00",
  "end_at": "2026-03-31 17:00:00",
  "processing_status": "in_progress",
  "completion_percent": 45,
  "priority": "high",
  "completed_at": null,
  "departments": [
    { "id": 1, "name": "Phòng Nhân sự", "code": "NS", "role": "main" }
  ],
  "users": [
    { "id": 2, "name": "Nguyễn Văn A", "email": "a@example.com", "department_id": 1, "assignment_role": "main" }
  ],
  "created_by": { "id": 1, "name": "Admin" },
  "updated_by": { "id": 1, "name": "Admin" },
  "created_at": "08:00:00 10/01/2026",
  "updated_at": "10:00:00 01/04/2026"
}
```
