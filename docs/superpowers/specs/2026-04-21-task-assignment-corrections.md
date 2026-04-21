# TaskAssignment — Spec Corrections & Alignment

**Date:** 2026-04-21
**Base spec:** [phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md](../../../phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md)
**Status:** Approved, ready for implementation plan.

## 1. Background

Spec gốc (2026-04-01) thiết kế module TaskAssignment độc lập. Sau ~3 tháng triển khai, code đã deviation một số điểm. Doc này chốt:
- Deviation nào **giữ** (accept, update spec note).
- Deviation nào **fix về spec**.
- Missing feature nào **implement bây giờ**.

Deep-dive analysis đầy đủ: xem conversation hội thoại.

## 2. Decisions

### 2.1 `organization_id` — ACCEPT (giữ code, update spec)

**Deviation:** Tất cả bảng TaskAssignment đều có `organization_id` — trái spec §4.4 "module vận hành độc lập, không dùng `organization_id`".

**Quyết định:** **Giữ nguyên code.** Lý do:
- Spatie Permission teamsMode yêu cầu `organization_id` trong `model_has_roles` (non-null).
- Module sử dụng role/permission scope theo organization → phải có `organization_id` để filter dữ liệu khớp scope quyền.
- Rollback quá tốn kém, ảnh hưởng toàn bộ service/query đã viết.

**Action:** **Update spec gốc** (`phan-tich-module-quan-ly-giao-viec-lien-phong-ban.md` §4.4) để ghi nhận organization_id là bắt buộc (do Spatie teamsMode).

### 2.2 `task_assignment_users` — FIX (user có thể nhiều phòng ban)

**Current state:**
- Unique key `(user_id, organization_id)` → 1 user = 1 record/tổ chức.
- Không có cột `is_primary`.

**Required:**
- User có thể link **nhiều** `task_assignment_department_id` trong cùng 1 organization.
- Có cờ `is_primary` để FE mặc định phòng ban chính.

**Migration changes:**
```sql
-- Bỏ unique cũ
DROP INDEX task_assignment_users_user_id_organization_id_unique ON task_assignment_users;

-- Thêm cột
ALTER TABLE task_assignment_users
  ADD COLUMN is_primary BOOLEAN NOT NULL DEFAULT FALSE AFTER task_assignment_department_id;

-- Thêm unique mới
CREATE UNIQUE INDEX task_assignment_users_user_dept_org_unique
  ON task_assignment_users(user_id, task_assignment_department_id, organization_id);
```

**Business rules:**
- User có thể có **0-nhiều** record active, nhưng **tối đa 1 record là `is_primary=true` trong cùng organization**.
- Khi tạo record đầu tiên cho user trong org → auto set `is_primary=true`.
- Khi set 1 record = primary → auto set các record cùng (user, org) khác thành `is_primary=false`.

**Service layer:**
- `TaskAssignmentDepartmentService::attachUser($user, $deptId, $isPrimary=false)` — handle primary sync logic.
- `TaskAssignmentDepartmentService::setPrimary($userId, $deptId)` — đổi primary.
- Query mặc định cho FE filter: chọn record `is_primary=true` nếu có, else record đầu tiên.

### 2.3 `task_assignment_item_reports` — FIX (theo spec §3.9)

**Missing columns (7):**
```php
$table->boolean('manager_confirmed')->default(false);
$table->unsignedBigInteger('manager_confirmed_by')->nullable();
$table->dateTime('manager_confirmed_at')->nullable();
$table->text('manager_confirm_note')->nullable();
$table->boolean('is_locked')->default(false);
$table->dateTime('locked_at')->nullable();
$table->unsignedBigInteger('locked_by')->nullable();

$table->foreign('manager_confirmed_by')->references('id')->on('users')->nullOnDelete();
$table->foreign('locked_by')->references('id')->on('users')->nullOnDelete();

$table->index(['manager_confirmed', 'is_locked']);
```

**New endpoint:** `PATCH /api/task-assignment-item-reports/{id}/confirm`

**Request:**
```json
{
  "confirm_note": "Đạt quy định"   // optional
}
```

**Behavior:**
- Validate: report chưa `is_locked=true`.
- Set: `manager_confirmed=true`, `manager_confirmed_by=auth_user_id`, `manager_confirmed_at=now()`, `manager_confirm_note=input`.
- Set: `is_locked=true`, `locked_at=now()`, `locked_by=auth_user_id` (tự khóa khi confirm — 1-step per spec §3.9 note).
- Response 200: report đã confirm.

**Permission:** `task-assignment-item-reports.confirm` (mới).

**Update logic:** `TaskAssignmentReportService::update()` — reject update nếu `is_locked=true`.

**Out of scope v1:**
- Endpoint "unlock đặc biệt" (spec §9.3.C "confirmed_locked -> draft_report exception"). Để v2 khi có yêu cầu cụ thể về audit.
- State machine "đóng công việc" (spec §9.3.D) khi báo cáo cuối confirmed + locked — giữ manual flow ban đầu, không auto close.

### 2.4 `department_id` vs `assigned_department_id` — ACCEPT (verified snapshot)

**Verification:** `TaskAssignmentItemService.php:260` tạo record với `department_id` copy từ input khi create. Không có UPDATE nào sửa cột này sau đó. → Semantic = snapshot (khớp ý định spec `assigned_department_id`).

**Action:** **Update spec gốc** §3.7 và §5.2 note "cột `department_id` trong code = `assigned_department_id` trong spec (snapshot at assignment time)". Không rename code (tốn kém, không tăng giá trị).

### 2.5 Reminder system — UPDATE SPEC (chấp nhận Core)

**Current code** đã refactor dùng Core notification system (`notification_schedules` + `notification_event_configs`) — **kiến trúc đã ổn, scalable, tái sử dụng tốt**.

**Code-level fix cần làm (bug):**
- `TaskReminderStatusEnum` hiện giữ `pending/sent/failed` nhưng migration `2026_04_16_100004_restructure_task_assignment_reminders_table.php` dùng `pending/fired/cancelled`. **Enum lệch migration** → sync enum về khớp.

**Verify cần làm:**
- Idempotency chống gửi reminder trùng (spec §6.3). Check `NotificationDispatcher` xem có key idempotent không. Nếu không → add.

**Spec-level update** (spec gốc):
- §3.12: update schema `task_assignment_reminders` theo migration mới (notification_schedule_id FK, moment enum, status pending/fired/cancelled, fired_at).
- §3.13 `task_assignment_notification_settings`: **remove**, thay bằng note "dùng Core `notification_event_configs` + `notification_schedules` via `module_key=task_assignment` + event keys `ReminderBefore/ReminderOn/ReminderAfter`".
- §6.2: "Core `ReminderScheduler` chạy via Laravel Scheduler, không có command riêng cho module."
- §6.5: **remove** (không có endpoint riêng). API config dùng `/api/task-assignment/notification-config/*` (Core shared).

## 3. Out of scope

Các việc thiếu khác trong analysis mà chưa làm ở đợt này:
- Bảng `task_assignment_item_user_transfers` + endpoint `POST /items/{id}/transfers`.
- Bảng `task_assignment_item_notes` + endpoints notes.
- Endpoint attachment cho document (`POST/DELETE/PATCH /{id}/attachments`).
- Controller cho `my-received-tasks` / `my-assigned-tasks` (permission đã có, route chưa).

Làm ở đợt sau nếu cần.

## 4. Acceptance criteria

- [ ] Migration mới chạy thành công: alter `task_assignment_users` (bỏ unique cũ, thêm is_primary, unique mới).
- [ ] Migration mới chạy thành công: alter `task_assignment_item_reports` (7 cột confirm/lock).
- [ ] `TaskAssignmentDepartmentService::attachUser()` hỗ trợ is_primary, đồng bộ tự động.
- [ ] Endpoint `PATCH /api/task-assignment-item-reports/{id}/confirm` hoạt động (200 khi confirm, 409/422 khi đã locked).
- [ ] `TaskAssignmentReportService::update()` reject khi `is_locked=true` (403 hoặc 422).
- [ ] Permission `task-assignment-item-reports.confirm` thêm vào `PermissionSeeder`, Super Admin + Admin auto có.
- [ ] `TaskReminderStatusEnum` đồng bộ với migration (`pending/fired/cancelled`).
- [ ] Idempotency verified trong dispatcher.
- [ ] Spec gốc `phan-tich-module...` có ghi chú correction cho §3.1, §3.7, §3.9, §3.12, §3.13, §4.4, §5.2, §6.2, §6.5.
- [ ] Tests cover: multi-department attach, primary sync, confirm endpoint success/reject-when-locked.
- [ ] Full test suite pass (no regression).
