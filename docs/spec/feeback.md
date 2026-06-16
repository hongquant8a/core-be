PHÂN TÍCH HỆ THỐNG QUẢN LÝ CÔNG VIỆC
Task Management System — Đặc tả thiết kế CSDL & Logic nghiệp vụ
Phiên bản: 1.1 (đã cập nhật góp ý)  |  Stack: Laravel 12 + Vue.js 3 + Danatec Standard
1. Thiết kế Cơ sở Dữ liệu (Đã cập nhật)
1.1 Bảng tasks — Quản lý thông tin công việc
Lưu thông tin cốt lõi của công việc. Tiến độ thời gian (chưa đến hạn, quá hạn…) được tính toán động qua computed attribute trong Laravel Model — không lưu cột DB — trừ các trạng thái chốt khi đã hoàn thành.

Trường (Column)	Kiểu dữ liệu	Mô tả
id	BigIncrement	Khóa chính
organization_id	BigInteger (FK)	Công ty / đơn vị (multi-tenant)
parent_id	BigInteger (FK, Nullable)	Công việc cha (subtask)
title	VARCHAR(255)	Tên công việc
description	TEXT	Mô tả công việc
manager_id	BigInteger (FK)	Người quản lý → bảng users
completed_by	BigInteger (FK, Nullable)	Người duyệt hoàn thành → bảng users
start_date	DATE	Ngày bắt đầu
end_date	DATE	Ngày kết thúc / hạn chót
actual_end_date	DATETIME (Nullable)	Ngày thực tế hoàn thành
progress	TINYINT UNSIGNED (0–100)	% tiến độ hiện tại (denormalized)
type	TaskTypeEnum	Loại: feature, bug, admin…
priority	PriorityEnum	low / medium / high / urgent
status	TaskStatusEnum	pending → processing → pending_approval → completed / paused / canceled
created_at / updated_at	Timestamp	Tạo và cập nhật tự động

ℹ️  organization_id bắt buộc theo chuẩn multi-tenant Danatec. Model extends TenantModel thay vì Model.
ℹ️  parent_id dự phòng cho subtask sau này; dùng kalnyo/nestedset nếu cần truy vấn cây.
ℹ️  progress (0–100) lưu denormalized để tránh N+1 khi list. Cập nhật mỗi khi có task_reports mới được duyệt hoặc nhân viên cập nhật.
ℹ️  completed_by phục vụ audit trail: ai là người bấm Duyệt hoàn thành.

1.2 Bảng task_assignees — Người thực hiện
Quan hệ N-N giữa tasks và users. Tách riêng để dễ mở rộng khi giao việc cho nhóm.

Trường (Column)	Kiểu dữ liệu	Mô tả
task_id	BigInteger (FK)	Liên kết bảng tasks
user_id	BigInteger (FK)	Người thực hiện → bảng users
role	AssigneeRoleEnum	lead / supporter

ℹ️  Thêm cột role (AssigneeRoleEnum: lead / supporter) để phân biệt người chịu trách nhiệm chính khi có nhiều assignee.

1.3 Bảng task_reports — Báo cáo công việc / Duyệt
Lưu lịch sử toàn bộ các lần nhân viên báo cáo và quản lý duyệt. Nhân viên có thể tạo báo cáo mới không giới hạn số lần, kể cả sau khi bị từ chối.

Trường (Column)	Kiểu dữ liệu	Mô tả
id	BigIncrement	Khóa chính
task_id	BigInteger (FK)	Liên kết bảng tasks
user_id	BigInteger (FK)	Người báo cáo → bảng users
progress	TINYINT UNSIGNED (0–100)	% tiến độ tại thời điểm báo cáo
content	TEXT	Mô tả tiến độ, link kết quả, ghi chú
status	ReportStatusEnum	submitted / approved / rejected
feedback	TEXT (Nullable)	Phản hồi của quản lý khi duyệt/từ chối
created_at	Timestamp	Thời gian báo cáo

ℹ️  Tách progress (TINYINT 0–100) ra khỏi content để tránh lẫn lộn giữa số liệu định lượng và mô tả định tính.

2. Logic Trạng thái & Tiến độ
2.1 Trạng thái (status) — Lưu trực tiếp DB
Dùng TaskStatusEnum với các giá trị sau:
•	pending: Mới giao, chưa có hành động.
•	processing: Nhân viên đã bấm Bắt đầu hoặc có báo cáo tiến độ (progress 1–99%).
•	pending_approval: Nhân viên gửi báo cáo 100%, chờ quản lý duyệt.
•	completed: Quản lý đã duyệt hoàn thành.
•	paused: Tạm dừng (nên thêm cột pause_reason nếu cần audit).
•	canceled: Đã hủy bỏ.

2.2 Tiến độ (progress_status) — Computed Attribute
Không lưu cột DB. Khai báo là getProgressStatusAttribute() trong Laravel Model, trả về TaskProgressStatusEnum.

progress_status (Computed)	Điều kiện xác định
canceled	status == 'canceled'
completed_early	status == 'completed' AND actual_end_date < end_date (chênh lệch > 1 ngày)
completed_on_time	status == 'completed' AND actual_end_date <= end_date
completed_late	status == 'completed' AND actual_end_date > end_date
on_track	status IN (pending/processing/paused) AND now() <= end_date
overdue	status IN (pending/processing/paused) AND now() > end_date

3. Quy trình Nghiệp vụ (Workflow)
Bước 1 — Giao việc (Manager)
1.	Quản lý tạo công việc: nhập tên, mô tả, chọn assignee(s), ngày bắt đầu/kết thúc, loại, ưu tiên.
2.	API Laravel lưu vào tasks với status = pending, progress = 0.
3.	Hệ thống gửi thông báo (Event → Listener → Notification/WebSocket) đến tất cả assignee.

Bước 2 — Báo cáo công việc (Assignee)
4.	Nhân viên bấm Bắt đầu → tasks.status = processing, progress = 1 (hoặc giá trị nhập vào).
5.	Định kỳ hoặc khi xong, nhân viên tạo task_report (progress, content, link kết quả) với report.status = submitted.
6.	Nếu báo cáo progress = 100%: tasks.status tự động chuyển thành pending_approval.
7.	tasks.progress được cập nhật theo giá trị progress của báo cáo mới nhất.

Bước 3 — Duyệt hoàn thành (Manager)
8.	Quản lý nhận thông báo, xem danh sách công việc có status = pending_approval.
Trường hợp ĐẠT — Quản lý bấm Duyệt:
•	tasks.status = completed
•	tasks.actual_end_date = Carbon::now()
•	tasks.completed_by = auth()->id()
•	task_reports.status = approved

Trường hợp KHÔNG ĐẠT — Quản lý bấm Từ chối + nhập feedback:
•	tasks.status = processing (quay lại làm tiếp)
•	task_reports.status = rejected, task_reports.feedback = [lý do]
•	tasks.progress KHÔNG reset — giữ nguyên % đã đạt
•	Nhân viên được tạo báo cáo mới không giới hạn số lần
Danatec Technology JSC  |  Tài liệu nội bộ  |  Phiên bản 1.1
