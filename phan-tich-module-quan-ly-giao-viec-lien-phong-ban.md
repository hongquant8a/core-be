# Phân tích giải pháp module quản lý giao việc liên phòng ban

**Ngày tạo:** 2026-04-01  
**Mục đích:** Thiết kế giải pháp triển khai module quản lý văn bản giao việc và danh sách công việc liên phòng ban, đáp ứng theo dõi tiến độ theo thời gian, thống kê tổng hợp và báo cáo nhắc việc.

---

## 1. Phạm vi bài toán

### 1.1 Đối tượng quản lý

1. **Văn bản giao việc** (đầu việc cấp văn bản):
   - Tên văn bản giao việc
   - Tóm tắt nội dung
   - Ngày ban hành
   - Loại văn bản giao việc
   - Trạng thái: `draft` (lưu tạm), `issued` (ban hành)
   - Tệp đính kèm: cho phép nhiều

2. **Công việc thuộc văn bản**:
   - Tên công việc
   - Mô tả
   - Đơn vị thực hiện (phòng ban nội bộ của module TaskAssignment)
   - Ngày giờ bắt đầu
   - Ngày giờ kết thúc
   - Loại thời hạn: có thời hạn / không có thời hạn
   - Loại công việc
   - Trạng thái xử lý
   - Phần trăm hoàn thành
   - Mức độ ưu tiên

### 1.2 Mục tiêu quản trị

- Theo dõi công việc theo phòng ban theo từng mốc thời gian.
- Tổng hợp thống kê theo trạng thái, hạn xử lý, loại công việc, loại văn bản.
- Tự động nhắc việc (sắp đến hạn, quá hạn, chưa bắt đầu).

---

## 2. Thiết kế module theo chuẩn dự án

Theo quy ước hiện tại, đề xuất tạo module mới: `TaskAssignment` (hoặc tên nghiệp vụ tương đương).

### 2.1 Cấu trúc thư mục đề xuất

```text
app/Modules/TaskAssignment/
├── Enums/
│   ├── TaskAssignmentDocumentStatusEnum.php
│   ├── TaskDeadlineTypeEnum.php
│   ├── TaskProgressStatusEnum.php
│   └── TaskReminderStatusEnum.php
├── Models/
│   ├── TaskAssignmentDocument.php
│   ├── TaskAssignmentType.php
│   ├── TaskAssignmentItem.php
│   ├── TaskAssignmentItemType.php
│   ├── TaskAssignmentReminder.php
│   └── TaskAssignmentItemDepartment.php
├── Requests/
├── Resources/
├── Services/
│   ├── TaskAssignmentDocumentService.php
│   ├── TaskAssignmentReportService.php
│   └── TaskAssignmentReminderService.php
├── Exports/
├── Imports/
├── Routes/
│   ├── task_assignment_document.php
│   ├── task_assignment_type.php
│   └── task_assignment_item_type.php
└── ...
```

### 2.2 Endpoint chuẩn cho resource chính

Áp dụng đủ bộ action chuẩn dự án:
- `stats`
- `index`
- `show`
- `store`
- `update`
- `destroy`
- `bulkDestroy`
- `bulkUpdateStatus`
- `changeStatus`
- `export`
- `import`

Tối thiểu triển khai cho:
- Văn bản giao việc (`task-assignment-documents`)
- Danh mục loại văn bản (`task-assignment-types`)
- Danh mục loại công việc (`task-assignment-item-types`)

---

## 3. Thiết kế dữ liệu (database)

## 3.1 Nguồn dữ liệu phòng ban để lọc user và thống kê

Mục đích: quản lý danh mục phòng ban nội bộ riêng của module `TaskAssignment`, phục vụ lọc user, phân quyền nghiệp vụ và thống kê.

**Bảng:** `task_assignment_departments`

Trường chính đề xuất:
- `id`
- `code` (unique, phục vụ import/report)
- `name`
- `description` (nullable)
- `status` (`active`/`inactive`)
- `sort_order` (default 0)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

Khuyến nghị:
- Không giao việc trực tiếp cho phòng ban, chỉ giao cho user.
- FE chọn `department_id` để lọc user thuộc phòng ban đó trước khi chọn người nhận việc.
- Quan hệ user-phòng ban do module `TaskAssignment` quản lý riêng (không phụ thuộc Core).

Để quản lý danh sách user thuộc phòng ban (phục vụ lọc khi giao việc), bổ sung bảng:

**Bảng:** `task_assignment_user_departments`

Trường chính:
- `id`
- `user_id` (FK -> `users`)
- `department_id` (FK -> `task_assignment_departments`)
- `is_primary` (bool, default true) - nếu user thuộc nhiều phòng ban, chọn phòng ban chính để FE mặc định
- `status` (`active`, `inactive`)
- `created_at`, `updated_at`

Ghi chú:
- Khi giao việc cho user, BE sẽ lưu `assigned_department_id` vào bản ghi phân công `task_assignment_item_user` để phục vụ thống kê về sau.

## 3.2 Bảng danh mục loại văn bản

**Bảng:** `task_assignment_types`  
Mục đích: quản lý loại văn bản giao việc.

Trường chính đề xuất:
- `id`
- `name`
- `description` (nullable)
- `status` (`active`/`inactive`)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

## 3.3 Bảng văn bản giao việc

**Bảng:** `task_assignment_documents`

Trường chính đề xuất:
- `id`
- `name` (tên văn bản giao việc)
- `summary` (tóm tắt nội dung)
- `issue_date` (ngày ban hành)
- `task_assignment_type_id` (FK -> `task_assignment_types`)
- `status` (`draft`, `issued`)
- `issued_at` (nullable, set khi ban hành)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

Index khuyến nghị:
- `(status)`
- `(issue_date)`
- `(task_assignment_type_id)`

## 3.4 Bảng đính kèm tệp của văn bản giao việc

Để đáp ứng yêu cầu 1 văn bản có nhiều tệp đính kèm, bổ sung bảng:

**Bảng:** `task_assignment_document_attachments`

Trường chính:
- `id`
- `task_assignment_document_id` (FK -> `task_assignment_documents`)
- `media_id` (FK -> bảng media dùng chung, lưu qua `MediaService`)
- `file_name` (tên hiển thị, nullable)
- `sort_order` (default 0)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

Ràng buộc:
- unique(`task_assignment_document_id`, `media_id`)
- index(`task_assignment_document_id`, `sort_order`)

Ghi chú triển khai:
- Upload/xóa tệp phải đi qua `App\Modules\Core\Services\MediaService`.
- Không lưu file trực tiếp trong service của module.

## 3.5 Bảng loại công việc

**Bảng:** `task_assignment_item_types`

Trường chính:
- `id`
- `name`
- `description` (nullable)
- `status`
- `created_by`, `updated_by`
- `created_at`, `updated_at`

## 3.6 Bảng công việc thuộc văn bản

**Bảng:** `task_assignment_items`

Trường chính:
- `id`
- `task_assignment_document_id` (FK -> `task_assignment_documents`)
- `name` (tên công việc)
- `description` (mô tả)
- `task_assignment_item_type_id` (FK -> `task_assignment_item_types`)
- `deadline_type` (`has_deadline`, `no_deadline`)
- `start_at` (datetime, nullable)
- `end_at` (datetime, nullable, bắt buộc khi `deadline_type = has_deadline`)
- `processing_status` (`todo`, `in_progress`, `done`, `overdue`, `paused`, `cancelled`)
- `completion_percent` (0-100, default 0)
- `priority` (`low`, `medium`, `high`, `urgent`)
- `completed_at` (nullable)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

Index khuyến nghị:
- `(task_assignment_document_id)`
- `(processing_status)`
- `(deadline_type, end_at)`
- `(task_assignment_item_type_id)`
- `(priority)`

## 3.7 Bảng liên kết công việc - người dùng thực hiện

Để giao việc đến từng cá nhân (phòng ban dùng để lọc user), bổ sung bảng:

**Bảng pivot:** `task_assignment_item_user`

Trường:
- `id`
- `task_assignment_item_id`
- `user_id` (FK -> `users`)
- `assigned_department_id` (FK -> `task_assignment_departments`) - phòng ban được gán tại thời điểm giao việc
- `assignment_role` (`main`, `support`) - người chủ trì/phối hợp
- `assignment_status` (`assigned`, `accepted`, `rejected`, `done`, `transferred`) - trạng thái nhận việc
- `assigned_at` (thời điểm giao)
- `accepted_at` (nullable)
- `completed_at` (nullable)
- `note` (nullable)
- `created_at`, `updated_at`

Ràng buộc:
- unique(`task_assignment_item_id`, `user_id`)
- index(`assigned_department_id`, `assignment_status`)

## 3.8 Bảng lịch sử điều chuyển công việc giữa người dùng

Để hỗ trợ trường hợp người dùng chuyển công việc cho người khác và vẫn lưu lại lịch sử, bổ sung bảng:

**Bảng:** `task_assignment_item_user_transfers`

Trường:
- `id`
- `task_assignment_item_id` (FK -> `task_assignment_items`)
- `from_user_id` (FK -> `users`)
- `to_user_id` (FK -> `users`)
- `transferred_by_user_id` (FK -> `users`) - người thực hiện điều chuyển
- `transferred_department_id` (FK -> `task_assignment_departments`) - phòng ban tại thời điểm điều chuyển
- `transferred_at` (datetime)
- `note` (nullable)
- `created_at`, `updated_at`

Ràng buộc:
- index(`task_assignment_item_id`, `transferred_at`)

## 3.9 Bảng báo cáo kết quả thực hiện công việc

Để quản lý nội dung người được giao báo cáo, bổ sung bảng:

**Bảng:** `task_assignment_item_reports`

Trường:
- `id`
- `task_assignment_item_id` (FK -> `task_assignment_items`)
- `reporter_user_id` (FK -> `users`)
- `completed_at` (datetime, ngày hoàn thành báo cáo)
- `report_document_number` (số ký hiệu văn bản báo cáo)
- `report_document_excerpt` (trích yếu văn bản báo cáo)
- `report_document_content` (nội dung văn bản báo cáo)
- `manager_confirmed` (bool, default false)
- `manager_confirmed_by` (FK -> `users`, nullable)
- `manager_confirmed_at` (datetime, nullable)
- `manager_confirm_note` (text, nullable, ghi chú xác nhận/không đạt)
- `is_locked` (bool, default false) - khóa báo cáo sau khi đã xác nhận
- `locked_at` (datetime, nullable)
- `locked_by` (FK -> `users`, nullable)
- `updated_at` (ngày cập nhật báo cáo gần nhất)
- `created_at`

Ràng buộc:
- index(`task_assignment_item_id`, `reporter_user_id`)
- index(`completed_at`)

Ghi chú:
- Mỗi công việc có thể có nhiều lần báo cáo theo tiến độ hoặc nhiều báo cáo từ các user phối hợp.
- Với nhu cầu hiện tại, chỉ cần 1 bước xác nhận đơn giản: quản lý bật `manager_confirmed=true` khi kiểm tra đạt quy định.
- Sau khi xác nhận đạt, báo cáo chuyển `is_locked=true` để không cho chỉnh sửa nội dung/file (trừ quyền mở khóa đặc biệt).

## 3.10 Bảng đính kèm tệp văn bản báo cáo

**Bảng:** `task_assignment_item_report_attachments`

Trường:
- `id`
- `task_assignment_item_report_id` (FK -> `task_assignment_item_reports`)
- `media_id` (FK -> bảng media dùng chung, upload qua `MediaService`)
- `file_name` (nullable)
- `sort_order` (default 0)
- `created_at`, `updated_at`

Ràng buộc:
- unique(`task_assignment_item_report_id`, `media_id`)

## 3.11 Bảng nhận xét, ghi chú và trao đổi (theo từng công việc)

Mục đích: mỗi công việc có luồng **trao đổi giữa quản lý và người thực hiện**, mỗi dòng là một lần gửi, **có dấu thời gian**, không sửa/xóa nội dung đã gửi (chỉ thêm mới để giữ lịch sử).

**Bảng:** `task_assignment_item_notes`

Trường:
- `id`
- `task_assignment_item_id` (FK -> `task_assignment_items`)
- `author_user_id` (FK -> `users`) - người gửi
- `author_role` (`manager`, `assignee`) - phân biệt quản lý hay người thực hiện (theo quyền tại thời điểm gửi)
- `content` (text) - nội dung nhận xét / ghi chú / trao đổi
- `created_at` (datetime) - thời điểm gửi, dùng làm dấu thời gian hiển thị timeline

Ràng buộc:
- index(`task_assignment_item_id`, `created_at`)

Quy tắc:
- Chỉ cho phép **thêm mới** (append-only); không cập nhật/xóa nội dung đã lưu (trừ quyền hệ thống đặc biệt nếu có).
- FE hiển thị theo thứ tự `created_at` tăng dần (timeline).

## 3.12 Bảng lưu nhắc việc/lịch sử gửi nhắc

**Bảng:** `task_assignment_reminders`

Trường:
- `id`
- `task_assignment_item_id`
- `remind_at` (thời điểm dự kiến nhắc)
- `sent_at` (nullable)
- `channel` (`system`, `email`, `zalo`, `sms`)
- `recipient_department_id` (nullable, tham chiếu phòng ban trong module `TaskAssignment`)
- `recipient_user_id` (nullable)
- `status` (`pending`, `sent`, `failed`)
- `error_message` (nullable)
- `created_at`, `updated_at`

## 3.13 Bảng cấu hình thông báo của module giao việc

Để cho phép bật/tắt linh hoạt các kênh gửi nhận thông báo và cấu hình mốc thời gian gửi nhắc, bổ sung:

**Bảng:** `task_assignment_notification_settings`

Trường:
- `id`
- `name` (tên cấu hình, ví dụ: cấu hình mặc định)
- `is_active` (bool, default true)
- `channel_email_enabled` (bool)
- `channel_sms_enabled` (bool)
- `channel_zalo_enabled` (bool)
- `channel_firebase_enabled` (bool)
- `channel_system_enabled` (bool, thông báo nội bộ)
- `lead_days_json` (json, ví dụ: `[7,3,1]` - trước hạn bao nhiêu ngày)
- `on_due_day_enabled` (bool, gửi ngày đến hạn)
- `overdue_days_json` (json, ví dụ: `[1,3,7]` - quá hạn bao nhiêu ngày)
- `send_time` (time, ví dụ: `08:00:00`)
- `timezone` (varchar, ví dụ: `Asia/Ho_Chi_Minh`)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

Ghi chú:
- Có thể dùng 1 cấu hình mặc định toàn hệ thống hoặc mở rộng nhiều cấu hình theo loại công việc.
- Các giá trị json cần validate là mảng số nguyên dương, không trùng lặp.

---

## 4. Quy tắc nghiệp vụ trọng tâm

## 4.1 Luồng trạng thái văn bản

- `draft`: cho phép chỉnh sửa thông tin văn bản, thêm/sửa/xóa công việc.
- `draft`: cho phép thêm/xóa/sắp xếp tệp đính kèm của văn bản.
- `issued`: khóa các trường cốt lõi (hoặc chỉ cho phép chỉnh sửa có kiểm soát theo quyền nâng cao).
- Khi chuyển `draft -> issued`:
  - validate đầy đủ dữ liệu công việc bắt buộc.
  - validate danh sách tệp đính kèm hợp lệ (nếu có cấu hình bắt buộc tệp).
  - set `issued_at`.
  - sinh lịch nhắc việc ban đầu (nếu có hạn).

## 4.2 Luồng thời hạn công việc

- `deadline_type = has_deadline`:
  - bắt buộc có `end_at`.
  - nếu `end_at < hiện tại` và chưa hoàn thành thì đánh dấu `overdue`.
- `deadline_type = no_deadline`:
  - không yêu cầu `end_at`.
  - không đưa vào nhóm cảnh báo sắp đến hạn/quá hạn.

## 4.3 Cập nhật tiến độ công việc

- Cho phép cập nhật `processing_status`, `completion_percent`, `priority`, `completed_at`.
- Rule đồng bộ:
  - `processing_status = done` -> `completion_percent = 100`, set `completed_at`.
  - `completion_percent = 100` -> tự chuyển `done`.
  - nếu đang `done` mà mở lại -> clear `completed_at`.

## 4.4 Phạm vi dữ liệu và bảo mật

- Module `TaskAssignment` vận hành độc lập, **không dùng `organization_id`**.
- Không giao việc trực tiếp cho phòng ban, chỉ giao cho user.
- Phòng ban dùng nguồn dữ liệu riêng của module (`task_assignment_departments`).
- Người nhận việc (`user_id`) phải là user hợp lệ/đang hoạt động và được ánh xạ với phòng ban trong module.
- Nếu hệ thống chạy đa tổ chức, việc tách dữ liệu tổ chức xử lý ở tầng triển khai/hạ tầng, không đặt trong schema nghiệp vụ của module này.

## 4.5 Nhận xét, ghi chú và trao đổi (theo công việc)

- Mỗi công việc có **timeline** nhận xét/trao đổi giữa **quản lý** và **người thực hiện**, lưu tại `task_assignment_item_notes`.
- Mỗi lần gửi là một bản ghi mới có `created_at` cố định; không sửa/xóa nội dung cũ để đảm bảo truy vết.
- `author_role` phân loại quản lý hay người thực hiện (theo quyền tại thời điểm gửi).
- BE chỉ cho phép user có quyền (quản lý hoặc người được phân công hiện tại) đọc/ghi ghi chú theo công việc.

---

## 5. API và bộ lọc theo dõi theo thời gian

## 5.1 Bộ lọc index cho văn bản/công việc

Để theo dõi công việc theo user và tổng hợp theo phòng ban của module theo thời gian, index cần hỗ trợ:
- `search` (tên công việc/tên văn bản)
- `processing_status` (trạng thái xử lý)
- `completion_percent_from`, `completion_percent_to`
- `priority`
- `deadline_type`
- `start_from`, `start_to` (lọc theo ngày giờ bắt đầu)
- `end_from`, `end_to` (lọc theo ngày giờ kết thúc)
- `from_date`, `to_date` (theo `issue_date` của văn bản)
- `department_id` (lọc tập user theo `task_assignment_user_departments`)
- (Thống kê phòng ban sử dụng `assigned_department_id` đã lưu tại thời điểm giao)
- `user_id` (lọc theo người được giao việc)
- `assignment_role` (`main`/`support`)
- `assignment_status` (`assigned`, `accepted`, `rejected`, `done`, `transferred`)
- `task_assignment_type_id`
- `task_assignment_item_type_id`
- `sort_by`: `id`, `created_at`, `updated_at`, `start_at`, `end_at`, `completion_percent`, `priority`, `issue_date`
- `sort_order`: `asc`/`desc`
- `limit`

## 5.2 Endpoint báo cáo/thống kê đề xuất

Ngoài `stats` chuẩn, nên có:

1. `GET /api/task-assignment-items/stats-by-department`
   - Tổng số theo `assigned_department_id` (phòng ban tại thời điểm giao việc).
   - Đang làm / hoàn thành / quá hạn.

2. `GET /api/task-assignment-items/stats-by-user`
   - Tổng việc theo từng người dùng.
   - Tỷ lệ hoàn thành đúng hạn, quá hạn theo cá nhân.

3. `GET /api/task-assignment-items/stats-by-time`
   - Theo tuần/tháng/quý.
   - So sánh xu hướng hoàn thành và quá hạn.

4. `GET /api/task-assignment-items/overdue`
   - Danh sách quá hạn cần xử lý ngay.

5. `GET /api/task-assignment-items/upcoming-deadline`
   - Danh sách sắp đến hạn trong N ngày.

## 5.3 API nhận xét ghi chú tra đổi (theo công việc)

- `GET /api/task-assignment-items/{id}/notes`
  - Danh sách ghi chú theo thời gian (`created_at` tăng dần), phục vụ hiển thị timeline.
- `POST /api/task-assignment-items/{id}/notes`
  - Thêm một dòng nhận xét/trao đổi: `content` (bắt buộc); BE tự gán `author_user_id` và `author_role` theo người đăng nhập và quyền.

---

## 6. Cơ chế nhắc việc (Reminder)

## 6.1 Chiến lược nhắc việc

Đề xuất mốc nhắc mặc định (cấu hình được):
- Trước hạn `7 ngày`, `3 ngày`, `1 ngày`
- Đúng ngày hạn

Và nhắc quá hạn:
- Sau hạn `+1 ngày`, `+3 ngày`, `+7 ngày`

Các mốc này không hard-code, lấy từ `task_assignment_notification_settings`:
- `lead_days_json`
- `on_due_day_enabled`
- `overdue_days_json`

## 6.2 Scheduler & job

- Tạo command chạy định kỳ mỗi 15 phút hoặc mỗi giờ:
  - `sail artisan task-assignment:dispatch-reminders`
- Luồng:
  1. Lấy công việc có `has_deadline`, chưa `done`.
  2. Nạp cấu hình thông báo đang active.
  3. Tính mốc nhắc cần gửi tại thời điểm hiện tại theo timezone cấu hình.
  4. Ghi vào `task_assignment_reminders` (pending -> sent/failed).
  5. Gửi qua các kênh đã bật (system/email/sms/zalo/firebase).

## 6.3 Chống gửi trùng

- Tạo khóa idempotent theo:
  - `task_assignment_item_id + remind_at + channel + recipient`
- Nếu đã có bản ghi `sent` thì bỏ qua.

## 6.4 Quy tắc cấu hình kênh gửi nhận thông báo

- Cho phép bật/tắt độc lập từng kênh:
  - Email
  - SMS
  - Zalo
  - Firebase
  - Thông báo nội bộ hệ thống
- Khi một kênh tắt, job không đẩy vào provider tương ứng.
- Nếu gửi thất bại ở một kênh, vẫn tiếp tục các kênh còn lại và ghi `failed` chi tiết.
- Với Firebase: ưu tiên cho thông báo realtime trên app.
- Với Email/SMS/Zalo: dùng cho nhắc việc quan trọng hoặc quá hạn.

## 6.5 API cấu hình thông báo đề xuất

- `GET /api/task-assignment-notification-settings`
  - Lấy cấu hình thông báo hiện tại.
- `PUT /api/task-assignment-notification-settings`
  - Cập nhật cấu hình kênh và mốc thời gian gửi nhắc.

Body đề xuất:
- `channel_email_enabled`: boolean
- `channel_sms_enabled`: boolean
- `channel_zalo_enabled`: boolean
- `channel_firebase_enabled`: boolean
- `channel_system_enabled`: boolean
- `lead_days`: array<int> (ví dụ `[7,3,1]`)
- `on_due_day_enabled`: boolean
- `overdue_days`: array<int> (ví dụ `[1,3,7]`)
- `send_time`: `H:i:s`
- `timezone`: string

---

## 7. Báo cáo quản trị

## 7.1 Dashboard KPI tối thiểu

- Tổng số văn bản giao việc (theo tháng/quý/năm).
- Tổng số công việc theo phòng ban.
- Tổng số công việc theo người dùng.
- Tỷ lệ hoàn thành đúng hạn.
- Số lượng đang quá hạn.
- Top phòng ban có nhiều việc quá hạn.
- Top cá nhân quá hạn nhiều.
- Phân bố theo loại công việc.

## 7.2 Mẫu báo cáo định kỳ

1. **Báo cáo tuần theo phòng ban**:
   - Việc nhận mới
   - Việc hoàn thành
   - Việc tồn/ quá hạn

2. **Báo cáo tuần theo người dùng trong từng phòng ban**:
   - Việc được giao
   - Việc đã nhận xử lý
   - Việc hoàn thành đúng hạn / quá hạn

3. **Báo cáo theo văn bản giao việc**:
   - Từng văn bản có bao nhiêu công việc
   - Tỷ lệ hoàn thành từng văn bản

4. **Báo cáo nhắc việc**:
   - Số lượt nhắc đã gửi
   - Tỷ lệ gửi thành công/thất bại
   - Danh sách chưa gửi được để xử lý

---

## 8. Quy trình nghiệp vụ và tích hợp API

## 8.1 Quy trình end-to-end (từ giao việc đến khóa công việc)

### Giai đoạn 1: Khởi tạo giao việc

1. Quản lý tạo văn bản giao việc ở trạng thái `draft`.
2. Quản lý đính kèm tệp văn bản giao việc.
3. Quản lý thêm các công việc thuộc văn bản (thời hạn, ưu tiên, loại công việc).
4. FE lọc user theo phòng ban module và chọn người thực hiện.
5. BE lưu phân công tại `task_assignment_item_user` với `assigned_department_id` tại thời điểm giao.
6. Quản lý ban hành văn bản (`issued`) để bắt đầu vận hành chính thức.

### Giai đoạn 2: Thực hiện công việc

1. Người thực hiện nhận việc và cập nhật tiến độ (`processing_status`, `completion_percent`).
2. Quản lý và người thực hiện trao đổi qua timeline ghi chú `task_assignment_item_notes` (có dấu thời gian `created_at`).
3. Nếu cần, công việc được điều chuyển bởi quản lý hoặc người thực hiện hiện tại (theo quyền), lưu lịch sử tại `task_assignment_item_user_transfers`.
4. Hệ thống tự động đánh dấu `overdue` khi quá hạn mà chưa hoàn thành.

### Giai đoạn 3: Báo cáo kết quả

1. Người thực hiện nộp báo cáo kết quả (ngày hoàn thành, số ký hiệu, trích yếu, nội dung, tệp đính kèm).
2. Người thực hiện được chỉnh sửa báo cáo khi chưa xác nhận/khóa.
3. Quản lý kiểm tra và xác nhận đạt quy định (`manager_confirmed=true`).

### Giai đoạn 4: Khóa báo cáo và đóng công việc

1. Sau xác nhận, BE khóa báo cáo (`is_locked=true`, `locked_at`, `locked_by`).
2. Khi báo cáo cuối đã xác nhận và khóa, công việc chuyển hoàn thành nghiệp vụ (`processing_status=done`).
3. Sau khi đóng công việc:
   - chặn cập nhật báo cáo thông thường,
   - chặn điều chuyển/gán lại người thực hiện thông thường,
   - chỉ cho phép mở lại bằng quyền đặc biệt và có log.

### Giai đoạn 5: Điều hành và thống kê

1. Dashboard thống kê theo phòng ban, user, thời gian, quá hạn, sắp đến hạn.
2. Thống kê phòng ban luôn dựa trên `assigned_department_id` đã lưu tại thời điểm giao/điều chuyển để giữ ổn định lịch sử.
3. Drill-down xem đầy đủ lịch sử: phân công, điều chuyển, ghi chú timeline, báo cáo, xác nhận và trạng thái khóa.

## 8.2 Luồng BE/FE theo API (mapping kỹ thuật)

### Luồng A - Tạo và ban hành văn bản giao việc

1. **FE** tạo văn bản ở trạng thái `draft`:
   - Gọi `POST /api/task-assignment-documents` với `name`, `summary`, `issue_date`, `task_assignment_type_id`.
2. **BE** validate và lưu văn bản:
   - Trả về `document_id` để FE dùng cho các bước tiếp theo.
3. **FE** đính kèm nhiều tệp:
   - Gọi `POST /api/task-assignment-documents/{id}/attachments`.
4. **BE** upload qua `MediaService` và gắn attachment:
   - Lưu vào `task_assignment_document_attachments`.
5. **FE** thêm danh sách công việc thuộc văn bản:
   - `name`, `description`, `start_at`, `end_at`, `processing_status`, `completion_percent`, `priority`, `deadline_type`.
6. **BE** validate quy tắc thời gian và tiến độ:
   - `end_at >= start_at` (nếu có `end_at`).
   - `deadline_type = has_deadline` thì bắt buộc `end_at`.
7. **FE** lọc user theo phòng ban và gán người dùng cho từng công việc:
   - Ghi nhận `assignment_role`, `assignment_status`.
8. **BE** kiểm tra user hợp lệ trước khi giao:
   - Lưu liên kết `task_assignment_item_user` và ghi `assigned_department_id` theo phòng ban đang dùng để lọc user tại thời điểm giao.
9. **FE** phát hành văn bản:
   - Gọi `PATCH /api/task-assignment-documents/{id}/change-status` sang `issued`.
10. **BE** khóa logic chỉnh sửa cốt lõi và sinh lịch nhắc việc:
    - Set `issued_at`.
    - Tạo lịch nhắc ban đầu cho các công việc có hạn.

### Luồng B - Người dùng xử lý công việc hằng ngày

1. **FE** mở màn hình "Công việc của tôi":
   - Gọi danh sách với filter: `department_id`, `user_id`, `processing_status`, `priority`, `start_from/start_to`, `end_from/end_to`.
2. **BE** trả danh sách đã phân trang:
   - Kèm thông tin văn bản, người giao, người phối hợp và `assigned_department_id` (phòng ban tại thời điểm giao việc).
3. **FE** cập nhật tiến độ công việc:
   - Gọi `PATCH /api/task-assignment-items/{id}` với `processing_status`, `completion_percent`, ghi chú.
3b. **FE** xem và gửi nhận xét, ghi chú tra đổi với quản lý (timeline):
   - `GET /api/task-assignment-items/{id}/notes`
   - `POST /api/task-assignment-items/{id}/notes` với `content` (mỗi lần gửi là một bản ghi mới, có `created_at`).
4. **BE** đồng bộ trạng thái:
   - `processing_status = done` => `completion_percent = 100`, set `completed_at`.
   - `completion_percent = 100` => tự chuyển `done`.
   - Quá `end_at` mà chưa hoàn thành => đánh dấu `overdue`.
5. **FE** (người đang được giao) chuyển công việc cho người dùng khác:
   - Gọi `POST /api/task-assignment-items/{id}/transfers` với `to_user_id`, `note`.
6. **BE** ghi nhận điều chuyển và lưu lịch sử:
   - Tạo record tại `task_assignment_item_user_transfers`.
   - Cập nhật record phân công hiện tại của user: `assignment_status=transferred`.
   - Tạo record phân công mới cho `to_user_id`: `assignment_status=assigned`, đồng thời set `assigned_department_id` theo phòng ban tại thời điểm điều chuyển.
   - Người thực hiện điều chuyển (`transferred_by_user_id`) có thể là **quản lý** hoặc **người thực hiện hiện tại** (theo phân quyền).
7. **FE** nộp báo cáo thực hiện công việc:
   - Gọi `POST /api/task-assignment-items/{id}/reports` với:
   - `completed_at`, `report_document_number`, `report_document_excerpt`, `report_document_content`, `files[]`.
8. **BE** lưu báo cáo và tệp đính kèm:
   - Lưu vào `task_assignment_item_reports`.
   - Upload file qua `MediaService`, lưu liên kết tại `task_assignment_item_report_attachments`.
9. **FE** chỉnh sửa báo cáo:
   - Gọi `PATCH /api/task-assignment-item-reports/{reportId}` để cập nhật nội dung, ngày cập nhật, tệp đính kèm.
10. **FE (quản lý)** xác nhận hoàn thành đúng quy định:
   - Gọi `PATCH /api/task-assignment-item-reports/{reportId}/confirm`.
11. **BE** ghi nhận xác nhận:
   - Set `manager_confirmed=true`, `manager_confirmed_by`, `manager_confirmed_at`, `manager_confirm_note`.
   - Khóa báo cáo: set `is_locked=true`, `locked_at`, `locked_by`.
   - Nếu báo cáo cuối cùng của công việc đã được xác nhận, cho phép chuyển công việc sang `processing_status=done`.

### Luồng C - Nhắc việc tự động

1. **BE Scheduler** chạy command định kỳ:
   - `sail artisan task-assignment:dispatch-reminders`.
2. **BE** chọn công việc cần nhắc theo mốc:
   - Trước hạn, đến hạn, quá hạn.
3. **BE** gửi nhắc qua kênh cấu hình:
   - In-app/email/Zalo/SMS.
4. **BE** lưu lịch sử gửi:
   - `pending/sent/failed`, chống gửi trùng theo khóa idempotent.

### Luồng D - Báo cáo và điều hành

1. **FE** dashboard gọi API thống kê:
   - `stats-by-department`, `stats-by-user`, `stats-by-time`, `overdue`, `upcoming-deadline`.
2. **BE** tổng hợp số liệu theo bộ lọc:
   - Thời gian, phòng ban, người dùng, trạng thái xử lý, mức độ ưu tiên.
3. **FE** hiển thị báo cáo:
   - KPI cards, biểu đồ xu hướng, danh sách quá hạn, top phòng ban/cá nhân chậm tiến độ.
4. **FE** xuất báo cáo:
   - Gọi endpoint export theo đúng bộ lọc đang xem để phục vụ họp giao ban.
5. **BE** hỗ trợ drill-down theo báo cáo thực hiện:
   - Xem chi tiết báo cáo của từng người: ngày hoàn thành, số ký hiệu, trích yếu, nội dung, tệp đính kèm, ngày cập nhật.
   - Hiển thị thêm trạng thái quản lý đã xác nhận hay chưa.

## 8.3 Import/Export

### Export

- Export văn bản: đầy đủ trường của index + thông tin tạo/cập nhật + danh sách tệp đính kèm (tên tệp/đường dẫn tải).
- Export công việc: gồm văn bản, người dùng được giao, phòng ban trong module, vai trò giao việc, trạng thái nhận việc, loại công việc, `start_at`, `end_at`, `processing_status`, `completion_percent`, `priority`.
- Trường thời gian format thống nhất theo chuẩn tài nguyên API.

### Import

- File validate: `required|file|mimes:xlsx,xls,csv|max:10240`.
- Cột bắt buộc tối thiểu:
  - Văn bản: `name`, `issue_date`, `task_assignment_type`
  - Công việc: `document_code_or_name`, `task_name`, `assignee_user`, `deadline_type`
- Nếu `deadline_type = has_deadline` bắt buộc có `end_at`.
- `assignee_user` import theo `id` hoặc `email/username`; phòng ban lấy theo ánh xạ user-phòng ban trong module.
- Tệp đính kèm không import trực tiếp qua cột excel nhị phân; khuyến nghị import theo `attachment_urls` (phân tách `;`) hoặc dùng API upload đính kèm riêng sau khi tạo văn bản.

## 8.4 API upload đính kèm đề xuất

- `POST /api/task-assignment-documents/{id}/attachments`
  - Upload nhiều tệp, trả về danh sách attachment đã gắn vào văn bản.
- `DELETE /api/task-assignment-documents/{id}/attachments/{attachmentId}`
  - Gỡ tệp đính kèm khỏi văn bản.
- `PATCH /api/task-assignment-documents/{id}/attachments/sort`
  - Cập nhật thứ tự hiển thị tệp đính kèm.

Validate upload đề xuất:
- `files` => `required|array|min:1`
- `files.*` => `file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:20480`

---

## 9. Permission và logging

## 9.1 Permission cần bổ sung

Trong `PermissionSeeder`, bổ sung các resource:
- `task-assignment-documents`: `stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import`
- `task-assignment-items`: `stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import`
- `task-assignment-types`: action chuẩn
- `task-assignment-item-types`: action chuẩn
- `task-assignment-item-notes`: `index` (list theo công việc), `store` (thêm ghi chú)

## 9.2 LogActivity

- Bổ sung mapping label cho:
  - ban hành văn bản
  - cập nhật tiến độ công việc
  - thêm nhận xét, ghi chú tra đổi theo công việc
  - gửi nhắc việc
- Log đầy đủ `resource`, `action`, `target_id`, `assignee_user_id` và `assignee_department_id` theo dữ liệu phòng ban của module tại thời điểm log.

## 9.3 State Machine chuẩn (để BE/FE triển khai thống nhất)

### A. State machine phân công người thực hiện (`assignment_status`)

Trạng thái: `assigned`, `accepted`, `rejected`, `done`, `transferred`.

Luồng chuyển trạng thái:
- `assigned -> accepted`: người nhận việc xác nhận tiếp nhận.
- `assigned -> rejected`: người nhận việc từ chối (có lý do).
- `accepted -> done`: người nhận việc hoàn thành phần việc của mình.
- `assigned|accepted|done -> transferred`: quản lý hoặc người thực hiện hiện tại điều chuyển cho user khác (theo quyền).

Quy tắc:
- Khi `transferred`, bản ghi cũ không xóa; tạo bản ghi phân công mới cho user mới với `assignment_status=assigned`.
- Mọi điều chuyển bắt buộc lưu tại `task_assignment_item_user_transfers`.

### B. State machine xử lý công việc (`processing_status`)

Trạng thái: `todo`, `in_progress`, `paused`, `overdue`, `done`, `cancelled`.

Luồng chuyển trạng thái chính:
- `todo -> in_progress`: bắt đầu xử lý.
- `in_progress -> paused`: tạm dừng.
- `paused -> in_progress`: tiếp tục xử lý.
- `todo|in_progress|paused -> done`: hoàn thành.
- `todo|in_progress|paused -> overdue`: quá `end_at` nhưng chưa hoàn thành (hệ thống tự động).
- `todo|in_progress|paused -> cancelled`: hủy theo quyết định quản lý.

Quy tắc đồng bộ:
- `done` => `completion_percent=100`, set `completed_at`.
- `completion_percent=100` => tự chuyển `done`.
- Với công việc đã có báo cáo cuối được xác nhận và khóa, không cho sửa ngược về trạng thái đang xử lý (trừ quyền mở khóa đặc biệt).

### C. State machine báo cáo kết quả (`manager_confirmed` + `is_locked`)

Trạng thái logic:
- `draft_report`: đã tạo/chỉnh sửa báo cáo, chưa xác nhận quản lý (`manager_confirmed=false`, `is_locked=false`).
- `confirmed_locked`: quản lý xác nhận đạt quy định và khóa báo cáo (`manager_confirmed=true`, `is_locked=true`).

Luồng chuyển trạng thái:
- `draft_report -> confirmed_locked`: quản lý thực hiện action confirm.
- `confirmed_locked -> draft_report` (ngoại lệ): mở khóa bởi quyền đặc biệt (audit bắt buộc).

Quy tắc khóa:
- Khi `is_locked=true`: chặn cập nhật nội dung báo cáo, chặn sửa danh sách tệp báo cáo.
- Chỉ cho phép thao tác đọc và hiển thị lịch sử.

### D. Điều kiện đóng công việc (khóa nghiệp vụ)

Khuyến nghị điều kiện đóng:
- `processing_status=done`
- Có báo cáo cuối `manager_confirmed=true` và `is_locked=true`
- Không còn phân công đang mở ở trạng thái `assigned|accepted|in_progress` (nếu áp dụng đa người thực hiện)

Sau khi đóng:
- Chặn điều chuyển/gán lại người thực hiện.
- Chặn cập nhật tiến độ và báo cáo thông thường.
- Chỉ mở lại bằng quyền đặc biệt và phải ghi log lý do.

---

## 10. Lộ trình triển khai khuyến nghị

## Giai đoạn 1: Core CRUD + dữ liệu chuẩn
- Tạo migration + model + enum + request + resource + service.
- CRUD phòng ban nội bộ `task_assignment_departments`.
- CRUD văn bản, loại văn bản, loại công việc, công việc.
- Triển khai giao việc đến user qua `task_assignment_item_user`.
- Triển khai bảng ánh xạ user-phòng ban riêng cho module (ví dụ `task_assignment_user_departments`) để phục vụ lọc/thống kê.
- Bảng `task_assignment_item_notes` và API nhận xét ghi chú tra đổi theo công việc.

## Giai đoạn 2: Theo dõi tiến độ & thống kê
- Bộ lọc theo user, phòng ban module và thời gian.
 - Bổ sung bộ lọc theo user và trạng thái nhận việc.
- API `stats-by-department`, `stats-by-user`, `stats-by-time`, `overdue`.
- Dashboard backend cho lãnh đạo/phòng điều phối.

## Giai đoạn 3: Nhắc việc tự động
- Scheduler + queue job + bảng reminder history.
- Cấu hình mốc nhắc theo tham số module.
- Báo cáo hiệu quả nhắc việc.

## Giai đoạn 4: Tối ưu vận hành
- Cache thống kê theo ngày.
- Cơ chế phân quyền sâu theo phòng ban.
- Audit log và đối soát chất lượng dữ liệu.

---

## 11. Kết luận

Giải pháp tối ưu là tách rõ 3 lớp nghiệp vụ:
- **Lớp quản lý văn bản giao việc** (đầu vào pháp lý/quản trị),
- **Lớp thực thi công việc theo cá nhân** (theo dõi tiến độ thực tế),
- **Lớp điều hành** (thống kê + nhắc việc + báo cáo).

Thiết kế trên đáp ứng đầy đủ yêu cầu hiện tại và mở rộng tốt cho các nhu cầu nâng cao như KPI phòng ban, SLA xử lý, và tích hợp đa kênh nhắc việc.

*Tài liệu phân tích phục vụ triển khai module quản lý giao việc liên phòng ban.*
