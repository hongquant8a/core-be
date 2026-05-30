# TÀI LIỆU GIẢI PHÁP KỸ THUẬT
# MODULE QUẢN LÝ LỊCH CÔNG TÁC

> **Văn phòng Thành ủy & Thường trực Thành ủy**

| Mục | Nội dung |
|---|---|
| Tên tài liệu | Giải pháp Module Quản lý Lịch công tác |
| Phiên bản | 1.0 |
| Đối tượng | Văn phòng Thành ủy / Cơ quan hành chính nhà nước |
| Phạm vi | Mô tả nghiệp vụ, kiến trúc, CSDL, luồng xử lý |
| Người soạn thảo | Đặng Hồng Quân - Giám đốc kỹ thuật |
| Ngày phát hành | Tháng 5/2026 |
| Mã tài liệu | DNT-TKKT-SCHEDULING-v1.0 |

---

## MỤC LỤC

1. [Tổng quan giải pháp](#phần-1-tổng-quan-giải-pháp)
2. [Actor và phân quyền](#phần-2-actor-và-phân-quyền)
3. [Thiết kế cơ sở dữ liệu](#phần-3-thiết-kế-cơ-sở-dữ-liệu)
4. [Enums và Constants](#phần-4-enums-và-constants)
5. [Các luồng nghiệp vụ chi tiết](#phần-5-các-luồng-nghiệp-vụ-chi-tiết)
6. [Xuất báo cáo (Excel / PDF / Word)](#phần-6-xuất-báo-cáo-excel--pdf--word)
7. [Kiến trúc module và cấu trúc thư mục](#phần-7-kiến-trúc-module-và-cấu-trúc-thư-mục)
8. [API Endpoints](#phần-8-api-endpoints)
9. [Thư viện và dependencies](#phần-9-thư-viện-và-dependencies)
10. [Cấu hình module](#phần-10-cấu-hình-module)
11. [Roadmap triển khai](#phần-11-roadmap-triển-khai)
12. [Checklist cho Claude Code](#phần-12-checklist-cho-claude-code-triển-khai)
13. [Phụ lục](#phụ-lục)

---

# PHẦN 1. TỔNG QUAN GIẢI PHÁP

## 1.1. Mục đích và phạm vi

Module Quản lý Lịch công tác (Scheduling) là một module nghiệp vụ thuộc hệ thống quản lý điều hành tổng thể của Văn phòng Thành ủy. Module được thiết kế theo kiến trúc plug-in, có thể tích hợp vào hệ thống Core đã có sẵn các thành phần phổ biến (User, Organization, RBAC, File Storage).

Module phục vụ **02 phân hệ nghiệp vụ độc lập**:

- **Phân hệ Thường trực Thành ủy (Executive):** Quản lý lịch của Lãnh đạo cấp cao (Bí thư, Phó Bí thư). Lịch do Thư ký lập thay cho Lãnh đạo phụ trách.
- **Phân hệ Văn phòng Thành ủy (Office):** Quản lý lịch của cán bộ, công chức trong Văn phòng. Mỗi người tự đăng ký, Chuyên viên tổng hợp điều phối.

## 1.2. Đặc điểm kiến trúc

| Hạng mục | Mô tả |
|---|---|
| Kiến trúc tổng thể | Module hóa (Modular) trong Laravel — 1 module Scheduling phục vụ cả 2 phân hệ, dùng chung Core User/Organization/RBAC |
| Multi-tenant | Mọi dữ liệu nghiệp vụ có `organization_id` để cô lập theo đơn vị; Global Scope tự động lọc |
| Phân hệ | Phân biệt qua trường `module_type` (EXECUTIVE/OFFICE), dùng chung CSDL |
| Backend stack | Laravel 12 + MySQL 8.0 + Redis + Horizon + Reverb |
| Frontend stack | Vue 3 Composition API + Pinia + Tailwind CSS + Vite |
| Pattern code | Service-Action Pattern, Form Request validation, Resource transformation, Policy authorization |

## 1.3. Tích hợp với Core hệ thống

Module tận dụng các thành phần đã có sẵn trong Core, không xây dựng lại:

| Thành phần Core | Cách module sử dụng |
|---|---|
| Model User (`\App\Models\User`) | Reference qua FK: host_id, driver_id, created_by, approved_by... |
| Model Organization | Multi-tenant qua organization_id |
| spatie/laravel-permission | Module đăng ký permissions với prefix `scheduling.*` |
| laravel/sanctum | API auth, không cần cấu hình thêm |
| Bảng users | Cần có: `organization_id`, `priority_weight` (denormalize cho sort), `fcm_token` (push) |

## 1.4. Phạm vi chức năng

- Thêm, sửa, xóa, sao chép lịch công tác
- Duyệt lịch theo cấu hình động (bật/tắt cho từng phân hệ)
- Hiển thị ma trận lịch tuần: Buổi (hàng) × Ngày (cột)
- Drag-drop sắp xếp lịch trong cùng buổi/ngày
- Nhiều tài liệu đính kèm cho mỗi lịch, có tên gọi riêng
- Thông báo nhắc lịch đa kênh: FCM, Zalo OA, SMS, In-app (Reverb)
- Mỗi lịch có thể cấu hình riêng kênh và mốc nhắc (override preset mặc định)
- Nhóm tài khoản (Notification Group) — gửi thông báo cho nhóm thay vì chọn từng người
- Bộ lọc thông minh: lọc theo phân hệ, chức danh, người tham dự, nội dung, view (cá nhân/toàn cơ quan/quản lý)
- Lưu bộ lọc cá nhân (filter preset) để tái sử dụng
- Xuất báo cáo: Excel, PDF, Word theo mẫu hành chính
- View riêng cho Lái xe: chỉ hiển thị thông tin cơ bản (giờ, địa điểm, chủ trì), không thấy nội dung chi tiết
- Audit log toàn bộ thao tác CRUD

## 1.5. Quy mô dự kiến

| Chỉ số | Giá trị |
|---|---|
| Số lịch/tuần | ~ 300 lịch (cao điểm) |
| Số lịch/năm | ~ 14.400 lịch |
| Số tài khoản đồng thời | 50 user |
| Số notification/tuần | ~ 8.000 - 10.000 (tùy số recipients/lịch) |
| Số organization triển khai | Pilot: 1 (VP Thành ủy ĐN). Tương lai: nhiều đơn vị |

---

# PHẦN 2. ACTOR VÀ PHÂN QUYỀN

## 2.1. Các Actor tham gia hệ thống

| Mã Role | Vai trò nghiệp vụ | Phạm vi chức năng |
|---|---|---|
| `scheduling.admin` | Quản trị module | Toàn quyền: cấu hình duyệt, quản lý preset, nhóm tài khoản, settings |
| `scheduling.tong-hop` | Chuyên viên tổng hợp | Toàn quyền lịch trong organization: tạo, sửa/xóa bất kỳ lịch, duyệt, kéo thả, xuất file |
| `scheduling.thu-ky` | Thư ký Lãnh đạo | Tạo lịch phân hệ Thường trực với host là Lãnh đạo phụ trách; sửa/xóa lịch của mình |
| `scheduling.lanh-dao` | Lãnh đạo | Xem lịch, xuất báo cáo (không tạo/sửa) |
| `scheduling.nhan-vien` | Cán bộ công chức | Tạo lịch phân hệ Văn phòng; sửa/xóa lịch của mình |
| `scheduling.lai-xe` | Lái xe | Chỉ xem lịch mình được phân công (thông tin cơ bản: giờ, địa điểm, chủ trì) |

## 2.2. Danh sách Permissions

Toàn bộ permission dùng prefix `scheduling.*` để tránh xung đột với các module khác của Core. Việc gán role cho user do Admin của Core hệ thống quản lý.

| Permission | Mô tả nghiệp vụ |
|---|---|
| `scheduling.schedule.view` | Xem lịch (kết hợp với view filter) |
| `scheduling.schedule.create` | Tạo lịch mới |
| `scheduling.schedule.update.own` | Sửa lịch do mình tạo |
| `scheduling.schedule.update.any` | Sửa lịch của bất kỳ ai |
| `scheduling.schedule.delete.own` | Xóa lịch do mình tạo |
| `scheduling.schedule.delete.any` | Xóa lịch của bất kỳ ai |
| `scheduling.schedule.approve` | Duyệt lịch chờ duyệt (PENDING → PUBLISHED) |
| `scheduling.schedule.reorder` | Kéo thả sắp xếp thứ tự lịch |
| `scheduling.schedule.export` | Xuất Excel, PDF, Word |
| `scheduling.group.manage` | Tạo, sửa, xóa nhóm tài khoản nhận thông báo |
| `scheduling.preset.manage` | Quản lý mốc nhắc lịch mặc định |
| `scheduling.settings.manage` | Cấu hình duyệt theo phân hệ |

## 2.3. Mapping Role - Permission mặc định

```
scheduling.admin:
  → Tất cả permissions

scheduling.tong-hop:
  - schedule.view, create, update.any, delete.any, approve, reorder, export
  - group.manage

scheduling.thu-ky:
  - schedule.view, create, update.own, delete.own, export
  - Logic bổ sung: chỉ tạo được lịch EXECUTIVE,
    host_id phải là Lãnh đạo mà thư ký phụ trách
    (kiểm tra qua role hoặc cấu hình thủ công)

scheduling.lanh-dao:
  - schedule.view, schedule.export

scheduling.nhan-vien:
  - schedule.view, create, update.own, delete.own, export
  - Logic bổ sung: chỉ tạo được lịch OFFICE

scheduling.lai-xe:
  - schedule.view
  - Logic bổ sung: view filter mặc định = "personal",
    chỉ xem được lịch có driver_id = mình,
    Resource trả về rút gọn (DriverScheduleResource)
```

---

# PHẦN 3. THIẾT KẾ CƠ SỞ DỮ LIỆU

## 3.1. Sơ đồ tổng thể các bảng

Module bao gồm **10 bảng nghiệp vụ**. Các bảng `users`, `organizations`, `audits`, `roles`, `permissions` thuộc Core hệ thống (không tạo lại).

- **schedules** — Bảng trung tâm chứa thông tin lịch
- **schedule_attachments** — Tài liệu đính kèm (nhiều file/lịch, có tên gọi riêng)
- **schedule_notification_recipients** — Đối tượng nhận thông báo (user hoặc group)
- **schedule_reminders** — Mốc nhắc của từng lịch (PRESET hoặc CUSTOM)
- **schedule_notifications** — Hàng đợi gửi thông báo
- **notification_groups** — Nhóm tài khoản nhận thông báo
- **notification_group_members** — Thành viên nhóm
- **reminder_presets** — Mốc nhắc mặc định (Admin định nghĩa)
- **org_scheduling_settings** — Cấu hình duyệt theo phân hệ
- **filter_presets** — Bộ lọc cá nhân (lưu để tái sử dụng)

## 3.2. Bảng `schedules` — Lịch công tác (CORE)

| Trường | Kiểu | NULL | Mô tả |
|---|---|---|---|
| id | BIGINT | No | Khóa chính, auto-increment |
| organization_id | FK organizations | No | Multi-tenant, INDEXED |
| module_type | ENUM(EXECUTIVE, OFFICE) | No | Phân hệ: Thường trực hoặc Văn phòng |
| event_date | DATE | No | Ngày diễn ra |
| start_time | TIME | No | Giờ bắt đầu |
| end_time | TIME | Yes | Giờ kết thúc |
| session | ENUM(S, C, T) | No | Auto tính: <12=S, <18=C, >=18=T |
| content | TEXT | No | Nội dung cuộc họp |
| host_id | FK users | No | Người chủ trì |
| host_priority_weight | TINYINT UNSIGNED | No | Denormalize từ user.priority_weight |
| location | VARCHAR(255) | Yes | Địa điểm |
| preparation_unit | VARCHAR(255) | Yes | Đơn vị chuẩn bị |
| participant_count | VARCHAR(50) | Yes | Số người (text tự do) |
| nature | ENUM(HOST, ATTEND) | No | Tính chất: Chủ trì hoặc Tham gia |
| driver_id | FK users | Yes | Lái xe (tài khoản trong hệ thống) |
| color_code | VARCHAR(7) | No | Mã màu HEX hiển thị (mặc định #FFFFFF) |
| participants_text | TEXT | Yes | Thành phần tham dự (text tự do, in báo cáo) |
| departments_text | TEXT | Yes | Ban ngành tham dự (text tự do) |
| sort_order | INT | No | Trọng số sắp xếp thủ công (kéo thả) |
| status | TINYINT | No | 0=DRAFT, 1=PENDING, 2=PUBLISHED, 3=CANCELLED |
| week_number | TINYINT | No | Tuần trong năm (auto tính) |
| year | SMALLINT | No | Năm (auto tính) |
| approved_by | FK users | Yes | Người duyệt |
| approved_at | DATETIME | Yes | Thời điểm duyệt |
| created_by | FK users | No | Người tạo |
| updated_by | FK users | Yes | Người sửa cuối |
| created_at, updated_at, deleted_at | TIMESTAMP | Yes | SoftDeletes |

**Indexes cần tạo:**

- `idx_org_module_date (organization_id, module_type, event_date, session, status)` — Index chính cho query lịch tuần
- `idx_org_week (organization_id, year, week_number)` — Truy vấn nhanh theo tuần
- `idx_org_host (organization_id, host_id, event_date)` — Lọc lịch của 1 lãnh đạo
- `idx_org_driver (organization_id, driver_id, event_date)` — Lọc lịch của lái xe
- `idx_sort (organization_id, event_date, session, sort_order)` — Sắp xếp ma trận
- `FULLTEXT(content, location, preparation_unit, participants_text, departments_text)` — Tìm kiếm

## 3.3. Bảng `schedule_attachments` — Tài liệu đính kèm

Mỗi lịch có thể có nhiều tài liệu. Mỗi tài liệu có tên gọi riêng (do user nhập, khác với tên file gốc). File lưu trên disk với UUID để tránh trùng và bảo mật.

| Trường | Kiểu | NULL | Mô tả |
|---|---|---|---|
| id | BIGINT | No | Khóa chính |
| schedule_id | FK schedules | No | CASCADE delete khi xóa schedule |
| title | VARCHAR(255) | No | Tên tài liệu (user nhập, hiển thị trên UI) |
| file_name | VARCHAR(255) | No | Tên file gốc khi upload |
| file_path | VARCHAR(500) | No | Path lưu (vd: schedules/2026/05/uuid.pdf) |
| file_size | BIGINT | No | Dung lượng (bytes) |
| mime_type | VARCHAR(100) | No | application/pdf, image/png... |
| sort_order | INT | No | Thứ tự hiển thị (default 0) |
| uploaded_by | FK users | No | Người upload |
| created_at | TIMESTAMP | No | |

**Quy ước storage:**

- Disk: `scheduling` (custom disk hoặc `public` tùy cấu hình)
- Path pattern: `schedules/{year}/{month}/{uuid}.{ext}`
- Whitelist mime: pdf, doc, docx, xls, xlsx, ppt, pptx, png, jpg, jpeg, zip, rar
- Max size: 50MB/file, max 20 file/lịch
- Khi download: trả về với tên = `title.ext` (đẹp), không phải UUID

## 3.4. Bảng `schedule_notification_recipients` — Đối tượng nhận thông báo

Mỗi schedule có danh sách người/nhóm nhận thông báo. Khi tạo notifications, hệ thống expand group thành các user thực tế.

| Trường | Kiểu | NULL | Mô tả |
|---|---|---|---|
| id | BIGINT | No | Khóa chính |
| schedule_id | FK schedules | No | CASCADE delete |
| user_id | FK users | Yes | Người nhận trực tiếp (NULL nếu là group) |
| group_id | FK notification_groups | Yes | Nhóm nhận (NULL nếu là user) |
| created_at | TIMESTAMP | No | |

**Ràng buộc:** Mỗi record phải có `user_id` HOẶC `group_id`, không được cả 2 NULL.

## 3.5. Bảng `schedule_reminders` — Mốc nhắc của từng lịch

Mỗi lịch có nhiều mốc nhắc. Mỗi mốc có thể đến từ Preset (chọn từ danh sách mặc định) hoặc Custom (user tự định nghĩa cho lịch này). Khi chọn từ Preset, hệ thống **SNAPSHOT** giá trị tại thời điểm tạo — sau này admin sửa preset không ảnh hưởng lịch cũ.

| Trường | Kiểu | NULL | Mô tả |
|---|---|---|---|
| id | BIGINT | No | Khóa chính |
| schedule_id | FK schedules | No | CASCADE delete |
| minutes_before | INT | No | Số phút trước event |
| channels | JSON | No | `["FCM","ZALO","SMS","APP"]` |
| source | ENUM(PRESET, CUSTOM) | No | Nguồn gốc của mốc nhắc |
| preset_id | FK reminder_presets | Yes | Trace nguồn nếu source=PRESET |
| created_at | TIMESTAMP | No | |

## 3.6. Bảng `schedule_notifications` — Hàng đợi gửi

| Trường | Kiểu | NULL | Mô tả |
|---|---|---|---|
| id | BIGINT | No | Khóa chính |
| organization_id | FK organizations | No | Multi-tenant |
| schedule_id | FK schedules | No | CASCADE delete |
| user_id | FK users | No | Người nhận thực tế (đã expand từ group nếu có) |
| channel | ENUM(FCM, ZALO, SMS, APP) | No | Kênh gửi |
| remind_at | DATETIME | No | Thời điểm gửi |
| status | TINYINT | No | 0=PENDING, 1=SENT, 2=FAILED, 3=CANCELLED |
| retry_count | TINYINT | No | Số lần đã retry |
| external_message_id | VARCHAR(255) | Yes | ID từ FCM/Zalo để trace |
| error_message | TEXT | Yes | Lỗi nếu FAILED |
| sent_at | DATETIME | Yes | Thời điểm gửi thành công |
| created_at, updated_at | TIMESTAMP | No | |

**Indexes:** `(organization_id, status, remind_at)`, `(schedule_id)`, `(user_id, status)`

## 3.7. Bảng `notification_groups` & `notification_group_members`

Nhóm tài khoản để gửi thông báo cho nhiều người cùng lúc mà không phải chọn từng user. Ví dụ: nhóm "Lãnh đạo", nhóm "Thư ký Bí thư", nhóm "Phòng Hành chính".

| Trường | Kiểu | Mô tả |
|---|---|---|
| notification_groups.id | BIGINT PK | |
| notification_groups.organization_id | FK | Multi-tenant |
| notification_groups.name | VARCHAR(100) | Tên nhóm hiển thị |
| notification_groups.description | VARCHAR(255) | Mô tả |
| notification_groups.created_by | FK users | |
| notification_group_members.id | BIGINT PK | |
| notification_group_members.group_id | FK groups | CASCADE delete |
| notification_group_members.user_id | FK users | |
| UNIQUE (group_id, user_id) | | Mỗi user chỉ thuộc 1 group 1 lần |

## 3.8. Bảng `reminder_presets` — Mốc nhắc mặc định

| Trường | Kiểu | Mô tả |
|---|---|---|
| id | BIGINT PK | |
| organization_id | FK | NULL = preset hệ thống (chung tất cả org) |
| name | VARCHAR(100) | "Trước 1 ngày", "Trước 30 phút"... |
| minutes_before | INT | Số phút trước event |
| channels | JSON | `["FCM","ZALO","SMS"]` |
| is_default | BOOLEAN | Tự động checked khi tạo lịch mới |
| is_active | BOOLEAN | Tạm ngừng sử dụng |
| sort_order | INT | Thứ tự hiển thị trên UI |

## 3.9. Bảng `org_scheduling_settings` — Cấu hình duyệt theo phân hệ

Mỗi organization có 1 record cấu hình. Quyết định phân hệ nào cần duyệt, phân hệ nào tạo xong là PUBLISHED luôn.

| Trường | Kiểu | Mô tả |
|---|---|---|
| id | BIGINT PK | |
| organization_id | FK UNIQUE | Mỗi org 1 record |
| executive_requires_approval | BOOLEAN | Phân hệ Thường trực có cần duyệt |
| executive_approver_roles | JSON | Roles được phép duyệt lịch Thường trực |
| office_requires_approval | BOOLEAN | Phân hệ Văn phòng có cần duyệt |
| office_approver_roles | JSON | Roles được phép duyệt lịch Văn phòng |

## 3.10. Bảng `filter_presets` — Bộ lọc cá nhân

Cho phép mỗi user lưu lại các bộ lọc thường dùng để truy xuất nhanh. Ví dụ: "Lịch Bí thư tuần này", "Lịch họp với Sở Y tế".

| Trường | Kiểu | Mô tả |
|---|---|---|
| id | BIGINT PK | |
| user_id | FK users | Filter cá nhân (private) |
| organization_id | FK | Multi-tenant |
| name | VARCHAR(100) | Tên hiển thị |
| filters | JSON | State của tất cả filter parameters |
| is_default | BOOLEAN | Áp dụng tự động khi vào trang |

---

# PHẦN 4. ENUMS VÀ CONSTANTS

## 4.1. ModuleType — Phân hệ

```php
enum ModuleType: string {
    case EXECUTIVE = 'EXECUTIVE';  // Thường trực Thành ủy
    case OFFICE = 'OFFICE';        // Văn phòng Thành ủy
}
```

## 4.2. SessionType — Buổi (auto tính từ start_time)

```php
enum SessionType: string {
    case MORNING = 'S';    // hour < 12
    case AFTERNOON = 'C';  // 12 <= hour < 18
    case EVENING = 'T';    // hour >= 18

    public static function fromTime(string $startTime): self
    {
        $hour = (int) substr($startTime, 0, 2);
        return match(true) {
            $hour < 12 => self::MORNING,
            $hour < 18 => self::AFTERNOON,
            default => self::EVENING,
        };
    }
}
```

## 4.3. Nature — Tính chất cuộc họp

```php
enum Nature: string {
    case HOST = 'HOST';      // Chủ trì
    case ATTEND = 'ATTEND';  // Tham gia
}
```

## 4.4. ScheduleStatus — Trạng thái lịch

```php
enum ScheduleStatus: int {
    case DRAFT = 0;        // Nháp
    case PENDING = 1;      // Chờ duyệt
    case PUBLISHED = 2;    // Đã duyệt - chính thức
    case CANCELLED = 3;    // Hủy
}
```

## 4.5. NotificationChannel — Kênh thông báo

```php
enum NotificationChannel: string {
    case FCM = 'FCM';      // Firebase Cloud Messaging (mobile push)
    case ZALO = 'ZALO';    // Zalo Official Account (qua n8n)
    case SMS = 'SMS';      // SMS Brandname (qua n8n)
    case APP = 'APP';      // In-app realtime (qua Laravel Reverb)
}
```

## 4.6. NotificationStatus, ReminderSource, SortMode, ViewFilter

```php
enum NotificationStatus: int {
    case PENDING = 0;
    case SENT = 1;
    case FAILED = 2;
    case CANCELLED = 3;
}

enum ReminderSource: string {
    case PRESET = 'PRESET';    // Chọn từ mốc nhắc mặc định
    case CUSTOM = 'CUSTOM';    // Tùy chỉnh cho lịch này
}

enum SortMode: string {
    case TIME = 'time';         // Theo start_time ASC
    case POSITION = 'position'; // Theo host_priority_weight ASC
    case MANUAL = 'manual';     // Theo sort_order ASC (kéo thả)
}

enum ViewFilter: string {
    case PERSONAL = 'personal'; // Chỉ lịch liên quan đến mình
    case ALL = 'all';           // Toàn cơ quan
    case MANAGED = 'managed';   // Chỉ lịch mình tạo
}
```

---

# PHẦN 5. CÁC LUỒNG NGHIỆP VỤ CHI TIẾT

## 5.1. Luồng tạo lịch mới

### 5.1.1. Input từ Frontend

```
POST /api/scheduling/schedules
Content-Type: multipart/form-data (vì có file đính kèm)

{
  // Thông tin cuộc họp
  module_type: "EXECUTIVE",        // EXECUTIVE | OFFICE
  event_date: "2026-05-25",
  start_time: "08:00",
  end_time: "10:00",
  content: "Họp giao ban tuần",
  host_id: 12,
  location: "Phòng họp số 1",
  preparation_unit: "Văn phòng Thành ủy",
  participant_count: "Khoảng 15 người",
  nature: "HOST",
  driver_id: 25,
  color_code: "#FFD700",
  participants_text: "Toàn thể cán bộ Văn phòng, đại diện Sở Y tế",
  departments_text: "Sở Y tế, Sở Giáo dục & Đào tạo",

  // Tài liệu đính kèm (nhiều file, có title riêng)
  attachments: [
    { title: "Báo cáo Quý 1", file: <UploadedFile> },
    { title: "Slide trình bày", file: <UploadedFile> }
  ],

  // Đối tượng nhận thông báo (user và/hoặc nhóm)
  notification_recipients: {
    user_ids: [12, 25, 30],
    group_ids: [2, 5]
  },

  // Cấu hình mốc nhắc (mix preset và custom)
  reminders: [
    { source: "PRESET", preset_id: 1 },
    { source: "PRESET", preset_id: 3 },
    {
      source: "CUSTOM",
      minutes_before: 180,
      channels: ["FCM", "SMS"]
    }
  ]
}
```

### 5.1.2. Luồng xử lý Backend (CreateScheduleAction)

```
Bước 1: Authentication & Authorization
  - Middleware auth:sanctum xác thực user
  - StoreScheduleRequest validate input
  - Gate::authorize('create', Schedule::class)
  - Logic bổ sung:
    + Nếu module_type=EXECUTIVE và user role=thu-ky:
      → Validate host_id phải là Lãnh đạo phụ trách
    + Nếu module_type=OFFICE và user role=nhan-vien:
      → OK, không cần check host
    + Nếu user role=tong-hop hoặc admin: OK

Bước 2: Chuẩn bị dữ liệu (Action::prepare)
  - session = SessionType::fromTime(start_time)
  - week_number, year từ Carbon::parse(event_date)
  - host_priority_weight = User::find(host_id)->priority_weight
  - sort_order = max(sort_order) + 10 cho cùng (date, session, org)

Bước 3: Quyết định status (Action::determineStatus)
  - Đọc OrgSchedulingSettings của organization
  - Nếu module_type=EXECUTIVE và executive_requires_approval=true:
    → status = PENDING
  - Nếu module_type=OFFICE và office_requires_approval=true:
    → status = PENDING
  - Ngược lại → status = PUBLISHED

Bước 4: DB::transaction (Action::execute)
  4.1. INSERT schedules
  4.2. Lưu attachments:
       foreach $attachments as $att:
         - Validate file
         - $uuid = Str::uuid()
         - $path = $file->storeAs('schedules/'.$year.'/'.$month, $uuid.'.'.$ext, 'scheduling')
         - INSERT schedule_attachments
  4.3. Lưu notification recipients:
       foreach user_ids as $uid:
         INSERT schedule_notification_recipients (user_id=$uid)
       foreach group_ids as $gid:
         INSERT schedule_notification_recipients (group_id=$gid)
  4.4. Lưu reminders:
       foreach reminders as $r:
         if $r.source == 'PRESET':
           $preset = ReminderPreset::find($r.preset_id)
           INSERT schedule_reminders (
             minutes_before = $preset->minutes_before,  // SNAPSHOT
             channels = $preset->channels,              // SNAPSHOT
             source = 'PRESET',
             preset_id = $preset->id
           )
         else:
           INSERT schedule_reminders (CUSTOM values)

Bước 5: GenerateNotificationsAction
  - Lấy danh sách user thực tế (expand groups):
    $users = recipients.user_ids ∪ all users from recipients.group_ids
  - foreach schedule_reminders as $r:
    $remindAt = $startDatetime - $r.minutes_before minutes
    if $remindAt < now(): skip
    foreach $users as $user:
      foreach $r.channels as $channel:
        INSERT schedule_notifications (status=PENDING)
        SendReminderJob::dispatch($notif.id, $orgId)
          ->delay($remindAt)
          ->onQueue('scheduling')

Bước 6: Fire event
  event(new ScheduleCreated($schedule))

Bước 7: Return Response
  return new ScheduleResource($schedule->load([
    'host', 'driver', 'attachments', 'reminders',
    'notificationRecipients.user', 'notificationRecipients.group'
  ]));
```

### 5.1.3. Listeners của ScheduleCreated

- **BroadcastScheduleChange** — broadcast qua Reverb tới channel `organization.{orgId}.schedules` để các client khác refresh
- **LogAudit** — laravel-auditing tự động ghi nhận
- **SendImmediateNotification** (tùy chọn) — Gửi FCM ngay cho host khi được giao chủ trì

## 5.2. Luồng sửa lịch

```
PUT /api/scheduling/schedules/{id}

Authorize (SchedulePolicy::update):
  - user.can('schedule.update.any')           → OK
  - user.can('schedule.update.own') AND
    schedule.created_by == user.id            → OK
  - Else                                      → 403

UpdateScheduleAction::execute:
  1. Snapshot old values
  2. Update schedules (KHÔNG đổi status, giữ PUBLISHED nếu đang PUBLISHED)
  3. Xử lý attachments:
     - attachments_keep: [12, 15]  → giữ
     - attachments_delete: lấy danh sách hiện tại trừ keep
       → DELETE từ DB + Storage::delete(file_path)
     - attachments_update: { 12: "Tên mới" }
       → UPDATE title
     - attachments_new: thêm mới (như flow create)
  4. Sync notification_recipients (delete all + insert lại — đơn giản)
  5. Xử lý reminders (nếu reminders config thay đổi HOẶC thời gian lịch thay đổi):
     - CANCEL tất cả schedule_notifications PENDING
       UPDATE WHERE schedule_id=X AND status=PENDING
       SET status=CANCELLED
     - DELETE schedule_reminders cũ
     - INSERT lại theo input mới
     - Generate notifications mới + dispatch jobs
  6. event(new ScheduleUpdated($schedule, $changes))

Lưu ý: Job cũ khi chạy sẽ check notification.status,
       thấy CANCELLED → tự bỏ qua, không gửi.
```

## 5.3. Luồng xóa lịch (Soft Delete)

```
DELETE /api/scheduling/schedules/{id}

Authorize: delete.own (own creator) hoặc delete.any

DeleteScheduleAction:
  1. CANCEL tất cả notifications PENDING
  2. $schedule->delete()  // Soft delete (deleted_at = now)
  3. File đính kèm KHÔNG xóa vật lý — chỉ ẩn đi
     (Khi force delete thực sự mới xóa file storage)
  4. event(new ScheduleDeleted)
  5. Return 204 No Content

Restore (khôi phục):
POST /api/scheduling/schedules/{id}/restore
  - Chỉ role tong-hop hoặc admin
  - Set deleted_at = NULL
  - KHÔNG tự động phục hồi notifications (đã CANCELLED)
```

## 5.4. Luồng duyệt lịch

Áp dụng khi `org_scheduling_settings` có `require_approval=true` cho phân hệ tương ứng.

```
Sub-flow 1: Tạo lịch tự động chuyển sang PENDING
  → Đã xử lý ở Bước 3 của CreateScheduleAction

Sub-flow 2: Người duyệt xem danh sách chờ duyệt
  GET /api/scheduling/schedules?status=1&module_type=EXECUTIVE
  → Trả về các lịch PENDING của phân hệ tương ứng

Sub-flow 3: Duyệt
  POST /api/scheduling/schedules/{id}/approve
  Body: { note?: "Đã duyệt" }

  Authorize:
    - user.can('schedule.approve')
    - User có role nằm trong executive_approver_roles
      (nếu schedule là EXECUTIVE) hoặc office_approver_roles

  ApproveScheduleAction:
    1. schedule.status = PUBLISHED
    2. schedule.approved_by = user.id
    3. schedule.approved_at = now()
    4. event(new ScheduleApproved)

  Listeners:
    - Broadcast Reverb → các client refresh, lịch xuất hiện trên ma trận
    - Notify creator: "Lịch của bạn đã được duyệt"
    - Notify host + driver + recipients: "Lịch X đã chính thức"

Sub-flow 4: Từ chối
  POST /api/scheduling/schedules/{id}/reject
  Body: { reason: "Trùng giờ với lịch khác" }

  RejectScheduleAction:
    1. schedule.status = DRAFT (quay về nháp)
    2. Lưu reject_reason vào audit log
    3. Notify creator: "Lịch bị từ chối: {reason}"

LƯU Ý QUAN TRỌNG:
  - Job gửi notification CHỈ chạy nếu schedule.status == PUBLISHED
  - Nếu schedule còn PENDING tại thời điểm remind_at đến → Job bỏ qua
  - Sau khi APPROVE: nếu remind_at đã qua thì coi như miss,
    nếu remind_at còn ở tương lai thì job vẫn chạy đúng giờ
```

## 5.5. Luồng sao chép lịch (Duplicate)

```
POST /api/scheduling/schedules/{id}/duplicate
{
  target_dates: ["2026-05-27", "2026-05-29", "2026-06-01"]
}

DuplicateScheduleAction::execute:
  $source = Schedule::find($id);
  Gate::authorize('view', $source);
  Gate::authorize('create', Schedule::class);

  $newSchedules = [];

  foreach target_dates as $date:
    DB::transaction:
      1. Clone schedule:
         - Copy tất cả trường trừ id, created_at, approved_*
         - event_date = $date
         - week_number, year = recompute
         - status = DRAFT (user phải submit/approve lại)
         - sort_order = max + 10 của ngày đó
         - created_by = auth()->id()

      2. Copy attachments:
         foreach source.attachments:
           - Clone file storage (Storage::copy)
           - INSERT schedule_attachments (giữ title, file_name)

      3. Copy notification_recipients:
         INSERT lại với schedule_id mới

      4. Copy reminders:
         INSERT lại schedule_reminders (giữ source, preset_id)

      5. Generate notifications mới + dispatch jobs
         (chỉ với remind_at > now)

      6. event(new ScheduleCreated($newSchedule))

    $newSchedules[] = $newSchedule;

  return $newSchedules;  // Trả về danh sách lịch mới
```

## 5.6. Luồng sắp xếp (Reorder bằng kéo thả)

```
POST /api/scheduling/schedules/reorder
{
  event_date: "2026-05-25",
  session: "S",
  module_type: "EXECUTIVE",
  items: [
    { id: 102, sort_order: 10 },
    { id: 100, sort_order: 20 },
    { id: 101, sort_order: 30 }
  ]
}

ReorderScheduleAction:
  1. Authorize: user.can('schedule.reorder')

  2. Verify integrity:
     - All items thuộc cùng organization của user
     - All items có cùng (event_date, session, module_type)
     → Tránh user gửi mảng linh tinh sửa lịch khác buổi

  3. Bulk update bằng CASE WHEN (1 query duy nhất):
     UPDATE schedules
     SET sort_order = CASE id
       WHEN 102 THEN 10
       WHEN 100 THEN 20
       WHEN 101 THEN 30
     END,
     updated_by = {user.id},
     updated_at = NOW()
     WHERE id IN (102, 100, 101)
       AND organization_id = {orgId}
       AND event_date = '2026-05-25'
       AND session = 'S'

  4. event(new ScheduleReordered)

  5. Broadcast Reverb → các client khác auto refresh thứ tự

KỸ THUẬT QUAN TRỌNG:
  - Sort_order nên cách nhau bước 10 (10, 20, 30...)
    → Có chỗ chèn giữa mà không phải re-index toàn bộ
  - Khi khoảng cách bị thu hẹp (< 2):
    Cron weekly chạy rebalance lại
```

## 5.7. Bộ lọc thông minh và View Filter

### 5.7.1. Endpoint chính

```
GET /api/scheduling/schedules
  ?module_type=EXECUTIVE          # Phân hệ
  &view=personal                  # personal | all | managed
  &year=2026&week=21              # Tuần
  &from=2026-05-25&to=2026-05-31  # Hoặc khoảng ngày
  &host_id=12                     # Chủ trì cụ thể
  &driver_id=25                   # Lái xe
  &q=giao ban                     # Full-text search
  &session=S                      # Buổi
  &nature=HOST                    # Tính chất
  &status=2                       # Trạng thái (mặc định PUBLISHED)
  &sort_mode=position             # manual | time | position
  &per_page=50
  &page=1
```

### 5.7.2. Logic View Filter

```
view=all:
  → Không filter thêm (đã có organization_id qua Global Scope)
  → Yêu cầu permission schedule.view

view=personal:
  → WHERE (
      host_id = {user.id}
      OR driver_id = {user.id}
      OR EXISTS (
        SELECT 1 FROM schedule_notification_recipients snr
        WHERE snr.schedule_id = schedules.id AND (
          snr.user_id = {user.id}
          OR snr.group_id IN (
            SELECT group_id FROM notification_group_members
            WHERE user_id = {user.id}
          )
        )
      )
    )

view=managed:
  → WHERE created_by = {user.id}
  → Dùng cho người tạo lịch xem lại các lịch mình quản lý
```

### 5.7.3. Full-text Search

```
Tìm kiếm tự nhiên trên các trường:
  - content (nội dung)
  - location (địa điểm)
  - preparation_unit (đơn vị chuẩn bị)
  - participants_text (thành phần tham dự)
  - departments_text (ban ngành)

Phương án A (mặc định): MySQL LIKE
  $q = trim($request->q);
  $query->where(function($w) use ($q) {
    foreach (['content','location','preparation_unit',
              'participants_text','departments_text'] as $col) {
      $w->orWhere($col, 'LIKE', "%{$q}%");
    }
  });

Phương án B (tùy chọn nâng cao): MySQL FULLTEXT
  ALTER TABLE schedules ADD FULLTEXT INDEX ft_search
    (content, location, preparation_unit,
     participants_text, departments_text)
    WITH PARSER ngram;

  $query->whereRaw(
    "MATCH(content, location, preparation_unit,
           participants_text, departments_text)
     AGAINST(? IN NATURAL LANGUAGE MODE)",
    [$q]
  );

→ Với 14.400 records/năm, LIKE đủ nhanh.
  Khi vượt 50.000 records, chuyển sang FULLTEXT.
```

### 5.7.4. Sort Modes

```
sort_mode='time' (theo giờ bắt đầu):
  ORDER BY event_date ASC,
           FIELD(session, 'S', 'C', 'T') ASC,
           start_time ASC

sort_mode='position' (theo chức vụ chủ trì):
  ORDER BY event_date ASC,
           FIELD(session, 'S', 'C', 'T') ASC,
           host_priority_weight ASC,   -- denormalized, không cần JOIN
           start_time ASC

sort_mode='manual' (theo sort_order thủ công - mặc định):
  ORDER BY event_date ASC,
           FIELD(session, 'S', 'C', 'T') ASC,
           sort_order ASC
```

## 5.8. Luồng thông báo và nhắc lịch

### 5.8.1. Cơ chế delayed job (không dùng cron quét)

Thay vì cron quét bảng notifications mỗi phút (antipattern), module dùng Laravel Queue với delay. Khi tạo notification, hệ thống dispatch `SendReminderJob` với `delay($remind_at)`. Job tự kích hoạt đúng thời điểm, chính xác đến giây.

### 5.8.2. SendReminderJob::handle()

```php
public function handle(NotificationService $service): void
{
    // Bind tenant context cho Queue Worker
    app()->instance('current_organization_id', $this->organizationId);

    // Load notification
    $notif = ScheduleNotification::find($this->notificationId);
    if (!$notif || $notif->status !== NotificationStatus::PENDING) {
        return; // Đã bị xử lý hoặc hủy
    }

    // Load schedule
    $schedule = $notif->schedule()->with('host')->first();
    if (!$schedule || $schedule->trashed()) {
        $notif->update(['status' => NotificationStatus::CANCELLED]);
        return;
    }

    // Schedule còn PENDING/DRAFT → KHÔNG gửi
    if ($schedule->status !== ScheduleStatus::PUBLISHED) {
        $notif->update(['status' => NotificationStatus::CANCELLED]);
        return;
    }

    // Load user nhận
    $user = $notif->user;
    if (!$user || !$user->is_active) {
        $notif->update(['status' => NotificationStatus::CANCELLED]);
        return;
    }

    // Dispatch theo channel
    try {
        $messageId = match ($notif->channel) {
            NotificationChannel::FCM => app(FcmChannel::class)
                ->send($user, $schedule, $notif),
            NotificationChannel::ZALO => app(ZaloChannel::class)
                ->send($user, $schedule, $notif),
            NotificationChannel::SMS => app(SmsChannel::class)
                ->send($user, $schedule, $notif),
            NotificationChannel::APP => app(InAppChannel::class)
                ->send($user, $schedule, $notif),
        };

        $notif->update([
            'status' => NotificationStatus::SENT,
            'sent_at' => now(),
            'external_message_id' => $messageId,
        ]);

    } catch (\Throwable $e) {
        $notif->increment('retry_count');
        $notif->update([
            'status' => NotificationStatus::FAILED,
            'error_message' => $e->getMessage(),
        ]);
        throw $e; // Trigger retry của Horizon
    }
}

public int $tries = 3;
public int $backoff = 60;
```

### 5.8.3. Các Channel implementations

- **FcmChannel** — sử dụng `kreait/firebase-php`, lấy `user.fcm_token`, gửi qua Firebase Admin SDK
- **ZaloChannel** — webhook tới n8n, n8n forward tới Zalo OA API
- **SmsChannel** — webhook tới n8n, n8n forward tới SMS Brandname (VNPT/Viettel)
- **InAppChannel** — broadcast qua Reverb tới private channel `App.Models.User.{id}`

## 5.9. Luồng cho Lái xe (Driver View)

Lái xe có quyền hạn chế: chỉ xem lịch mình được phân công, chỉ thấy thông tin cơ bản.

```
GET /api/scheduling/driver/my-schedules?from=...&to=...

DriverScheduleController@index:
  - Authorize: user có role 'scheduling.lai-xe' OR
    auto filter qua middleware

  - Query:
    Schedule::where('driver_id', auth()->id())
      ->where('status', ScheduleStatus::PUBLISHED)
      ->whereBetween('event_date', [$from, $to])
      ->orderBy('event_date')
      ->orderBy('start_time')
      ->get();

  - Return DriverScheduleResource:

Trường ĐƯỢC xem:
  ✓ id, event_date, session, start_time, end_time
  ✓ content (nội dung — vì lái xe cần biết đi đâu)
  ✓ location
  ✓ host: { full_name, position }  -- Chỉ tên + chức vụ
  ✓ color_code
  ✓ status

Trường KHÔNG xem:
  ✗ participants_text
  ✗ departments_text
  ✗ attachments
  ✗ preparation_unit
  ✗ notification_recipients
  ✗ Lái xe khác (chỉ thấy lịch mình lái)
  ✗ approved_by, created_by

UI cho lái xe: List view dạng card, không phải ma trận
  → ScheduleListView.vue
  → Mỗi card hiển thị: Giờ - Tiêu đề - Địa điểm - Chủ trì
```

---

# PHẦN 6. XUẤT BÁO CÁO (EXCEL / PDF / WORD)

## 6.1. Yêu cầu chung

- Mẫu báo cáo theo chuẩn văn bản hành chính nhà nước
- Tiêu đề: "LỊCH CÔNG TÁC TUẦN ... TỪ NGÀY ... ĐẾN NGÀY ..."
- Layout ma trận: Buổi (hàng) × Ngày (cột) hoặc List theo ngày
- Gộp ô tự động khi nhiều cuộc họp cùng buổi
- Có header/footer chuẩn (logo, tên cơ quan, số trang)
- Filter trước khi xuất (theo phân hệ, theo tuần, theo lãnh đạo...)

## 6.2. Xuất Excel

### 6.2.1. Thư viện

Sử dụng `maatwebsite/excel` (chuẩn Danatec). Wrapper của PhpSpreadsheet.

### 6.2.2. Endpoint

```
GET /api/scheduling/schedules/export/excel
  ?year=2026&week=21
  &module_type=EXECUTIVE
  &host_id=12
  &include_draft=false

Authorize: user.can('schedule.export')
```

### 6.2.3. Class Export

```php
class WeeklyScheduleExcelExport implements
    FromCollection,
    WithHeadings,
    WithEvents,         // Hook để merge cells
    WithStyles,         // Format chữ, viền
    WithColumnWidths,
    WithTitle
{
    public function __construct(
        protected Collection $schedules,
        protected int $year,
        protected int $week,
        protected Organization $org
    ) {}

    public function collection(): Collection { ... }

    public function headings(): array {
        return ['Ngày', 'Buổi', 'Giờ', 'Nội dung',
                'Chủ trì', 'Địa điểm', 'Đơn vị chuẩn bị',
                'Lái xe', 'Ghi chú'];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet;

                // Title rows
                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1',
                    'LỊCH CÔNG TÁC TUẦN '.$this->week.'/'.$this->year);

                // Merge cells khi cùng ngày/buổi
                $this->mergeDateColumns($sheet);
                $this->mergeSessionColumns($sheet);

                // Apply color theo color_code
                $this->applyRowColors($sheet);

                // Set border, alignment toàn bảng
                ...
            }
        ];
    }

    public function styles(Worksheet $sheet) { ... }

    public function columnWidths(): array {
        return [
            'A' => 12, 'B' => 8, 'C' => 12, 'D' => 40,
            'E' => 25, 'F' => 20, 'G' => 25, 'H' => 18, 'I' => 30
        ];
    }
}
```

## 6.3. Xuất PDF

### 6.3.1. Thư viện

Sử dụng `barryvdh/laravel-dompdf`. Pure PHP, không cần cài binary, hỗ trợ Unicode tiếng Việt tốt nếu load font chuẩn.

### 6.3.2. Logic

```php
GET /api/scheduling/schedules/export/pdf?year=2026&week=21

ScheduleExportController@pdf:
  $schedules = $this->service->getWeeklySchedules($year, $week, $filters);

  $pdf = Pdf::loadView('scheduling::exports.pdf.weekly', [
      'schedules' => $schedules,
      'year' => $year,
      'week' => $week,
      'org' => Organization::find($orgId),
      'date_range' => $this->service->getWeekDateRange($year, $week)
  ])
  ->setPaper('a4', 'landscape')  // Ngang để chứa 7 ngày
  ->setOption('isHtml5ParserEnabled', true)
  ->setOption('isPhpEnabled', true);

  return $pdf->download("Lich-cong-tac-{$week}-{$year}.pdf");
```

### 6.3.3. Blade Template

File: `resources/views/scheduling/exports/pdf/weekly.blade.php`

- Layout HTML/CSS dạng table với border đậm
- Load font DejaVu Sans (chuẩn dompdf) hoặc Times New Roman
- Header: Logo, tên cơ quan, tiêu đề tuần
- Footer: Số trang, ngày in, người ký xác nhận
- Page break tự động khi tuần dài >1 trang

## 6.4. Xuất Word

### 6.4.1. Thư viện và phương án

Sử dụng `phpoffice/phpword`. Có 2 phương án triển khai:

- **Phương án A — Programmatic:** Tạo document từ code, linh hoạt nhưng code dài
- **Phương án B — Template (khuyến nghị):** Dùng file mẫu .docx có placeholder, code chỉ điền dữ liệu

### 6.4.2. Phương án Template (khuyến nghị)

```php
Bước 1: Tạo file template có placeholder
File: storage/app/scheduling/templates/weekly-schedule.docx

Nội dung mẫu:
  "LỊCH CÔNG TÁC TUẦN ${week_number}/${year}"
  "Từ ngày ${date_from} đến ngày ${date_to}"

  Bảng (1 row template):
  | ${row.day} | ${row.session} | ${row.time} |
  | ${row.content} | ${row.host} | ${row.location} |

Bước 2: Code xuất file

class WeeklyScheduleWordExporter
{
    public function export(int $year, int $week, array $filters): string
    {
        $templatePath = storage_path(
            'app/scheduling/templates/weekly-schedule.docx'
        );

        $template = new TemplateProcessor($templatePath);

        $template->setValue('week_number', $week);
        $template->setValue('year', $year);
        $template->setValue('date_from', $dateFrom->format('d/m/Y'));
        $template->setValue('date_to', $dateTo->format('d/m/Y'));
        $template->setValue('org_name', $org->name);

        // Clone row template cho mỗi dòng lịch
        $rows = $this->prepareRows($schedules);
        $template->cloneRowAndSetValues('row.day', $rows);

        $outputPath = storage_path(
            'app/temp/Lich-tuan-'.$week.'-'.$year.'-'.uniqid().'.docx'
        );
        $template->saveAs($outputPath);

        return $outputPath;
    }
}

// Controller:
return response()
    ->download($path)
    ->deleteFileAfterSend();
```

### 6.4.3. Lý do chọn Template

- Người không cần biết code (chuyên viên VP) vẫn chỉnh được mẫu — thay logo, đổi font, sửa wording
- Code đơn giản hơn 10 lần so với phương án programmatic
- Quan trọng với khu vực công: thường xuyên phải đổi mẫu theo công văn mới

## 6.5. Xuất file dung lượng lớn (>500 lịch)

```
Khi filter trả về > 500 records:
  - Không xuất sync (sẽ timeout)
  - Tách thành Queue Job: GenerateExportFileJob
  - Trả về 202 Accepted + job_id ngay

  Flow:
  1. Client: POST /api/scheduling/schedules/export/excel?async=true
  2. Server: dispatch job, return { job_id, status_url }
  3. Job chạy nền:
     - Generate file
     - Lưu vào storage/app/exports/{job_id}.xlsx
     - Update job_status table: status=COMPLETED, file_url
     - Broadcast Reverb: ExportCompleted event
  4. Client nhận event hoặc poll status:
     GET /api/scheduling/exports/{job_id}/status
  5. Download:
     GET /api/scheduling/exports/{job_id}/download
```

---

# PHẦN 7. KIẾN TRÚC MODULE VÀ CẤU TRÚC THƯ MỤC

## 7.1. Cấu trúc thư mục Backend

```
app/Modules/Scheduling/
├── Config/
│   └── scheduling.php                       # Config module
│
├── Database/
│   ├── Migrations/
│   │   ├── 2026_01_01_000001_create_schedules_table.php
│   │   ├── 2026_01_01_000002_create_schedule_attachments_table.php
│   │   ├── 2026_01_01_000003_create_schedule_notification_recipients_table.php
│   │   ├── 2026_01_01_000004_create_schedule_reminders_table.php
│   │   ├── 2026_01_01_000005_create_schedule_notifications_table.php
│   │   ├── 2026_01_01_000006_create_notification_groups_table.php
│   │   ├── 2026_01_01_000007_create_notification_group_members_table.php
│   │   ├── 2026_01_01_000008_create_reminder_presets_table.php
│   │   ├── 2026_01_01_000009_create_org_scheduling_settings_table.php
│   │   └── 2026_01_01_000010_create_filter_presets_table.php
│   └── Seeders/
│       ├── PermissionSeeder.php
│       ├── ReminderPresetSeeder.php
│       └── OrgSchedulingSettingsSeeder.php
│
├── Http/
│   ├── Controllers/
│   │   ├── ScheduleController.php           # CRUD lịch
│   │   ├── ScheduleApprovalController.php   # Duyệt/Từ chối
│   │   ├── ScheduleReorderController.php    # Kéo thả
│   │   ├── ScheduleDuplicateController.php  # Sao chép
│   │   ├── ScheduleExportController.php     # Xuất Excel/PDF/Word
│   │   ├── DriverScheduleController.php     # View riêng Lái xe
│   │   ├── NotificationGroupController.php  # CRUD nhóm
│   │   ├── ReminderPresetController.php     # CRUD preset
│   │   ├── FilterPresetController.php       # CRUD filter cá nhân
│   │   └── SettingsController.php           # Cấu hình duyệt
│   ├── Requests/
│   │   ├── StoreScheduleRequest.php
│   │   ├── UpdateScheduleRequest.php
│   │   ├── ReorderScheduleRequest.php
│   │   ├── DuplicateScheduleRequest.php
│   │   ├── StoreReminderPresetRequest.php
│   │   └── StoreNotificationGroupRequest.php
│   ├── Resources/
│   │   ├── ScheduleResource.php
│   │   ├── ScheduleCollection.php
│   │   ├── DriverScheduleResource.php       # Rút gọn cho Lái xe
│   │   ├── AttachmentResource.php
│   │   ├── NotificationGroupResource.php
│   │   └── ReminderPresetResource.php
│   └── Middleware/
│       └── ScopeOrganization.php            # Bind tenant context
│
├── Models/
│   ├── Schedule.php
│   ├── ScheduleAttachment.php
│   ├── ScheduleNotificationRecipient.php
│   ├── ScheduleReminder.php
│   ├── ScheduleNotification.php
│   ├── NotificationGroup.php
│   ├── NotificationGroupMember.php
│   ├── ReminderPreset.php
│   ├── OrgSchedulingSettings.php
│   └── FilterPreset.php
│
├── Enums/
│   ├── ModuleType.php
│   ├── SessionType.php
│   ├── Nature.php
│   ├── ScheduleStatus.php
│   ├── NotificationChannel.php
│   ├── NotificationStatus.php
│   ├── ReminderSource.php
│   ├── SortMode.php
│   └── ViewFilter.php
│
├── Actions/                                 # Business logic
│   ├── CreateScheduleAction.php
│   ├── UpdateScheduleAction.php
│   ├── DeleteScheduleAction.php
│   ├── DuplicateScheduleAction.php
│   ├── ApproveScheduleAction.php
│   ├── RejectScheduleAction.php
│   ├── ReorderScheduleAction.php
│   ├── GenerateRemindersAction.php
│   ├── GenerateNotificationsAction.php
│   └── ExpandGroupRecipientsAction.php
│
├── Services/                                # Coordinator / Query layer
│   ├── ScheduleService.php
│   ├── ScheduleFilterService.php
│   ├── ScheduleQueryBuilder.php
│   ├── NotificationService.php
│   ├── ApprovalConfigService.php
│   └── AttachmentService.php
│
├── Events/
│   ├── ScheduleCreated.php
│   ├── ScheduleUpdated.php
│   ├── ScheduleDeleted.php
│   ├── ScheduleApproved.php
│   ├── ScheduleRejected.php
│   └── ScheduleReordered.php
│
├── Listeners/
│   ├── BroadcastScheduleChange.php
│   ├── NotifyHostOnAssignment.php
│   └── NotifyOnApproval.php
│
├── Jobs/
│   ├── SendReminderJob.php
│   ├── GenerateExportFileJob.php
│   └── CleanupOldNotificationsJob.php       # Cron weekly
│
├── Notifications/
│   └── Channels/
│       ├── FcmChannel.php
│       ├── ZaloChannel.php
│       ├── SmsChannel.php
│       └── InAppChannel.php
│
├── Policies/
│   ├── SchedulePolicy.php
│   ├── NotificationGroupPolicy.php
│   ├── ReminderPresetPolicy.php
│   └── FilterPresetPolicy.php
│
├── Scopes/
│   └── OrganizationScope.php                # Global scope multi-tenant
│
├── Traits/
│   └── BelongsToOrganization.php
│
├── Exports/
│   ├── WeeklyScheduleExcelExport.php        # maatwebsite/excel
│   ├── WeeklySchedulePdfExporter.php        # barryvdh/laravel-dompdf
│   └── WeeklyScheduleWordExporter.php       # phpoffice/phpword
│
├── Routes/
│   ├── api.php
│   └── web.php
│
├── Resources/
│   ├── views/
│   │   └── exports/
│   │       ├── pdf/weekly.blade.php
│   │       └── word/templates/weekly-schedule.docx
│   └── lang/
│       └── vi/
│           └── scheduling.php
│
├── Providers/
│   ├── SchedulingServiceProvider.php
│   └── SchedulingEventServiceProvider.php
│
└── README.md
```

## 7.2. Cấu trúc Frontend Vue 3

```
resources/js/modules/scheduling/
├── views/
│   ├── ScheduleWeeklyView.vue              # Ma trận tuần (trang chính)
│   ├── ScheduleListView.vue                # List view (cho mobile / lái xe)
│   ├── ScheduleDetailView.vue              # Chi tiết 1 lịch
│   ├── DriverScheduleView.vue              # Trang riêng cho Lái xe
│   ├── NotificationGroupView.vue           # Quản lý nhóm
│   ├── ReminderPresetView.vue              # Quản lý preset
│   ├── PendingApprovalView.vue             # Danh sách lịch chờ duyệt
│   └── SettingsView.vue                    # Cấu hình duyệt
│
├── components/
│   ├── matrix/
│   │   ├── WeeklyMatrix.vue                # Ma trận chính
│   │   ├── MatrixCell.vue                  # Ô lịch (1 schedule)
│   │   ├── MatrixHeader.vue                # Hàng tiêu đề (T2-CN)
│   │   ├── SessionRow.vue                  # Hàng buổi (S/C/T)
│   │   └── EmptyCell.vue                   # Ô trống (click để thêm)
│   │
│   ├── form/
│   │   ├── ScheduleFormDialog.vue          # Form thêm/sửa
│   │   ├── AttachmentManager.vue           # Quản lý tài liệu (nhiều file + title)
│   │   ├── NotificationRecipientSelector.vue # Chọn user/nhóm nhận
│   │   ├── ReminderConfigurator.vue        # Cấu hình mốc nhắc
│   │   ├── HostSelector.vue                # Chọn chủ trì (filter theo role)
│   │   ├── DriverSelector.vue              # Chọn lái xe
│   │   ├── ColorPicker.vue
│   │   └── DuplicateDialog.vue             # Sao chép nhiều ngày
│   │
│   ├── filter/
│   │   ├── FilterPanel.vue                 # Panel filter chính
│   │   ├── ViewSwitcher.vue                # personal | all | managed
│   │   ├── SortModeSwitcher.vue
│   │   ├── ModuleTypeSwitcher.vue          # EXECUTIVE | OFFICE
│   │   ├── SearchBox.vue                   # Full-text search
│   │   └── FilterPresetManager.vue         # Lưu/xóa preset cá nhân
│   │
│   ├── approval/
│   │   ├── ApprovalBadge.vue
│   │   ├── ApproveActions.vue              # Nút duyệt/từ chối
│   │   └── RejectDialog.vue                # Form nhập lý do từ chối
│   │
│   ├── export/
│   │   └── ExportMenu.vue                  # Dropdown chọn Excel/PDF/Word
│   │
│   └── shared/
│       ├── UserAutocomplete.vue
│       ├── GroupAutocomplete.vue
│       ├── WeekNavigator.vue               # Tuần trước / tuần sau
│       └── PermissionWrapper.vue           # v-if check permission
│
├── composables/
│   ├── useScheduleApi.ts
│   ├── useScheduleRealtime.ts              # Subscribe Reverb
│   ├── useWeekNavigation.ts
│   ├── useFilterState.ts                   # URL sync filter
│   ├── useSchedulePermissions.ts
│   └── useDragDrop.ts
│
├── stores/                                 # Pinia
│   ├── schedule.store.ts
│   ├── filter.store.ts
│   ├── notification.store.ts
│   └── auth.store.ts                       # Trỏ Core auth
│
├── types/
│   ├── schedule.types.ts
│   ├── attachment.types.ts
│   ├── notification.types.ts
│   └── filter.types.ts
│
├── utils/
│   ├── sessionCalculator.ts                # FE cũng tính session
│   ├── colorHelper.ts
│   ├── dateFormatter.ts
│   └── permissionMap.ts
│
└── routes/
    └── index.ts                            # Route definitions module
```

---

# PHẦN 8. API ENDPOINTS

## 8.1. Lịch công tác

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/scheduling/schedules` | Danh sách + filter + pagination |
| POST | `/api/scheduling/schedules` | Tạo lịch mới |
| GET | `/api/scheduling/schedules/{id}` | Chi tiết lịch |
| PUT | `/api/scheduling/schedules/{id}` | Cập nhật lịch |
| DELETE | `/api/scheduling/schedules/{id}` | Xóa (soft delete) |
| POST | `/api/scheduling/schedules/{id}/restore` | Khôi phục lịch đã xóa |
| POST | `/api/scheduling/schedules/{id}/approve` | Duyệt lịch (PENDING → PUBLISHED) |
| POST | `/api/scheduling/schedules/{id}/reject` | Từ chối lịch |
| POST | `/api/scheduling/schedules/{id}/duplicate` | Sao chép sang nhiều ngày |
| POST | `/api/scheduling/schedules/reorder` | Bulk update sort_order (kéo thả) |
| GET | `/api/scheduling/schedules/week/{year}/{week}` | Lịch tuần dạng ma trận |

## 8.2. Tài liệu đính kèm

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/scheduling/schedules/{id}/attachments` | Danh sách attachments của 1 lịch |
| GET | `/api/scheduling/attachments/{id}/download` | Download file (tên = title.ext) |
| GET | `/api/scheduling/attachments/{id}/preview` | Preview ảnh / PDF |

## 8.3. Xuất báo cáo

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/scheduling/schedules/export/excel` | Xuất Excel (sync) |
| GET | `/api/scheduling/schedules/export/pdf` | Xuất PDF (sync) |
| GET | `/api/scheduling/schedules/export/word` | Xuất Word (sync) |
| POST | `/api/scheduling/exports` | Tạo export job (async cho file lớn) |
| GET | `/api/scheduling/exports/{job_id}/status` | Check status job |
| GET | `/api/scheduling/exports/{job_id}/download` | Download file khi job COMPLETED |

## 8.4. View riêng Lái xe

| Method | Endpoint | Mô tả |
|---|---|---|
| GET | `/api/scheduling/driver/my-schedules` | Lịch của lái xe (rút gọn) |
| GET | `/api/scheduling/driver/my-schedules/{id}` | Chi tiết (rút gọn) |

## 8.5. Quản lý cấu hình

| Method | Endpoint | Mô tả |
|---|---|---|
| GET\|POST\|PUT\|DELETE | `/api/scheduling/notification-groups` | CRUD nhóm tài khoản |
| GET\|POST\|PUT\|DELETE | `/api/scheduling/reminder-presets` | CRUD mốc nhắc mặc định |
| GET\|PUT | `/api/scheduling/settings` | Cấu hình duyệt theo phân hệ |
| GET\|POST\|DELETE | `/api/scheduling/filter-presets` | Bộ lọc cá nhân |

---

# PHẦN 9. THƯ VIỆN VÀ DEPENDENCIES

## 9.1. Backend - PHP 8.3 / Laravel 12

| Package | Version | Mục đích | Trạng thái |
|---|---|---|---|
| laravel/framework | ^12.0 | Core framework | Core |
| laravel/sanctum | ^4.0 | API authentication | Core (đã có) |
| spatie/laravel-permission | ^6.0 | RBAC: Role + Permission | Core (đã có) |
| spatie/laravel-medialibrary | ^11.0 | (Tùy chọn) File storage alternative | Core (đã có) |
| laravel/reverb | ^1.0 | WebSocket realtime | Core (đã có) |
| laravel/horizon | ^5.0 | Queue management + monitor | Core hoặc cài mới |
| maatwebsite/excel | ^3.1 | Xuất Excel với merge cells | Cài mới |
| phpoffice/phpword | ^1.3 | Xuất Word từ template | Cài mới |
| barryvdh/laravel-dompdf | ^3.0 | Xuất PDF (pure PHP, Unicode) | Cài mới |
| kreait/firebase-php | ^7.0 | Firebase Admin SDK cho FCM | Cài mới |
| owen-it/laravel-auditing | ^14.0 | Audit log tự động (yêu cầu pháp lý) | Cài mới |
| nesbot/carbon | ^3.0 | Xử lý ngày tháng, week_number | Có sẵn theo Laravel |
| knuckleswtf/scribe | ^4.0 | Auto generate API documentation | Cài mới (Tùy chọn) |

## 9.2. Frontend - Vue 3

| Package | Mục đích |
|---|---|
| vue ^3.5 | Framework chính |
| pinia ^2.2 | State management |
| vue-router ^4.4 | Routing |
| axios ^1.7 | HTTP client |
| vuedraggable ^4.1 | Drag-drop sắp xếp lịch |
| @vueuse/core ^11 | Composition utilities (useDebounce, useStorage...) |
| dayjs ^1.11 | Xử lý ngày tháng, plugin weekOfYear, isoWeek |
| laravel-echo ^1.16 | Client cho Reverb |
| pusher-js ^8.4 | Driver cho Echo |
| vee-validate ^4.13 + yup | Form validation |
| @headlessui/vue ^1.7 | UI primitives accessible (dialog, dropdown, listbox) |
| lucide-vue-next ^0.447 | Icons |
| tailwindcss ^3.4 | Utility CSS framework |
| vite ^5.4 | Build tool |

## 9.3. Lệnh cài đặt

**Backend (chỉ cài thêm những gì Core chưa có):**

```bash
composer require maatwebsite/excel
composer require phpoffice/phpword
composer require barryvdh/laravel-dompdf
composer require kreait/firebase-php
composer require owen-it/laravel-auditing

# Nếu Core chưa có:
composer require laravel/horizon
composer require knuckleswtf/scribe
```

**Frontend:**

```bash
npm install vuedraggable@^4.1
npm install dayjs
npm install vee-validate yup
npm install @headlessui/vue
npm install lucide-vue-next

# Nếu Core chưa có:
npm install pinia laravel-echo pusher-js
```

## 9.4. Hạ tầng (chuẩn Danatec)

```
Server: Ubuntu 22.04+
Quản lý: aaPanel hoặc Docker Compose
PHP: 8.3 (php-fpm)
Database: MySQL 8.0 (master + slave nếu HA)
Cache + Queue: Redis 7.x
File storage: Local hoặc MinIO (S3-compatible)
WebSocket: Laravel Reverb (port 8080)
Worker: Laravel Horizon (chạy background)
Reverse proxy: Nginx
External: n8n (middleware cho Zalo + SMS)
External: Firebase project cho FCM
```

---

# PHẦN 10. CẤU HÌNH MODULE

## 10.1. File config/scheduling.php

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tích hợp Core
    |--------------------------------------------------------------------------
    */
    'user_model' => \App\Models\User::class,
    'organization_model' => \App\Models\Organization::class,

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    */
    'route_prefix' => 'api/scheduling',
    'route_middleware' => ['auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    */
    'channels' => [
        'fcm' => env('SCHEDULING_FCM_ENABLED', true),
        'zalo' => env('SCHEDULING_ZALO_ENABLED', false),
        'sms' => env('SCHEDULING_SMS_ENABLED', false),
        'app' => env('SCHEDULING_APP_ENABLED', true),
    ],

    'zalo' => [
        'webhook_url' => env('ZALO_WEBHOOK_URL'),
        'oa_token' => env('ZALO_OA_TOKEN'),
    ],

    'sms' => [
        'webhook_url' => env('SMS_WEBHOOK_URL'),
        'brandname' => env('SMS_BRANDNAME', 'THANHUY'),
    ],

    'fcm' => [
        'credentials_path' => env(
            'FIREBASE_CREDENTIALS',
            storage_path('app/firebase-credentials.json')
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue & Storage
    |--------------------------------------------------------------------------
    */
    'queue' => env('SCHEDULING_QUEUE', 'scheduling'),

    'storage' => [
        'disk' => env('SCHEDULING_DISK', 'public'),
        'attachment_path' => 'schedules',
        'max_file_size' => 52428800, // 50MB
        'max_files_per_schedule' => 20,
        'allowed_mimes' => [
            'pdf','doc','docx','xls','xlsx','ppt','pptx',
            'png','jpg','jpeg','zip','rar'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Business Rules
    |--------------------------------------------------------------------------
    */
    'allow_past_schedule' => env('SCHEDULING_ALLOW_PAST', false),
    'notification_max_retries' => 3,
    'notification_retry_backoff' => 60,
    'sort_order_step' => 10,
    'sort_order_rebalance_threshold' => 2,

    /*
    |--------------------------------------------------------------------------
    | Export
    |--------------------------------------------------------------------------
    */
    'export' => [
        'word_template_path' => resource_path(
            'views/scheduling/exports/word/templates/weekly-schedule.docx'
        ),
        'pdf_font_path' => storage_path('fonts/'),
        'max_inline_records' => 500,  // > thì queue async
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    */
    'broadcast' => [
        'channel_prefix' => 'organization',
    ],
];
```

## 10.2. Biến môi trường (.env)

```bash
# Module Scheduling
SCHEDULING_DISK=public
SCHEDULING_QUEUE=scheduling
SCHEDULING_ALLOW_PAST=false

# Channels
SCHEDULING_FCM_ENABLED=true
SCHEDULING_ZALO_ENABLED=false
SCHEDULING_SMS_ENABLED=false
SCHEDULING_APP_ENABLED=true

# Firebase
FIREBASE_CREDENTIALS=/var/www/storage/app/firebase-credentials.json

# Zalo OA (qua n8n)
ZALO_WEBHOOK_URL=http://n8n:5678/webhook/scheduling-zalo
ZALO_OA_TOKEN=xxx

# SMS Brandname (qua n8n)
SMS_WEBHOOK_URL=http://n8n:5678/webhook/scheduling-sms
SMS_BRANDNAME=THANHUY

# Reverb
REVERB_APP_ID=scheduling
REVERB_APP_KEY=xxx
REVERB_APP_SECRET=xxx
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http
```

---

# PHẦN 11. ROADMAP TRIỂN KHAI

## 11.1. Phân chia Sprint

| Sprint | Thời gian | Nội dung | Deliverable |
|---|---|---|---|
| Sprint 0 | 0.5 tuần | Khảo sát, chốt yêu cầu, mockup UI | URD + Figma mockup |
| Sprint 1 | 1 tuần | Setup module, Migrations, Seeders, Models, Enums, Policies | Foundation database sẵn sàng |
| Sprint 2 | 2 tuần | CRUD Schedule + Actions + Services + FormRequests + Resources + Routes | API CRUD đầy đủ + Postman/Scribe docs |
| Sprint 3 | 1 tuần | Approval flow + Reorder + Duplicate + Filter + Search | Workflow nghiệp vụ hoàn chỉnh |
| Sprint 4 | 1 tuần | Notification module: Events, Listeners, Jobs, FCM channel, In-app Reverb | Thông báo FCM + Realtime hoạt động |
| Sprint 5 | 2 tuần | Vue 3: WeeklyMatrix, ScheduleFormDialog, Drag-drop, Filter Panel, Realtime sync | UI ma trận + form đầy đủ |
| Sprint 6 | 1 tuần | Notification Group, Reminder Preset, Filter Preset, Settings (CRUD + UI) | Quản lý cấu hình |
| Sprint 7 | 1 tuần | Export Excel, PDF, Word + template hành chính | Xuất báo cáo đầy đủ |
| Sprint 8 | 1 tuần | Zalo OA + SMS integration qua n8n + Driver view | Đa kênh thông báo + view lái xe |
| Sprint 9 | 1 tuần | Testing toàn diện, bug fix, security review, documentation | Sẵn sàng UAT |
| Sprint 10 | 0.5 tuần | UAT tại VP Thành ủy, training, go-live pilot | Vận hành chính thức |

**Tổng thời gian:** ~12 tuần (3 tháng) với team chuẩn

## 11.2. Phân chia nguồn lực

| Vai trò | Số lượng | Thời gian |
|---|---|---|
| Project Manager / BA | 1 | 3 tháng |
| Tech Lead (Backend) | 1 | 3 tháng |
| Backend Developer (Laravel) | 2 | 3 tháng |
| Frontend Developer (Vue 3) | 2 | 2.5 tháng (Sprint 5+) |
| QA / Tester | 1 | 2 tháng (Sprint 4+) |
| DevOps | 0.5 | Part-time |
| UI/UX Designer | 1 | Sprint 0 + hỗ trợ |

## 11.3. Phân tích rủi ro

| # | Rủi ro | Mức độ | Mitigation |
|---|---|---|---|
| 1 | Mất dữ liệu lịch của Lãnh đạo cấp cao | Cực cao | Backup real-time + MySQL replica + Daily snapshot + PITR |
| 2 | Xung đột data khi 2 user cùng sửa 1 lịch | Trung bình | Last-write-wins + Reverb notify, hoặc Optimistic locking với version column |
| 3 | Job notification trễ giờ hoặc miss | Cao | Horizon monitor, alert khi queue tắc; sử dụng delay job thay vì cron |
| 4 | Performance khi query 300 lịch/tuần với 50 user concurrent | Trung bình | Cache Redis 5 phút (invalidate khi event), Eager load, Index chuẩn |
| 5 | Tích hợp Zalo OA bị thay đổi API | Trung bình | Dùng n8n làm middleware, dễ swap |
| 6 | Lệ thuộc Firebase cho FCM | Thấp | Backup channel: APP (Reverb) luôn hoạt động |
| 7 | File export lớn (>500 lịch) gây timeout | Trung bình | Queue async + notification khi xong |
| 8 | Bảo mật: lộ thông tin lịch Lãnh đạo | Cao | Audit log mọi truy cập, HTTPS, Sanctum token với expiry |
| 9 | Người dùng quên cấu hình recipient khi tạo lịch | Thấp | UI auto-suggest: tự fill host + driver vào recipient |
| 10 | Sort_order chạy hết khoảng | Thấp | Cron weekly rebalance về (10, 20, 30...) |

---

# PHẦN 12. CHECKLIST CHO CLAUDE CODE TRIỂN KHAI

## 12.1. Thông tin cần cung cấp cho Claude Code

Trước khi yêu cầu Claude Code generate, đảm bảo cung cấp:

1. Path tuyệt đối của project Core (ví dụ: `/var/www/projects/danatec-core`)
2. Namespace User Core (mặc định `\App\Models\User`)
3. Namespace Organization Core (mặc định `\App\Models\Organization`)
4. Schema hiện tại của bảng `users` Core: có sẵn các trường nào (organization_id, priority_weight, fcm_token, phone, zalo_user_id...)
5. Phiên bản Laravel của Core (mặc định ^12.0)
6. Phiên bản PHP đang chạy (mặc định 8.3)
7. Core đã có sẵn package nào (Sanctum, Permission, MediaLibrary, Reverb, Horizon, Auditing)
8. File `.env` mẫu với các biến `SCHEDULING_*` đã setup
9. Template Word `weekly-schedule.docx` mẫu (hoặc cho phép Claude Code tự sinh)
10. Font tiếng Việt cho dompdf (DejaVuSans.ttf hoặc Times New Roman)

## 12.2. Thứ tự triển khai khuyến nghị

### Giai đoạn 1: Foundation Database

- Tạo thư mục `app/Modules/Scheduling/` với cấu trúc folder đầy đủ
- Cấu hình PSR-4 autoload trong `composer.json`
- Tạo `SchedulingServiceProvider` + đăng ký vào `bootstrap/providers.php`
- Tạo `config/scheduling.php` + publish
- Generate 10 migration files
- Generate Seeders: Permission, ReminderPreset, OrgSchedulingSettings
- Chạy migrate + seed

### Giai đoạn 2: Models & Enums

- Tạo 9 Enums
- Tạo 10 Eloquent Models với relationships đầy đủ
- Tạo Trait `BelongsToOrganization` + `OrganizationScope`
- Áp dụng trait `Auditable` (owen-it/laravel-auditing) cho Schedule

### Giai đoạn 3: Backend Logic

- Tạo Controllers (10 controllers)
- Tạo FormRequests với validation rules đầy đủ
- Tạo API Resources (ScheduleResource, DriverScheduleResource...)
- Tạo Policies cho từng resource
- Tạo Actions: CreateSchedule, UpdateSchedule, Approve, Duplicate, Reorder...
- Tạo Services: ScheduleService, NotificationService, ScheduleFilterService
- Tạo Routes API

### Giai đoạn 4: Events, Listeners, Jobs

- Tạo 6 Events + Listeners
- Tạo `SendReminderJob` với retry + delay
- Tạo 4 Channel implementations: FCM, Zalo, SMS, InApp
- Cấu hình Horizon cho queue `scheduling`

### Giai đoạn 5: Frontend Vue 3

- Setup module Vue trong `resources/js/modules/scheduling/`
- Tạo Pinia stores
- Tạo composables (useScheduleApi, useScheduleRealtime...)
- Tạo components: WeeklyMatrix, ScheduleFormDialog, FilterPanel...
- Tích hợp Laravel Echo + Reverb
- Setup routing

### Giai đoạn 6: Export

- Tạo WeeklyScheduleExcelExport với WithEvents
- Tạo Blade template PDF + WeeklySchedulePdfExporter
- Tạo template .docx + WeeklyScheduleWordExporter
- Tạo `GenerateExportFileJob` cho async export

## 12.3. Tiêu chí nghiệm thu từng giai đoạn

| Giai đoạn | Tiêu chí nghiệm thu |
|---|---|
| 1. Database | Migrations chạy thành công, Seeders insert đúng, schema khớp tài liệu |
| 2. Models | Eloquent relationships test pass, Global Scope tự động filter org_id |
| 3. Backend | Postman test pass 100% endpoints, Policies authorize đúng |
| 4. Notifications | Tạo lịch → notification được dispatch + gửi đúng giờ + đúng kênh |
| 5. Frontend | Tạo/sửa/xóa lịch UI hoạt động, drag-drop, filter, realtime sync |
| 6. Export | Xuất Excel/PDF/Word khớp mẫu hành chính, file mở được không lỗi |

---

# PHỤ LỤC

## A. Các quyết định thiết kế quan trọng

| # | Quyết định | Lý do |
|---|---|---|
| 1 | Cho phép trùng lịch tự do, không cảnh báo conflict | Trong cơ quan hành chính, 1 lãnh đạo có thể có 2 việc cùng giờ; conflict check gây phiền cho user |
| 2 | Dùng Job với delay, không dùng cron quét | Chính xác đến giây, tiết kiệm resource, Horizon quản lý dễ |
| 3 | Denormalize host_priority_weight vào schedules | Tránh JOIN với users khi sort_mode=position, tăng tốc query |
| 4 | Sort_order cách nhau bước 10 | Có chỗ chèn giữa mà không phải re-index toàn bộ |
| 5 | Snapshot preset value vào schedule_reminders | Sau này admin sửa preset không ảnh hưởng lịch đã tạo |
| 6 | Tách Thành phần (text) và Người nhận thông báo (FK) | Linh hoạt: thành phần là text in báo cáo, người nhận là user để gửi notif |
| 7 | Soft Delete cho schedules | Audit trail, có thể khôi phục khi xóa nhầm |
| 8 | File đính kèm lưu UUID làm tên | Tránh trùng, bảo mật, vẫn hiển thị title đẹp |
| 9 | Multi-tenant qua Global Scope tự động | Defense in depth, dev không phải nhớ filter org_id |
| 10 | 2 phân hệ dùng chung 1 bảng schedules, phân biệt qua module_type | Tái sử dụng code tối đa, dễ tổng hợp báo cáo chung |

## B. Phạm vi GIAI ĐOẠN 1 (KHÔNG bao gồm)

Để giữ scope module gọn, các tính năng sau **KHÔNG nằm trong giai đoạn 1**, sẽ phát triển ở giai đoạn sau nếu có nhu cầu:

- Lịch định kỳ (recurring schedules) tự động sinh hàng tuần/tháng
- Ủy quyền dự họp (delegation) — chuỗi ủy quyền nhiều cấp
- Conflict detection — cảnh báo trùng giờ
- Mobile app native (Flutter/React Native) — giai đoạn 1 chỉ Web responsive
- Tích hợp với eOffice, eCabinet, Phòng họp không giấy
- Đồng bộ Google Calendar / Outlook / iCal (.ics)
- Độ Mật cuộc họp (Confidentiality levels)
- Quiet hours (giờ không nhận thông báo)
- Báo cáo thống kê / Dashboard biểu đồ
- API public cho cổng thông tin điện tử
- Offline mode cho mobile
- 2FA riêng cho lịch Mật

## C. Tài liệu tham khảo

- Laravel 12 Documentation: https://laravel.com/docs/12.x
- Vue 3 Documentation: https://vuejs.org/
- spatie/laravel-permission: https://spatie.be/docs/laravel-permission
- maatwebsite/excel: https://docs.laravel-excel.com/
- phpoffice/phpword: https://phpword.readthedocs.io/
- Laravel Reverb: https://reverb.laravel.com/
- Laravel Horizon: https://laravel.com/docs/horizon
- Tailwind CSS: https://tailwindcss.com/

---

*--- KẾT THÚC TÀI LIỆU ---*

*Tài liệu này được biên soạn để phục vụ Claude Code và đội ngũ phát triển triển khai module.*

*© 2026 Công ty Cổ phần Công nghệ Danatec*
