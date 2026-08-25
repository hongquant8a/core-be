# Công việc — báo cáo, ghi chú, điều chuyển nay kiểm người liên quan

> Ngày tạo: 01:28:55 25/08/2026
> Cập nhật lần cuối: 02:46:45 25/08/2026

Nhóm endpoint báo cáo / ghi chú / điều chuyển trước đây chỉ gác `permission:...`, không kiểm
thao tác đó nhắm vào **công việc của ai**. Có quyền là gọi được lên bất kỳ id nào. Nay toàn bộ
đã gác bằng policy: **quyền** mở cửa, **phạm vi** quyết định làm được trên bản ghi nào.

Đây là phần §4.2 của tài liệu bàn giao đợt tái cấu trúc.

---

## 1. Thay đổi hành vi API

| Method | Path | Trước | Sau |
|---|---|---|---|
| `PUT/PATCH` | `/api/task-assignment-item-reports/{id}` | có quyền là qua | quyền **và** thuộc phạm vi, không thì **403** |
| `DELETE` | `/api/task-assignment-item-reports/{id}` | có quyền là qua | quyền **và** thuộc phạm vi, không thì **403** |

`index`, `show`, `store` giữ nguyên — vẫn chỉ gác `permission:my-received-tasks.report`.

**Phạm vi** (`TaskAssignmentItemReportPolicy`) — được thao tác nếu là một trong:

- người nộp báo cáo (`reporter_user_id`);
- người giao việc của công việc chứa báo cáo (`task_assignment_items.assigned_by`);
- có quyền `task-overview.manageAll` (bypass toàn bộ).

Người **cùng được giao** công việc nhưng không phải người nộp thì **không** sửa/xoá được —
báo cáo là phát ngôn của một người cụ thể.

## 2. Ability mới cho FE

`CaslAbilityConverter` nay sinh thêm:

```json
{ "action": "destroy", "subject": "TaskAssignmentItemReports" }
```

từ quyền `my-received-tasks.report`. Trước đây thiếu alias này nên `can('destroy',
'TaskAssignmentItemReports')` luôn trả `false` — nút "Xoá báo cáo" bị ẩn với mọi tài khoản
trừ Super Admin, dù route `DELETE` vẫn cho gọi. **FE nay hiện được nút Xoá**; nếu người dùng
không thuộc phạm vi thì server trả 403, FE báo lỗi bình thường.

## 3. Lưu ý: không có "xác nhận báo cáo"

Khái niệm này đã bị gỡ khỏi BE từ migration
`2026_06_08_100000_drop_approval_columns_from_task_assignment_item_reports` — 7 cột
`manager_confirmed`, `manager_confirmed_by`, `manager_confirmed_at`,
`manager_confirm_note`, `is_locked`, `locked_at`, `locked_by` đều đã drop.

Không có route `PATCH /api/task-assignment-item-reports/{id}/confirm` và chưa từng có.
FE nào còn gọi endpoint đó, còn đọc `manager_confirmed*` hay còn hiện nút "Xác nhận báo cáo"
thì là code chết — `core-miniapp` đã gỡ, `core-fe` (`TaskItemModal.vue`,
`TaskReportService.js`) **vẫn còn**.

## 4. Ghi chú công việc

| Method | Path | Trước | Sau |
|---|---|---|---|
| `POST` | `/api/task-assignment-items/{id}/notes` | có quyền là qua | **403** nếu không phải người liên quan |

Đây là lỗ hổng dùng được ngay: `my-received-tasks.note` nằm trong bộ quyền mặc định của vai trò
**Nhân viên**, nên trước đây mọi nhân viên ghi chú được vào công việc bất kỳ.

Phạm vi (`TaskAssignmentItemPolicy::note`): người giao việc (`assigned_by`) hoặc người được giao
(pivot `task_assignment_item_user`).

## 5. Điều chuyển công việc

| Method | Path | Trước | Sau |
|---|---|---|---|
| `POST` | `/api/task-assignment-items/{id}/transfers` | có quyền là qua | **403** nếu không phải người liên quan |

`GET` (lịch sử điều chuyển) giữ nguyên gác permission.

Service có nhánh "quản lý chuyển hộ": người gọi không nằm trong pivot thì nó lấy assignee `main`
để chuyển. Nhánh đó **vẫn giữ** — đúng cho người giao việc — nhưng nay chỉ người liên quan mới
vào tới được. Phạm vi giống mục 4.

## 6. Đọc báo cáo

| Method | Path | Trước | Sau |
|---|---|---|---|
| `GET` | `/api/task-assignment-item-reports?task_assignment_item_id=` | có quyền là qua | **403** nếu ngoài phạm vi |
| `GET` | `/api/task-assignment-item-reports/{id}` | có quyền là qua | **403** nếu ngoài phạm vi |
| `POST` | `/api/task-assignment-item-reports` | có quyền là qua | **403** nếu không phải người liên quan |

**Phạm vi ĐỌC rộng hơn phạm vi GHI một bậc**: ngoài người liên quan tới công việc còn cho người
có `task-overview.index` hoặc `presentation.index`. Căn cứ là ngữ nghĩa sẵn có của cây quyền —
7 route thống kê (`stats-by-user`, `stats-by-department`, `overdue`, …) đã gác đúng hai quyền
này, tức chúng vốn là quyền đọc dữ liệu công việc toàn tổ chức. Báo cáo là một mặt của cùng khối
dữ liệu đó.

**Nộp báo cáo (`POST`) thì KHÔNG nới**: `task-overview.index` là quyền đọc dữ liệu tổng hợp,
không hàm ý được ghi vào công việc của người khác.

## 7. Việc còn lại quanh khu vực này

Mục §4.1 và §4.3 của tài liệu bàn giao vẫn mở: `GET /task-assignment-items` và `GET /stats` chưa
giới hạn phạm vi ở server (việc tách "đang giao / được giao" vẫn nằm ở FE qua query param
`assignee_id`), và `applyDepartmentRestriction()` chưa phủ hết các endpoint thống kê.
