# Fix Code Review Issues — Module TaskAssignment & Scheduling

**Ngày:** 08/06/2026

Tham chiếu: [feedback.md](../../feedback.md)

---

## 1. Các issue đã xử lý

| ID | Vấn đề | Mức độ | Ảnh hưởng FE |
|----|--------|--------|---------------|
| ISSUE-02 | reject() bỏ qua $note | Cao | Có |
| ISSUE-03 | Scheduling bypass MediaService, thiếu cleanup | Cao | Không |
| ISSUE-04 | Cross-tenant risk trong bulk ops | Cao | Không |
| ISSUE-05 | bulkUpdateStatus logic thừa | Trung bình | Không |
| ISSUE-06 | getWeeks() mutate internal Eloquent state | Trung bình | Không |
| ISSUE-07 | Hard-code role names bằng string | Thấp | Không |
| ISSUE-08 | Dead code block trong update() | Thấp | Không |
| ISSUE-09 | Sort order race condition khi insert | Thấp | Không |
| ISSUE-10 | File upload 2 key ambiguous | Thấp | **Có (breaking)** |

ISSUE-01 (N+1 Query) được tách ra PR riêng do độ phức tạp.

---

## 2. Thay đổi chi tiết

### 2.1 ISSUE-02 — reject() lưu rejection_note

**Trước:** Controller nhận `rejection_note` từ FE, truyền vào `ScheduleService::reject()` nhưng Service không lưu vào DB → mất dữ liệu.

**Sau:** `rejection_note` được lưu vào cột `rejection_note` của bảng `schedules`.

**Migration:** `database/migrations/2026_06_08_110000_add_rejection_note_to_schedules.php` — thêm cột `rejection_note` (text, nullable) sau `approval_status`.

**File thay đổi:**
- [Schedule.php:23](app/Modules/Scheduling/Models/Schedule.php#L23) — thêm `'rejection_note'` vào `$fillable`
- [ScheduleService.php:360-367](app/Modules/Scheduling/Services/ScheduleService.php#L360-L367) — lưu `$note` vào DB

**Ảnh hưởng FE:**
- Endpoint `POST /api/scheduling/schedules/{id}/reject` với body `{"rejection_note": "..."}` — giờ dữ liệu sẽ thực sự được lưu và trả về trong response ScheduleResource.

---

### 2.2 ISSUE-03 — try/catch cleanup file upload

**Trước:** `store()` và `update()` upload file trực tiếp qua `Storage::disk('public')`, không có cơ chế cleanup nếu transaction fail → file orphan trên disk.

**Sau:** Wrap `DB::transaction()` trong `try/catch`. Nếu transaction fail → gọi `MediaService::cleanupStoredFiles()` để xóa file đã upload.

**File thay đổi:**
- [ScheduleService.php](app/Modules/Scheduling/Services/ScheduleService.php) — inject `MediaService`, thêm try/catch trong `store()` và `update()`, `uploadAttachment()` track `$storedFiles`

**Ảnh hưởng FE:** Không. Response và API không đổi.

---

### 2.3 ISSUE-04 — Explicit organization_id trong bulk ops

**Trước:** `bulkDestroy()` và `bulkUpdateStatus()` dùng `whereIn('id', $ids)` không có explicit `organization_id` → dù đã có TenantModel global scope nhưng thiếu an toàn cho destructive ops.

**Sau:** Thêm `->where('organization_id', getPermissionsTeamId())` trước `whereIn('id', $ids)`.

**File thay đổi:**
- [ScheduleService.php:293-308](app/Modules/Scheduling/Services/ScheduleService.php#L293-L308)
- [TaskAssignmentItemService.php:181-188](app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php#L181-L188)

**Ảnh hưởng FE:** Không. Response và behavior không đổi.

---

### 2.4 ISSUE-05 — Gộp logic clear completed_at

**Trước:** `bulkUpdateStatus()` thực hiện 2 query riêng biệt — query 2 kiểm tra `processing_status != done` nhưng query 1 vừa set xong nên điều kiện luôn true.

**Sau:** `buildStatusUpdateData()` tự set `completed_at = null` khi status khác `done`, `bulkUpdateStatus()` chỉ cần 1 query duy nhất.

**File thay đổi:**
- [TaskAssignmentItemService.php:263-273](app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php#L263-L273)

**Ảnh hưởng FE:** Không. Behavior không đổi — khi chuyển task từ done sang trạng thái khác, `completed_at` vẫn bị clear.

---

### 2.5 ISSUE-06 — Fix scopeFilter + xóa hack Eloquent

**Trước:** `getWeeks()` truyền `sort_by=''` để bypass default sort trong `scopeFilter`, rồi mutate `$query->getQuery()->orders = null` để xóa order đã thêm — phụ thuộc internal Eloquent structure.

**Sau:** `scopeFilter` xử lý 3 case:
- `sort_by` có giá trị thật → sort theo cột đó
- `sort_by=''` (chuỗi rỗng) → không sort (dành cho `getWeeks()`)
- Không có key `sort_by` → default sort `date_time asc` (giữ backward compat)

`getWeeks()` không còn hack `orders = null`.

**File thay đổi:**
- [Schedule.php:204-218](app/Modules/Scheduling/Models/Schedule.php#L204-L218)
- [ScheduleService.php:112-130](app/Modules/Scheduling/Services/ScheduleService.php#L112-L130)

**Ảnh hưởng FE:** Không. Response `GET /api/scheduling/weeks` không đổi.

---

### 2.6 ISSUE-07 — Constant ADMIN_ROLES

**Trước:** `['Quản trị', 'Super Admin', 'Admin']` hardcode inline trong `applyDepartmentRestriction()`.

**Sau:** Extract ra `private const ADMIN_ROLES`.

**File thay đổi:**
- [TaskAssignmentItemService.php:355](app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php#L355)

**Ảnh hưởng FE:** Không.

**Ghi chú:** Đây là giải pháp tạm thời. Về lâu dài cần chuyển sang permission-based check thay vì role name. Hiện tại pattern này tồn tại ở nhiều nơi trong codebase (SchedulePolicy, HasMeetingRole, TaskAssignmentNoteService...), cần 1 PR riêng để xử lý toàn diện.

---

### 2.7 ISSUE-08 — Xóa dead code block

**Trước:** Block `if` rỗng trong `update()`:
```php
if ($statusVal === ScheduleStatus::PUBLISHED->value) {
    // Observer tự động fire SchedulePublished hoặc ScheduleUpdated
}
```

**Sau:** Xóa toàn bộ block.

**File thay đổi:**
- [ScheduleService.php:279](app/Modules/Scheduling/Services/ScheduleService.php) (đã xóa)

**Ảnh hưởng FE:** Không.

---

### 2.8 ISSUE-09 — lockForUpdate() tránh race condition sort_order

**Trước:** Khi insert lịch, `increment('sort_order')` không lock → 2 user insert cùng slot có thể tạo sort_order trùng.

**Sau:** Thêm `->lockForUpdate()` trước `increment('sort_order')`.

**File thay đổi:**
- [ScheduleService.php:183](app/Modules/Scheduling/Services/ScheduleService.php#L183)

**Ảnh hưởng FE:** Không.

---

### 2.9 ISSUE-10 — Chọn 1 key upload duy nhất `attachments`

**Trước:** Controller merge cả `files` và `attachments` từ request → nếu FE gửi cả 2 key, file bị upload đôi.

**Sau:** Chỉ nhận key `attachments`. Bỏ key `files`.

**File thay đổi:**
- [ScheduleController.php:192,226](app/Modules/Scheduling/Controllers/ScheduleController.php) — `$request->file('attachments') ?? []`
- [StoreScheduleRequest.php](app/Modules/Scheduling/Requests/StoreScheduleRequest.php) — xóa rule `'files'`
- [UpdateScheduleRequest.php](app/Modules/Scheduling/Requests/UpdateScheduleRequest.php) — xóa rule `'files'`

**Ảnh hưởng FE — BREAKING:**
- Endpoint `POST /api/scheduling/schedules` — key upload đổi từ `files`/`attachments` → chỉ `attachments`
- Endpoint `POST /api/scheduling/schedules/{id}` (update) — tương tự
- FE cần kiểm tra và đảm bảo không còn gửi key `files`, chỉ dùng `attachments`

---

## 3. Migration mới

Chạy sau khi deploy:

```bash
php artisan migrate
```

Migration được thêm:
- `2026_06_08_110000_add_rejection_note_to_schedules.php`

---

## 4. API Reference (không đổi ngoại trừ ISSUE-10)

### 4.1 Tạo lịch

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/scheduling/schedules` |
| **Thay đổi** | Key upload file: dùng `attachments[]`, **không còn hỗ trợ `files[]`** |

### 4.2 Cập nhật lịch

| | |
|---|---|
| **Method** | POST (multipart) |
| **Path** | `/api/scheduling/schedules/{id}` |
| **Thay đổi** | Key upload file: dùng `attachments[]`, **không còn hỗ trợ `files[]`** |

### 4.3 Từ chối duyệt

| | |
|---|---|
| **Method** | POST |
| **Path** | `/api/scheduling/schedules/{id}/reject` |
| **Thay đổi** | `rejection_note` trong body được lưu vào DB và trả về trong response |

---

## 5. Ảnh hưởng đến Frontend

### Cần thay đổi

| Vị trí | Hành động |
|--------|-----------|
| Upload file trong form tạo/sửa lịch | Đổi key từ `files`/`attachments` → chỉ dùng `attachments` |
| Hiển thị lý do từ chối | Trường `rejection_note` giờ có trong response ScheduleResource |

### Không thay đổi

| Vị trí | Ghi chú |
|--------|---------|
| Tất cả endpoint khác của Scheduling | Response format, status code giữ nguyên |
| Tất cả endpoint của TaskAssignment | Giữ nguyên |
| GET /api/scheduling/weeks | Response không đổi |
| Thao tác bulk (delete, update status) | Behavior không đổi |
