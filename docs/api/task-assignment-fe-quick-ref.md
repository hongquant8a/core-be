# TaskAssignment FE Quick Reference

## CASL abilities

```js
// Báo cáo
$can('confirm', 'TaskAssignmentItemReport')   // duyệt + khóa report

// Công việc
$can('markDone', 'TaskAssignmentItem')        // đánh dấu hoàn thành
$can('updateProgress', 'TaskAssignmentItem')  // cập nhật tiến độ
$can('changeStatus', 'TaskAssignmentItem')    // đổi processing_status
```

## API endpoints (chỉ những endpoint MỚI / THAY ĐỔI)

```
# Confirm + lock report
PATCH /api/task-assignment-item-reports/{id}/confirm
Body: { confirm_note?: string }

# Đánh dấu hoàn thành task
PATCH /api/task-assignment-items/{id}/mark-done
Body: (none)
Yêu cầu: ≥1 report đã confirmed + task chưa done/cancelled

# Submit báo cáo (auto: assignment_status='done' cho reporter)
POST /api/task-assignment-item-reports
Body: { task_assignment_item_id, report_document_content?, report_document_number?, report_document_excerpt?, completed_at?, attachments[]? }

# Cập nhật tiến độ task
PATCH /api/task-assignment-items/{id}/progress
Body: { processing_status?, completion_percent? }

# REMOVED:
PATCH /api/task-assignment-items/{id}/confirm-done   ❌ KHÔNG CÒN
```

## `processing_status` (6 giá trị)

| Value | Label | User chọn? | Auto by BE? |
|---|---|---|---|
| `todo` | Chưa bắt đầu | ✓ | – |
| `in_progress` | Đang thực hiện | ✓ | – |
| `reported` | Đã báo cáo | ✓ | – |
| `paused` | Tạm dừng | ✓ | – |
| `cancelled` | Đã hủy | ✓ | – |
| `done` | Hoàn thành | ✗ | ✓ qua `mark-done` |

→ **Dropdown user render 5 option** (loại `done`).
→ Task ở `done` → dropdown disabled, badge read-only.
→ Truyền `done` vào endpoint `change-status`/`update-progress`/`store`/`update`/`bulk-update-status` → **422**.

## Timing (đúng hạn / trễ hạn)

**2 khái niệm khác nhau, ở 2 level khác nhau:**

### Task level — `is_overdue` (computed flag)
Task chưa hoàn thành mà quá `end_at`. Hiển thị: badge "Đang trễ hạn" (cảnh báo).
```json
{ "processing_status": "in_progress", "end_at": "2026-04-20", "is_overdue": true }
```

Filter:
```
GET /api/task-assignment-items?is_overdue=1
```

### Report level — `timing_status` (computed)
Báo cáo so với deadline task. Giá trị: `on_time` | `late` | `null`.
```json
{ "completed_at": "25/04/2026", "timing_status": "late" }
```
- `on_time` — `report.completed_at ≤ task.end_at`
- `late` — `report.completed_at > task.end_at`
- `null` — thiếu `completed_at` hoặc task `no_deadline`

FE: badge "Đúng hạn" (xanh) / "Trễ hạn" (cam) trên mỗi report.

### Breaking
- `processing_status === 'overdue'` → KHÔNG còn (enum đã gỡ).
- `is_late_completed` trên task → KHÔNG còn (dùng `report.timing_status` thay).
- Stats `on_time_count` / `overdue_done_count` → KHÔNG còn.

## Report fields mới (response)

```ts
{
  manager_confirmed: boolean
  manager_confirmed_by: number | { id, name, email } | null
  manager_confirmed_at: string | null
  manager_confirm_note: string | null
  is_locked: boolean
  locked_at: string | null
  locked_by: number | { id, name, email } | null
}
```

→ `is_locked === true` → ẩn nút Sửa/Xóa report. PATCH/DELETE → 422.

## Flow chuẩn

```
1. Manager:    POST   /task-assignment-documents          → tạo văn bản (draft)
2. Manager:    POST   /task-assignment-items              → tạo công việc (todo)
3. Manager:    PATCH  /task-assignment-documents/{id}/change-status → issued

4. Assignee:   PATCH  /task-assignment-items/{id}/progress    → in_progress, %
5. Assignee:   POST   /task-assignment-item-reports           → submit báo cáo
                                                              ↳ BE auto: assignment_status='done' cho reporter

6. Manager:    PATCH  /task-assignment-item-reports/{id}/confirm  → lock report
7. Manager:    PATCH  /task-assignment-items/{id}/mark-done       → task done
                                                                  ↳ BE set: status=done, completion=100, completed_at=now
```

## Permission mới (Spatie)

| Name | Roles |
|---|---|
| `task-assignment-item-reports.confirm` | Super Admin + Admin |
| `task-assignment-items.markDone` | Super Admin + Admin + Quản trị |

## Multi-department (nhỏ)

User có thể thuộc nhiều phòng ban. Record `task_assignment_users` thêm field `is_primary` (boolean). FE hiển thị badge "primary" cho phòng ban chính.

## Đầy đủ chi tiết

[task-assignment-corrections-changelog.md](task-assignment-corrections-changelog.md) — changelog chi tiết breaking changes + migration guide.
