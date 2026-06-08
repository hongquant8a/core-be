# Loại bỏ logic phê duyệt/xác nhận khỏi Báo cáo công việc

**Ngày:** 08/06/2026

---

## 1. Bối cảnh

Trước đây, hệ thống có 2 tầng phê duyệt trên module TaskAssignment:

| Tầng | Nơi áp dụng | Cơ chế |
|------|-------------|--------|
| Phê duyệt công việc | `task_assignment_items` | `markDone` - manager đánh dấu công việc hoàn thành |
| Xác nhận báo cáo | `task_assignment_item_reports` | `confirm` - manager xác nhận từng báo cáo, set `is_locked=true` |

**Ràng buộc cũ:** Để `markDone` một công việc, phải có ít nhất 1 báo cáo được xác nhận (`is_locked=true`). Điều này tạo ra một quy trình 2 bước không cần thiết: nhân viên báo cáo → manager xác nhận báo cáo → manager markDone công việc.

---

## 2. Quyết định

**Dời logic phê duyệt lên cấp công việc (`task-assignment-items`), loại bỏ hoàn toàn khỏi cấp báo cáo (`task-assignment-item-reports`).**

Lý do:
- Báo cáo chỉ là bản ghi tiến độ — không cần trạng thái phê duyệt riêng.
- Phê duyệt ở cấp công việc (`markDone`) là đủ để kiểm soát việc hoàn thành.
- Giảm 1 bước thao tác cho manager, đơn giản hóa UX.

---

## 3. Các thay đổi chi tiết

### 3.1 Model `TaskAssignmentItemReport`

**File:** `app/Modules/TaskAssignment/Models/TaskAssignmentItemReport.php`

Xóa 7 cột khỏi `$fillable`:
```
'manager_confirmed', 'manager_confirmed_by', 'manager_confirmed_at',
'manager_confirm_note', 'is_locked', 'locked_at', 'locked_by'
```

Xóa khỏi `$casts`:
```
'manager_confirmed' => 'boolean',
'manager_confirmed_at' => 'datetime',
'is_locked' => 'boolean',
'locked_at' => 'datetime',
```

Xóa 2 relations:
```
managerConfirmer() → belongsTo(User::class, 'manager_confirmed_by')
locker()           → belongsTo(User::class, 'locked_by')
```

### 3.2 Service `TaskAssignmentReportService`

**File:** `app/Modules/TaskAssignment/Services/TaskAssignmentReportService.php`

| Thay đổi | Chi tiết |
|----------|----------|
| Xóa method `confirm()` | Toàn bộ logic xác nhận báo cáo (set `manager_confirmed`, `is_locked`) bị xóa |
| Xóa import `TaskConfirmed` | Không còn fire event này khi confirm |
| Xóa guard `is_locked` trong `update()` | Trước đây ném `RuntimeException` nếu report đã locked |
| Xóa guard `is_locked` trong `destroy()` | Trước đây ném `RuntimeException` nếu report đã locked |
| Clean eager loads | Xóa `managerConfirmer`, `locker` khỏi tất cả `->with()` và `->load()` |

### 3.3 Controller `TaskAssignmentItemReportController`

**File:** `app/Modules/TaskAssignment/Controllers/TaskAssignmentItemReportController.php`

| Thay đổi | Chi tiết |
|----------|----------|
| Xóa method `confirm()` | Endpoint `PATCH /{id}/confirm` không còn tồn tại |
| Xóa import `ConfirmReportRequest` | Class này đã bị xóa |
| Xóa try-catch trong `update()` | Không còn `RuntimeException` từ guard `is_locked` |
| Xóa try-catch trong `destroy()` | Như trên |

### 3.4 Route

**File:** `app/Modules/TaskAssignment/Routes/task_assignment_item_report.php`

Xóa route:
```php
Route::patch('/{taskAssignmentItemReport}/confirm', [TaskAssignmentItemReportController::class, 'confirm'])
    ->middleware('permission:task-assignment-item-reports.confirm,web');
```

### 3.5 Request class

**File:** `app/Modules/TaskAssignment/Requests/ConfirmReportRequest.php`

Đã xóa toàn bộ file.

### 3.6 Resource `ReportResource`

**File:** `app/Modules/TaskAssignment/Resources/ReportResource.php`

Xóa 7 trường khỏi response:
```
'manager_confirmed'
'manager_confirmed_by' (kèm relation managerConfirmer)
'manager_confirmed_at'
'manager_confirm_note'
'is_locked'
'locked_by' (kèm relation locker)
'locked_at'
```

Response hiện tại chỉ còn các trường cốt lõi:
```
id, task_assignment_item_id, reporter, completed_at, timing_status,
report_document_number, report_document_excerpt, report_document_content,
attachments, created_at, updated_at
```

### 3.7 `markDone()` trong `TaskAssignmentItemService`

**File:** `app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php`

Xóa đoạn kiểm tra `is_locked` report:
```php
// ĐÃ XÓA:
$hasLockedReport = TaskAssignmentItemReport::where('task_assignment_item_id', $item->id)
    ->where('is_locked', true)
    ->exists();
if (! $hasLockedReport) {
    throw new \RuntimeException('Phải có ít nhất 1 báo cáo đã được xác nhận...');
}
```

**Hành vi mới:** `markDone` chỉ kiểm tra công việc chưa ở trạng thái done/cancelled, sau đó set `processing_status=done`, `completion_percent=100`, `completed_at=now()` và fire `TaskConfirmed` event.

### 3.8 Permission

**File:** `database/seeders/PermissionSeeder.php`

Xóa `'confirm'` khỏi mảng `task-assignment-item-reports`:
```php
// Cũ:
'task-assignment-item-reports' => ['index', 'show', 'store', 'update', 'destroy', 'confirm'],
// Mới:
'task-assignment-item-reports' => ['index', 'show', 'store', 'update', 'destroy'],
```

### 3.9 Migration

**File:** `database/migrations/2026_06_08_100000_drop_approval_columns_from_task_assignment_item_reports.php`

Drop 7 cột khỏi bảng `task_assignment_item_reports`:
- `manager_confirmed`
- `manager_confirmed_by`
- `manager_confirmed_at`
- `manager_confirm_note`
- `is_locked`
- `locked_at`
- `locked_by`

---

## 4. API Reference (sau thay đổi)

**Base path:** `/api/task-assignment-item-reports`

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

### 4.1 Danh sách báo cáo

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-reports` |
| **Query** | `task_assignment_item_id` (**required**, integer, ID công việc), `search` (số văn bản/trích yếu/nội dung), `sort_by` (id \| completed_at \| created_at), `sort_order` (asc \| desc), `limit` (1-100). |
| **Response** | Paginated collection; mỗi item kèm `reporter`, `attachments`. |

### 4.2 Chi tiết báo cáo

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **UrlParam** | `id` — ID báo cáo. |
| **Response** | Object báo cáo (ReportResource), kèm `reporter`, `attachments`. |

### 4.3 Tạo báo cáo

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/task-assignment-item-reports` |
| **Body** | `task_assignment_item_id` (required, integer, ID công việc), `completed_at` (optional, date, VD: `2026-04-30`), `report_document_number` (optional, string, số hiệu văn bản), `report_document_excerpt` (optional, string, trích yếu), `report_document_content` (optional, string, nội dung chi tiết), `attachments[]` (optional, file, tối đa 10 tệp, multipart/form-data). |
| **Response** | 201, object báo cáo + `"message": "Báo cáo đã được tạo thành công!"`. |

**Hành vi:** Khi tạo báo cáo, hệ thống tự động đánh dấu assignment của reporter là `done` và fire `TaskCompleted` event.

### 4.4 Cập nhật báo cáo

| | |
|---|---|
| **Method** | PUT / PATCH |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Body** | `completed_at` (optional, date), `report_document_number` (optional, string), `report_document_excerpt` (optional, string), `report_document_content` (optional, string), `attachments[]` (optional, file mới, append), `remove_attachment_ids` (optional, array[int], ID đính kèm cần xóa). |
| **Response** | Object báo cáo đã cập nhật + `"message": "Báo cáo đã được cập nhật!"`. |

**Xử lý file đính kèm:**
- `attachments[]` → upload file mới, thêm vào danh sách.
- `remove_attachment_ids` → xóa file theo ID.
- Không gửi → giữ nguyên.

### 4.5 Xóa báo cáo

| | |
|---|---|
| **Method** | DELETE |
| **Path** | `/api/task-assignment-item-reports/{id}` |
| **Response** | `{ "message": "Báo cáo đã được xóa thành công!" }`. |

### 4.6 Response mẫu (ReportResource)

```json
{
  "id": 1,
  "task_assignment_item_id": 1,
  "reporter": {
    "id": 2,
    "name": "Nguyễn Văn A",
    "email": "a@example.com"
  },
  "completed_at": "09:30:00 31/03/2026",
  "timing_status": "on_time",
  "report_document_number": "BC-01/2026",
  "report_document_excerpt": "Báo cáo kết quả thực hiện công việc tháng 4/2026.",
  "report_document_content": "Nội dung báo cáo đầy đủ...",
  "attachments": [
    {
      "id": 1,
      "media_id": 10,
      "file_name": "bao-cao.pdf",
      "sort_order": 0,
      "url": "/storage/10/bao-cao.pdf",
      "original_name": "bao-cao.pdf",
      "mime_type": "application/pdf",
      "size": 204800
    }
  ],
  "created_at": "09:30:00 31/03/2026",
  "updated_at": "09:30:00 31/03/2026"
}
```

**Giải thích các trường:**
- `timing_status`: `"on_time"` nếu `completed_at` ≤ deadline task, `"late"` nếu trễ hạn, `null` nếu chưa có `completed_at` hoặc task không có deadline.
- `reporter`: tự động gán theo `auth()->id()` khi tạo báo cáo.

---

## 5. Ảnh hưởng đến Frontend

### 5.1 Cần xóa hoặc disable

| Vị trí | Hành động |
|--------|-----------|
| Nút "Xác nhận báo cáo" trên từng report | **Xóa** — endpoint `PATCH /{id}/confirm` không còn tồn tại |
| Hiển thị trạng thái `manager_confirmed`, `is_locked` | **Xóa** — các trường này không còn trong response |
| Hiển thị người xác nhận, thời gian xác nhận | **Xóa** — `manager_confirmed_by`, `manager_confirmed_at` không còn |
| Permission check `task-assignment-item-reports.confirm` | **Xóa** — permission này không còn được seed |
| Guard "không thể sửa/xóa báo cáo đã khóa" | **Xóa** — không còn cơ chế khóa báo cáo |

### 5.2 Không thay đổi

| Vị trí | Ghi chú |
|--------|---------|
| CRUD báo cáo (index, show, store, update, destroy) | Vẫn hoạt động bình thường |
| `markDone` trên công việc | Vẫn hoạt động, nhưng **không còn** yêu cầu phải có báo cáo được xác nhận trước |
| Response format của report | Chỉ bỏ 7 trường approve/lock, các trường còn lại không đổi |

---

## 6. Luồng nghiệp vụ mới

```
Nhân viên tạo báo cáo (store report)
  → Báo cáo được lưu, assignment của reporter tự động chuyển sang "done"
  → Fire TaskCompleted event (gửi thông báo cho manager)

Manager xem danh sách báo cáo của công việc
  → Khi công việc đã hoàn thành, manager gọi markDone
  → Công việc chuyển sang done, fire TaskConfirmed event
```

**So với luồng cũ:** Đã bỏ bước "Manager xác nhận từng báo cáo (confirm report)" — bước này không còn cần thiết.
