# API Nhân viên giao việc (Task Assignment Employee)

> Cập nhật lần cuối: 24/08/2026 — bảng nối đổi tên thành `task_assignment_employee_department` và khoá theo `task_assignment_employee_id`; form nhân viên nhận thêm `department_ids`.

Quản lý danh sách **nhân viên module Task** — lớp trung gian giữa `users` tổng và bảng nối phòng ban (`task_assignment_employee_department`). Chỉ user đã đăng ký làm nhân viên (active) mới có thể được gán vào phòng ban hoặc nhận task.

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Phạm vi dữ liệu:** mọi endpoint scope theo tổ chức hiện tại (`organization_id`).

**Base path:** `/api/task-assignment-employees`

---

## Mục lục

1. [Mô hình dữ liệu](#mô-hình-dữ-liệu)
2. [Workflow FE](#workflow-fe) — 3 use case bắt buộc đọc trước khi code
3. [Cấu trúc response chung](#cấu-trúc-response-chung)
4. [Endpoints](#endpoints)
5. [Error responses](#error-responses)
6. [Permission FE-side check](#permission-fe-side-check)
7. [Endpoint phụ thuộc](#endpoint-phụ-thuộc)

---

## Mô hình dữ liệu

```
users (tổng hệ thống)
   │
   │ đăng ký vào module
   ▼
task_assignment_employees   ◄── BẢNG MỚI (lớp gate)
   │   - user_id (FK users)
   │   - status (active/inactive)
   │   - note
   │
   │ gán vào phòng ban
   ▼
task_assignment_employee_department (bảng nối employee × dept)
   │   - user_id, task_assignment_department_id
   │   - is_representative
   │
   │ giao việc
   ▼
task_assignment_item_user (pivot user × task × dept)
   - assignment_role, assignment_status
```

**Quy tắc validate**:
- `POST /task-assignment-departments/{id}/users` (sync nhân viên vào dept): `user_ids[*]` phải tồn tại trong `task_assignment_employees` active.
- `POST /task-assignment-items` + `PUT /task-assignment-items/{id}` (giao task): mỗi `users[*]` phải qua 2 gate — là employee active **và** thuộc đúng `department_id` đang gán.

---

## Workflow FE

### UC1 — Màn "Quản lý nhân viên" (mới)

CRUD danh sách nhân viên module Task. Vị trí menu: cụm Module giao việc, ngay cạnh "Phòng ban".

```
[Nút "Thêm nhân viên"] → mở dialog
  ↓
1. FE gọi GET /api/users?status=active&limit=100&search={typed}
   → list users tổng để chọn
2. FE filter loại bỏ user đã là employee (tùy chọn UX):
   - Cách 1 (client): cache list employees hiện tại, filter local
   - Cách 2 (server): không cần — nếu trùng, BE trả 422 "đã là nhân viên"
3. User pick → POST /api/task-assignment-employees
   { "user_id": 5, "status": "active", "note": null }
   → 201 + EmployeeResource
   → trả 422 nếu user đã là employee
   ↓
4. FE refetch list employees (GET /api/task-assignment-employees)
```

### UC2 — Màn "Phòng ban → Thêm/Sửa thành viên" (đổi nguồn dropdown)

**Đây là điểm phá BC quan trọng nhất**. Trước: pick từ `users` tổng. Sau: pick từ employees module Task.

```
[Mở phòng ban X → tab "Thành viên"] → nút "Thêm thành viên"
  ↓
1. FE gọi GET /api/task-assignment-employees/options?status=active
   → list nhân viên active (KHÔNG phải /api/users nữa)
2. Optional: FE filter loại bỏ user đã có trong dept X
   (gọi GET /api/task-assignment-departments/{X}/users để check)
3. User pick multi → POST /api/task-assignment-departments/{X}/users
   { "user_ids": [5, 8, 12], "representative_user_id": 5 }
   → BE validate: mọi user_id phải là employee active. Nếu không → 422.
```

**FE cần handle**:
- Nếu admin click "Thêm thành viên" mà list employees rỗng → hiện thông báo "Hãy đăng ký nhân viên ở màn Quản lý nhân viên trước" + nút deep link sang màn đó.
- Nếu user trong dept hiện đã bị set status `inactive` ở màn Quản lý nhân viên → vẫn hiện trong list dept (validate sync dùng `where status active`, nhưng list dept hiện tại không filter status employee).

### UC3 — Form Giao task (Văn bản → Thêm công việc) — **đa phòng ban**

**Dropdown đôi (dept → user) — cả 2 endpoint authenticated, KHÔNG qua Spatie**. Form admin dùng cặp này.

**Quan trọng**: 1 task = 1 dept ở phía BE. Giao 1 việc cho N dept = FE loop N call POST. Đọc chi tiết ở [task-assignment-item.md → Multi-department Pattern FE duplicate](./task-assignment-item.md#multi-department--pattern-fe-duplicate).

```
[Form thêm công việc đa phòng ban]
  ↓
1. User nhập thông tin chung (name, dates, priority, attachments...).

2. User pick danh sách dept và user/role qua 2 dropdown:
   - Dropdown 1 — chọn dept:
     GET /api/task-assignment-departments/options?status=active
     → list dept active (authenticated, no Spatie, dành riêng form admin)
     → KHÔNG dùng /api/public/... (guest) hay /api/task-assignment-departments (admin có Spatie)
   - Dropdown 2 — chọn user thuộc dept đã pick:
     GET /api/task-assignment-employees/options?department_id={dept_id}&status=active
     → chỉ trả employees active thuộc dept đó

3. User có thể thêm nhiều dòng — mỗi dòng = { dept_id, user_id, dept_role, ass_role }.
   Cho phép nhiều user cùng 1 dept, hoặc nhiều dept khác nhau.

4. FE group theo dept_id, submit N call POST song song:
   POST /api/task-assignment-items   { ...common, users: group_dept_A }
   POST /api/task-assignment-items   { ...common, users: group_dept_B }
   → mỗi POST tạo 1 task riêng cùng tên, khác ID, gắn 1 dept.

5. Admin sau đó edit từng task độc lập (không ảnh hưởng task dept khác).
```

**Lỗi thường gặp khi submit task**:
- 422 `users.0`: "User ID X không phải nhân viên module Task hoặc đã bị vô hiệu hóa." → user chưa register/inactive
- 422 `users.0`: "User ID X không thuộc phòng ban ID Y trong tổ chức này." → user là employee nhưng chưa được sync vào dept Y
- 422 nếu trộn nhiều `department_id` khác nhau trong cùng 1 POST: BE vẫn cho qua (schema không cấm), nhưng FE nên enforce 1 dept/POST để khớp UX edit độc lập

---

## Cấu trúc response chung

Toàn dự án dùng trait `RespondsWithJson`:

```jsonc
// Success — single resource (show/store/update/changeStatus)
{
  "success": true,
  "message": "Đăng ký nhân viên thành công!",  // optional
  "data": { /* EmployeeResource */ }
}

// Success — collection (index, tree)
{
  "success": true,
  "data": [ /* EmployeeResource[] */ ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": {
    "current_page": 1, "from": 1, "last_page": 3,
    "path": "...", "per_page": 10, "to": 10, "total": 26
  }
}

// Success — raw data (stats, destroy, bulk, options, import)
{
  "success": true,
  "message": "Xóa hàng loạt nhân viên thành công!",  // optional
  "data": { /* raw */ }
}

// Error
{
  "success": false,
  "message": "...",
  "errors": { /* field-level errors hoặc structured info */ },
  "code": "VALIDATION_ERROR" // optional code
}
```

### EmployeeResource — sample đầy đủ

```json
{
  "id": 1,
  "user_id": 5,
  "user": {
    "id": 5,
    "name": "Nguyễn Văn Hùng",
    "avatar": "/storage/123/avatar.jpg"
  },
  "status": "active",
  "note": "Bổ sung nhân sự phòng nghiệp vụ.",
  "departments": [
    {
      "id": 3,
      "name": "Phòng Kế hoạch",
      "code": "PB-KH",
      "is_representative": false
    },
    {
      "id": 5,
      "name": "Phòng Tổng hợp",
      "code": "PB-TH",
      "is_representative": false
    }
  ],
  "created_by": { "id": 1, "name": "Admin", "avatar": null },
  "updated_by": { "id": 1, "name": "Admin", "avatar": null },
  "created_at": "10:30:00 19/05/2026",
  "updated_at": "10:30:00 19/05/2026"
}
```

**Lưu ý field**:
- `user`: chỉ load khi eager load (`with('user')`) — luôn có ở index/show/store/update.
- `departments[]`: chỉ có ở index/show. Là bản ghi trong bảng nối `task_assignment_employee_department`, KHÔNG phải bảng `task_assignment_departments`. `is_representative` là flag của bảng nối (`is_primary` đã bị bỏ).
- `created_at`, `updated_at`: format `H:i:s d/m/Y` (chuẩn dự án).

---

## Endpoints

### 1. Dropdown options

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-employees/options` |
| **Auth** | Bắt buộc (KHÔNG qua Spatie permission — mọi authenticated user gọi được; FE phòng ban + form giao việc dùng để chọn). |
| **Query** | `search` (tên/email/user_name), `status` (active \| inactive, mặc định active), `department_id` (lọc nhân viên thuộc dept), `sort_by`, `sort_order`. |

**Response** (raw array, không paginate):

```json
{
  "success": true,
  "data": [
    { "id": 1, "user_id": 5, "name": "Nguyễn Văn Hùng",
      "email": "hung@example.com", "user_name": "hungnv",
      "status": "active" },
    { "id": 2, "user_id": 8, "name": "Trần Thị Mai",
      "email": "mai@example.com", "user_name": "maitt",
      "status": "active" }
  ]
}
```

---

### 2. Thống kê

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-employees/stats` |
| **Permission** | `task-assignment-employees.stats` |
| **Query** | `search`, `status`, `department_id`, `from_date`, `to_date`. |

**Response**:
```json
{ "success": true, "data": { "total": 30, "active": 28, "inactive": 2 } }
```

---

### 3. Danh sách nhân viên (paginated)

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-employees` |
| **Permission** | `task-assignment-employees.index` |
| **Query** | `search` (tên/email/user_name), `status`, `department_id`, `from_date` (Y-m-d), `to_date` (Y-m-d), `sort_by` (id \| status \| created_at \| updated_at), `sort_order` (asc \| desc), `limit` (1-100, mặc định 10). |

**Response**: pagination format chuẩn Laravel — xem [Cấu trúc response chung](#cấu-trúc-response-chung). `data[]` là array `EmployeeResource`.

---

### 4. Chi tiết nhân viên

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-employees/{id}` |
| **Permission** | `task-assignment-employees.show` |
| **UrlParam** | `id` — ID nhân viên. |

**Response**: `{ "success": true, "data": EmployeeResource }`.

---

### 5. Đăng ký nhân viên mới

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-employees` |
| **Permission** | `task-assignment-employees.store` |
| **Status code** | 201 (success) / 422 (validate fail) |

**Body**:
```json
{
  "user_id": 5,
  "department_ids": [1, 4],
  "status": "active",
  "note": "Bổ sung nhân sự phòng nghiệp vụ."
}
```
- `user_id` (required, integer) — ID trong `users` tổng. Phải unique theo `(user_id, organization_id)`.
- `status` (required) — `active` | `inactive`.
- `note` (optional, max 65535) — ghi chú nội bộ.

**Response 201**:
```json
{
  "success": true,
  "message": "Đăng ký nhân viên thành công!",
  "data": { /* EmployeeResource */ }
}
```

**Response 422** — xem [Error responses](#error-responses).

---

### 6. Cập nhật nhân viên

| | |
|---|---|
| **Method** | PUT \| PATCH |
| **Path** | `/api/task-assignment-employees/{id}` |
| **Permission** | `task-assignment-employees.update` |

**Body** (mọi field optional, partial update OK):
```json
{ "status": "inactive", "note": "Tạm ngưng do nghỉ phép." }
```
- `user_id` KHÔNG cho sửa (tránh phá link pivot dept/task) — gửi cũng bị validate bỏ qua.

**Response**: 200 + EmployeeResource + `message: "Cập nhật nhân viên thành công!"`.

---

### 7. Xóa 1 nhân viên — Soft block 409

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-employees/{id}` |
| **Permission** | `task-assignment-employees.destroy` |
| **Status code** | 200 (xóa OK) / 409 (còn ràng buộc) |

**Behavior**: trả 409 nếu nhân viên còn dept hoặc task. Phải remove khỏi dept (`DELETE /api/task-assignment-departments/{deptId}/users/{userId}`) và task (qua transfer/update) trước khi xóa.

**Response 200**:
```json
{ "success": true, "message": "Xóa nhân viên thành công!" }
```

**Response 409** — xem [Error responses](#error-responses).

---

### 8. Xóa hàng loạt (atomic)

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-employees/bulk-delete` |
| **Permission** | `task-assignment-employees.bulkDestroy` |
| **Status code** | 200 / 409 |

**Body**:
```json
{ "ids": [1, 2, 3] }
```

**Behavior atomic**: nếu BẤT KỲ ID nào còn ràng buộc → 409 + không xóa bất kỳ row nào (toàn bộ rollback).

---

### 9. Cập nhật trạng thái hàng loạt

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-employees/bulk-status` |
| **Permission** | `task-assignment-employees.bulkUpdateStatus` |

**Body**:
```json
{ "ids": [1, 2, 3], "status": "inactive" }
```

**Response**: `{ "success": true, "message": "Cập nhật trạng thái hàng loạt thành công!" }`.

---

### 10. Đổi trạng thái 1 nhân viên

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/task-assignment-employees/{id}/status` |
| **Permission** | `task-assignment-employees.changeStatus` |

**Body**:
```json
{ "status": "active" }
```

**Response**: 200 + EmployeeResource + `message: "Đổi trạng thái thành công!"`.

---

### 11. Xuất Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-employees/export` |
| **Permission** | `task-assignment-employees.export` |
| **Query** | giống `index`. |
| **Response** | Stream file `.xlsx`. Filename auto-prefix `nhan-vien-giao-viec-{timestamp}.xlsx`. |

**Cột Excel**: STT, ID user, Họ tên, Email, Tên đăng nhập, Trạng thái, Ghi chú, Người tạo, Người cập nhật, Ngày tạo, Ngày cập nhật, ID.

Cột STT, Trạng thái, Ngày tạo, Ngày cập nhật, ID — căn giữa tự động (chuẩn `AbstractExcelExport`).

---

### 12. Import Excel

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-employees/import` |
| **Permission** | `task-assignment-employees.import` |
| **Body** | `file` (multipart, xlsx/xls/csv, max 10MB). |

**Định dạng file**:
- **Cột bắt buộc**: `ID user`.
- **Cột không bắt buộc**: `Trạng thái` (mặc định `active`), `Ghi chú`.

**Response 200**:
```json
{ "success": true, "message": "Import nhân viên thành công." }
```

---

### 13. Tải mẫu Excel

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-employees/import-template` |
| **Permission** | `task-assignment-employees.import` |
| **Response** | Stream file `.xlsx` rỗng (chỉ có header). Filename: `import-employees-template.xlsx`. |

---

## Error responses

### 422 — Validation error (mọi endpoint Store/Update/Bulk)

```json
{
  "success": false,
  "message": "The user id has already been taken.",
  "errors": {
    "user_id": [
      "Người dùng này đã là nhân viên của module trong tổ chức hiện tại."
    ]
  },
  "code": "VALIDATION_ERROR"
}
```

**Các message validate FE thường gặp**:
- `user_id.required`: "Vui lòng chọn người dùng."
- `user_id.exists`: "Người dùng không tồn tại."
- `user_id.unique`: "Người dùng này đã là nhân viên của module trong tổ chức hiện tại."
- `status.required`: "Vui lòng chọn trạng thái."
- `status.in`: "Trạng thái không hợp lệ."

### 409 — Soft block khi xóa nhân viên còn ràng buộc

```json
{
  "success": false,
  "message": "Không thể xóa nhân viên đang thuộc phòng ban hoặc còn công việc: user #5 (2 phòng ban, 3 công việc); user #8 (1 phòng ban, 0 công việc)",
  "errors": {
    "5": { "dept_count": 2, "task_count": 3 },
    "8": { "dept_count": 1, "task_count": 0 }
  }
}
```

**FE handle gợi ý**: parse `errors` map → modal liệt kê từng nhân viên + nút "Đi tới Phòng ban" / "Đi tới Công việc" để admin gỡ ràng buộc trước.

### 401 — Chưa đăng nhập / token hết hạn

```json
{ "success": false, "message": "Chưa xác thực", "code": "UNAUTHORIZED" }
```

### 403 — Không có permission

```json
{ "success": false, "message": "Không có quyền truy cập", "code": "FORBIDDEN" }
```

### 404 — Không tìm thấy nhân viên

```json
{ "success": false, "message": "Không tìm thấy tài nguyên", "code": "NOT_FOUND" }
```

---

## Permission FE-side check

FE đọc permissions của user từ `/api/auth/me` (hoặc context), check trước khi hiện UI tương ứng:

| Action UI | Permission cần |
|---|---|
| Vào menu "Quản lý nhân viên" | `task-assignment-employees.index` |
| Nút "Xem chi tiết" | `task-assignment-employees.show` |
| Nút "Thêm nhân viên" | `task-assignment-employees.store` |
| Nút "Sửa" / "Đổi trạng thái" | `task-assignment-employees.update` hoặc `.changeStatus` |
| Nút "Xóa" / "Xóa hàng loạt" | `task-assignment-employees.destroy` / `.bulkDestroy` |
| Nút "Cập nhật trạng thái hàng loạt" | `task-assignment-employees.bulkUpdateStatus` |
| Nút "Xuất Excel" | `task-assignment-employees.export` |
| Nút "Import" / "Tải mẫu" | `task-assignment-employees.import` |
| Section "Thống kê" | `task-assignment-employees.stats` |

**Đặc biệt**: `/options` KHÔNG cần permission — FE phòng ban + form giao việc luôn gọi được. KHÔNG dùng permission `options` để show/hide menu (sẽ không tồn tại).

---

## Endpoint phụ thuộc

FE cần biết để integrate màn "Quản lý nhân viên":

| Mục đích | Endpoint | Module |
|---|---|---|
| List users tổng để pick khi đăng ký nhân viên | `GET /api/users?search=...&status=active&limit=20` | Core/User |
| Chi tiết user (nếu cần snapshot khác avatar/name) | `GET /api/users/{id}` | Core/User |
| Dropdown list dept (form admin, authenticated, no Spatie) | `GET /api/task-assignment-departments/options` | TaskAssignment |
| Dropdown list dept cho citizen/guest (public) | `GET /api/public/task-assignment-departments/options` | TaskAssignment |
| Sync nhân viên vào dept | `POST /api/task-assignment-departments/{id}/users` | TaskAssignment |
| Xem thành viên hiện tại của 1 dept | `GET /api/task-assignment-departments/{id}/users` | TaskAssignment |
| Remove 1 user khỏi dept (để xóa được nhân viên) | `DELETE /api/task-assignment-departments/{id}/users/{userId}` | TaskAssignment |
| Tạo/sửa task với multi-user | `POST/PUT /api/task-assignment-items` | TaskAssignment |
| Get user của dept khi giao task | `GET /api/task-assignment-employees/options?department_id={X}` | TaskAssignment (chính module này) |

---

## Changelog

- **2026-05-19** — Khởi tạo module + 14 endpoint chuẩn. Backfill 26 nhân viên từ bảng nối phòng ban hiện có. Validate cross-check `task_assignment_employees` áp dụng cho 2 endpoint:
  - `POST /api/task-assignment-departments/{id}/users` — user_ids phải là employee active.
  - `POST/PUT /api/task-assignment-items` — `users.*.user_id` phải là employee active + thuộc đúng dept.
- **2026-05-19** — Thêm endpoint dropdown department authenticated: `GET /api/task-assignment-departments/options` (no Spatie). Dành riêng cho form admin (giao task, gán nhân viên). Cặp với `GET /api/task-assignment-employees/options` tạo flow dropdown đôi (dept → user).
