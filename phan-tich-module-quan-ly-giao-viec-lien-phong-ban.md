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

## 3.1 Bảng phòng ban nội bộ của module

**Bảng:** `task_assignment_departments`  
Mục đích: quản lý danh mục phòng ban phục vụ riêng cho nghiệp vụ giao việc.

Trường chính đề xuất:
- `id`
- `code` (unique, phục vụ import/report)
- `name`
- `description` (nullable)
- `status` (`active`/`inactive`)
- `sort_order` (default 0)
- `created_by`, `updated_by`
- `created_at`, `updated_at`

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

## 3.7 Bảng liên kết công việc - phòng ban thực hiện

Do yêu cầu "giữa các phòng ban với nhau", 1 công việc có thể cần nhiều phòng ban phối hợp.

**Bảng pivot:** `task_assignment_item_department`

Trường:
- `id`
- `task_assignment_item_id`
- `department_id` (FK -> `task_assignment_departments`)
- `role` (`main`, `cooperate`) - phòng ban chính/phối hợp
- `created_at`, `updated_at`

Ràng buộc:
- unique(`task_assignment_item_id`, `department_id`)

## 3.8 Bảng liên kết công việc - người dùng thực hiện

Để giao việc đến từng cá nhân trong phòng ban, bổ sung bảng:

**Bảng pivot:** `task_assignment_item_user`

Trường:
- `id`
- `task_assignment_item_id`
- `department_id` (FK -> `task_assignment_departments`)
- `user_id` (FK -> `users`)
- `assignment_role` (`main`, `support`) - người chủ trì/phối hợp
- `assignment_status` (`assigned`, `accepted`, `rejected`, `done`) - trạng thái nhận việc
- `assigned_at` (thời điểm giao)
- `accepted_at` (nullable)
- `completed_at` (nullable)
- `note` (nullable)
- `created_at`, `updated_at`

Ràng buộc:
- unique(`task_assignment_item_id`, `user_id`)
- index(`department_id`, `assignment_status`)

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
- `updated_at` (ngày cập nhật báo cáo gần nhất)
- `created_at`

Ràng buộc:
- index(`task_assignment_item_id`, `reporter_user_id`)
- index(`completed_at`)

Ghi chú:
- Mỗi công việc có thể có nhiều lần báo cáo theo tiến độ hoặc nhiều báo cáo từ các user phối hợp.

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

## 3.11 Bảng lưu nhắc việc/lịch sử gửi nhắc

**Bảng:** `task_assignment_reminders`

Trường:
- `id`
- `task_assignment_item_id`
- `remind_at` (thời điểm dự kiến nhắc)
- `sent_at` (nullable)
- `channel` (`system`, `email`, `zalo`, `sms`)
- `recipient_department_id` (nullable)
- `recipient_user_id` (nullable)
- `status` (`pending`, `sent`, `failed`)
- `error_message` (nullable)
- `created_at`, `updated_at`

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
- Dữ liệu phòng ban được quản lý riêng qua bảng `task_assignment_departments`.
- Mọi ràng buộc nghiệp vụ giao việc phải kiểm tra theo `department_id` nội bộ của module.
- Người nhận việc (`user_id`) phải thuộc đúng `department_id` được giao trong module.
- Nếu hệ thống chạy đa tổ chức, việc tách dữ liệu tổ chức xử lý ở tầng triển khai/hạ tầng, không đặt trong schema nghiệp vụ của module này.

---

## 5. API và bộ lọc theo dõi theo thời gian

## 5.1 Bộ lọc index cho văn bản/công việc

Để theo dõi theo phòng ban theo thời gian, index công việc cần hỗ trợ:
- `search` (tên công việc/tên văn bản)
- `processing_status` (trạng thái xử lý)
- `completion_percent_from`, `completion_percent_to`
- `priority`
- `deadline_type`
- `start_from`, `start_to` (lọc theo ngày giờ bắt đầu)
- `end_from`, `end_to` (lọc theo ngày giờ kết thúc)
- `from_date`, `to_date` (theo `issue_date` của văn bản)
- `department_id` (lọc theo đơn vị thực hiện)
- `user_id` (lọc theo người được giao việc)
- `assignment_role` (`main`/`support`)
- `assignment_status` (`assigned`, `accepted`, `rejected`, `done`)
- `task_assignment_type_id`
- `task_assignment_item_type_id`
- `sort_by`: `id`, `created_at`, `updated_at`, `start_at`, `end_at`, `completion_percent`, `priority`, `issue_date`
- `sort_order`: `asc`/`desc`
- `limit`

## 5.2 Endpoint báo cáo/thống kê đề xuất

Ngoài `stats` chuẩn, nên có:

1. `GET /api/task-assignment-items/stats-by-department`
   - Tổng số theo phòng ban.
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

---

## 6. Cơ chế nhắc việc (Reminder)

## 6.1 Chiến lược nhắc việc

Đề xuất 3 mốc nhắc mặc định (cấu hình được):
- Trước hạn `3 ngày`
- Trước hạn `1 ngày`
- Đúng ngày hạn

Và nhắc quá hạn:
- Sau hạn `+1 ngày`, `+3 ngày`, `+7 ngày`

## 6.2 Scheduler & job

- Tạo command chạy định kỳ mỗi 15 phút hoặc mỗi giờ:
  - `sail artisan task-assignment:dispatch-reminders`
- Luồng:
  1. Lấy công việc có `has_deadline`, chưa `done`.
  2. Tính mốc nhắc cần gửi tại thời điểm hiện tại.
  3. Ghi vào `task_assignment_reminders` (pending -> sent/failed).
  4. Gửi qua kênh cấu hình (notification in-app/email/...).

## 6.3 Chống gửi trùng

- Tạo khóa idempotent theo:
  - `task_assignment_item_id + remind_at + channel + recipient`
- Nếu đã có bản ghi `sent` thì bỏ qua.

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

## 8. Import/Export

## 8.1 Export

- Export văn bản: đầy đủ trường của index + thông tin tạo/cập nhật + danh sách tệp đính kèm (tên tệp/đường dẫn tải).
- Export công việc: gồm văn bản, phòng ban thực hiện, người dùng được giao, vai trò giao việc, trạng thái nhận việc, loại công việc, `start_at`, `end_at`, `processing_status`, `completion_percent`, `priority`.
- Trường thời gian format thống nhất theo chuẩn tài nguyên API.

## 8.2 Import

- File validate: `required|file|mimes:xlsx,xls,csv|max:10240`.
- Cột bắt buộc tối thiểu:
  - Văn bản: `name`, `issue_date`, `task_assignment_type`
  - Công việc: `document_code_or_name`, `task_name`, `department`, `assignee_user`, `deadline_type`
- Nếu `deadline_type = has_deadline` bắt buộc có `end_at`.
- `department` import theo `code` hoặc `name` từ bảng `task_assignment_departments`.
- `assignee_user` import theo `id` hoặc `email/username` và phải thuộc `department` đã chỉ định.
- Tệp đính kèm không import trực tiếp qua cột excel nhị phân; khuyến nghị import theo `attachment_urls` (phân tách `;`) hoặc dùng API upload đính kèm riêng sau khi tạo văn bản.

## 8.3 API upload đính kèm đề xuất

- `POST /api/task-assignment-documents/{id}/attachments`
  - Upload nhiều tệp, trả về danh sách attachment đã gắn vào văn bản.
- `DELETE /api/task-assignment-documents/{id}/attachments/{attachmentId}`
  - Gỡ tệp đính kèm khỏi văn bản.
- `PATCH /api/task-assignment-documents/{id}/attachments/sort`
  - Cập nhật thứ tự hiển thị tệp đính kèm.

Validate upload đề xuất:
- `files` => `required|array|min:1`
- `files.*` => `file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx|max:20480`

## 8.4 Luồng hoạt động BE/FE để giao việc và báo cáo công việc

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
7. **FE** gán phòng ban và người dùng cho từng công việc:
   - Ghi nhận `assignment_role`, `assignment_status`.
8. **BE** kiểm tra user thuộc phòng ban đã giao:
   - Lưu liên kết `task_assignment_item_department` và `task_assignment_item_user`.
9. **FE** phát hành văn bản:
   - Gọi `PATCH /api/task-assignment-documents/{id}/change-status` sang `issued`.
10. **BE** khóa logic chỉnh sửa cốt lõi và sinh lịch nhắc việc:
    - Set `issued_at`.
    - Tạo lịch nhắc ban đầu cho các công việc có hạn.

### Luồng B - Người dùng xử lý công việc hằng ngày

1. **FE** mở màn hình "Công việc của tôi":
   - Gọi danh sách với filter: `department_id`, `user_id`, `processing_status`, `priority`, `start_from/start_to`, `end_from/end_to`.
2. **BE** trả danh sách đã phân trang:
   - Kèm thông tin văn bản, phòng ban, người giao, người phối hợp.
3. **FE** cập nhật tiến độ công việc:
   - Gọi `PATCH /api/task-assignment-items/{id}` với `processing_status`, `completion_percent`, ghi chú.
4. **BE** đồng bộ trạng thái:
   - `processing_status = done` => `completion_percent = 100`, set `completed_at`.
   - `completion_percent = 100` => tự chuyển `done`.
   - Quá `end_at` mà chưa hoàn thành => đánh dấu `overdue`.
5. **FE** nộp báo cáo thực hiện công việc:
   - Gọi `POST /api/task-assignment-items/{id}/reports` với:
   - `completed_at`, `report_document_number`, `report_document_excerpt`, `report_document_content`, `files[]`.
6. **BE** lưu báo cáo và tệp đính kèm:
   - Lưu vào `task_assignment_item_reports`.
   - Upload file qua `MediaService`, lưu liên kết tại `task_assignment_item_report_attachments`.
7. **FE** chỉnh sửa báo cáo:
   - Gọi `PATCH /api/task-assignment-item-reports/{reportId}` để cập nhật nội dung, ngày cập nhật, tệp đính kèm.

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

---

## 9. Permission và logging

## 9.1 Permission cần bổ sung

Trong `PermissionSeeder`, bổ sung các resource:
- `task-assignment-documents`: `stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import`
- `task-assignment-items`: `stats,index,show,store,update,destroy,bulkDestroy,bulkUpdateStatus,changeStatus,export,import`
- `task-assignment-types`: action chuẩn
- `task-assignment-item-types`: action chuẩn

## 9.2 LogActivity

- Bổ sung mapping label cho:
  - ban hành văn bản
  - cập nhật tiến độ công việc
  - gửi nhắc việc
- Log đầy đủ `resource`, `action`, `department_id` (nếu có), `target_id`.

---

## 10. Lộ trình triển khai khuyến nghị

## Giai đoạn 1: Core CRUD + dữ liệu chuẩn
- Tạo migration + model + enum + request + resource + service.
- CRUD phòng ban nội bộ `task_assignment_departments`.
- CRUD văn bản, loại văn bản, loại công việc, công việc.
- Thiết lập ràng buộc FK dựa trên `department_id`.
- Triển khai giao việc đến user qua `task_assignment_item_user`.

## Giai đoạn 2: Theo dõi tiến độ & thống kê
- Bộ lọc theo phòng ban và thời gian.
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
- **Lớp thực thi công việc liên phòng ban và theo cá nhân** (theo dõi tiến độ thực tế),
- **Lớp điều hành** (thống kê + nhắc việc + báo cáo).

Thiết kế trên đáp ứng đầy đủ yêu cầu hiện tại và mở rộng tốt cho các nhu cầu nâng cao như KPI phòng ban, SLA xử lý, và tích hợp đa kênh nhắc việc.

*Tài liệu phân tích phục vụ triển khai module quản lý giao việc liên phòng ban.*
