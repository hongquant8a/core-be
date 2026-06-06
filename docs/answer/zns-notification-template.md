# ZNS Notification Template — Thiết kế

## Tổng quan

- **1 module = 1 ZNS template**. Mỗi module (`meeting`, `task_assignment`, `scheduling`) có 1 cấu hình template dùng chung cho mọi event trong module đó.
- Mỗi template có 2 field: `template_id` (ZNS template ID từ Zalo) + `variable_mapping` (JSON `{"be_key": "zns_key", ...}` map biến BE sang tên biến trong ZNS template).
- **Event** được phân biệt qua biến `event` trong context (ví dụ `meeting_published`, `meeting_cancelled`), template ZNS dùng biến này để chọn nội dung phù hợp.
- Mỗi ContentBuilder tự khai báo `znsContext()` (data gửi ZNS) và `znsVariables()` (mô tả biến cho FE).

## Luồng

```
ContentBuilder::znsContext()
  → trả về flat array key-value (customer_name, gender, meeting_title, start_time, event, title, code_id, ...)
  → dữ liệu lấy từ cùng nguồn với email blade

BuildZns (trait dùng chung)
  → buildZnsPayload() gọi znsContext(), check phone, tạo NotificationPayload

SendDeliveryJob::handle()
  → builder->build() lấy payload
  → nếu channel là zalo_zns:
      → resolve() template active cho (org, module, channel)
      → applyMapping: remap context BE → ZNS key theo variable_mapping
      → set _template_id = template->template_id
      → gửi 1 ZNS
  → nếu không có template → skip delivery

ZaloZnsChannel::send()
  → template_id = payload->context['_template_id']
  → template_data = payload->context (đã remap), bỏ _template_id
```

## Interface ContentBuilder

```php
interface ContentBuilder
{
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload;
    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string;
    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string;
    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array;

    // Dữ liệu flat key-value cho ZNS template, lấy từ cùng nguồn với email blade
    public function znsContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array;

    // Map { key => "Mô tả tiếng Việt" } cho FE hiển thị available variables
    public function znsVariables(): array;
}
```

## Trait BuildZns

```php
trait BuildZns
{
    protected function buildZnsPayload(User $recipient, Model $notifiable): ?NotificationPayload
    {
        if (! $recipient->phone) return null;

        return new NotificationPayload(
            channels: ['zalo_zns'],
            recipient: new Recipient(phone: $recipient->phone, name: $recipient->name),
            content: $this->shortBody($recipient, $notifiable),
            context: $this->znsContext($recipient, $notifiable),
        );
    }
}
```

Tất cả 13 ContentBuilder dùng `use BuildZns;` và gọi `'zalo_zns' => $this->buildZnsPayload($recipient, $notifiable)` — không cần `toZaloZns()` riêng.

## ZNS Context chuẩn

Mỗi builder trả về các biến BE trong `znsContext()`, đã được format human-readable:

| Biến | Mô tả | Ví dụ |
|------|-------|-------|
| `customer_name` | Tên người nhận | Nguyễn Văn A |
| `gender` | Giới tính (Anh/Chị/Anh-Chị) | Anh |
| `title` | Tiêu đề thông báo (từ `title()`) | Bạn được mời tham dự cuộc họp |
| `event` | Loại sự kiện (tiếng Việt) | Cuộc họp được phát hành |
| `code_id` | ID của resource (không prefix path) | 42 |
| (model fields) | Trường từ notifiable model | |
| `meeting_title` | Tiêu đề cuộc họp | Họp Q1 |
| `task_name` | Tên công việc | Làm báo cáo |
| `schedule_content` | Nội dung lịch công tác | Họp giao ban |
| `document_name` | Tên văn bản (nếu có) | QĐ số 123 |
| (thời gian) | Format human text `H:i d/m/Y` | 08:00 15/06/2026 |
| `start_time` | Thời gian bắt đầu cuộc họp | |
| `event_date` | Ngày diễn ra lịch công tác | |
| `deadline` | Thời hạn công việc | |
| (reminder) | Chỉ có ở Reminder/MeetingReminder | |
| `moment` | Loại nhắc (tiếng Việt) | Trước cuộc họp / Đến hạn |

## Mục đích các API

Hệ thống phục vụ 2 luồng: **Cấu hình template** (admin) và **Gửi ZNS** (tự động).

### Luồng cấu hình (admin)

- `GET /variables?module=meeting` — trả về danh sách event + union các biến BE có sẵn trong module. FE dùng để hiển thị cho admin biết những biến nào có thể map.
- `GET /?module=meeting` — danh sách template đã cấu hình cho module.
- `POST /` — tạo template mới. Body: `module_key` + `template_id` + `variable_mapping` (JSON map BE→ZNS).
- `PUT /{id}` — cập nhật `template_id`, `variable_mapping`, `status`.
- `DELETE /{id}` — xóa template.

### Luồng gửi ZNS (tự động)

1. `SendDeliveryJob` chạy → `ContentBuilder::build()` → `BuildZns` build payload từ `znsContext()`
2. Resolve template active cho `(org, module, channel)`, ưu tiên org-specific, fallback system
3. Có template → remap context theo `variable_mapping` → gửi 1 ZNS
4. Không có template → skip delivery

## Bảng `notification_templates`

| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | bigint PK | |
| organization_id | bigint nullable | null = system default |
| module_key | varchar | meeting, scheduling, task_assignment |
| event_key | varchar nullable | giữ để tham khảo, không dùng trong unique |
| channel | varchar | zalo_zns |
| template_id | varchar | ZNS template ID từ Zalo |
| variable_mapping | json | `{"be_key": "zns_key", ...}` |
| is_default | boolean | |
| status | varchar | active / inactive |
| created_by | bigint nullable | |
| updated_by | bigint nullable | |
| timestamps | | |

Unique: `(organization_id, module_key, channel)`

## API Endpoints

| Method | Path | Permission | Mô tả |
|--------|------|------------|-------|
| `GET` | `/api/notification-templates/variables?module=meeting` | `notifications.templates.variables` | Events + union BE variables |
| `GET` | `/api/notification-templates?module=meeting` | `notifications.templates.index` | List template configs |
| `POST` | `/api/notification-templates` | `notifications.templates.store` | Tạo template |
| `PUT` | `/api/notification-templates/{id}` | `notifications.templates.update` | Cập nhật template |
| `DELETE` | `/api/notification-templates/{id}` | `notifications.templates.destroy` | Xóa template |

### Request/Response

**POST** (tạo template cho module meeting):
```json
{
    "module_key": "meeting",
    "channel": "zalo_zns",
    "template_id": "263628",
    "variable_mapping": {
        "customer_name": "dai_bieu",
        "meeting_title": "ky_hop",
        "start_time": "ngay_gio",
        "event": "loai_thong_bao"
    },
    "status": "active"
}
```

**PUT** (cập nhật mapping):
```json
{
    "template_id": "263629",
    "variable_mapping": {
        "customer_name": "dai_bieu",
        "meeting_title": "ky_hop",
        "start_time": "ngay_gio",
        "event": "loai_thong_bao"
    },
    "status": "active"
}
```

**GET /** (danh sách template của module):
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "organization_id": null,
            "module_key": "meeting",
            "event_key": null,
            "channel": "zalo_zns",
            "template_id": "263628",
            "variable_mapping": {
                "customer_name": "dai_bieu",
                "meeting_title": "ky_hop",
                "start_time": "ngay_gio",
                "event": "loai_thong_bao"
            },
            "is_default": false,
            "status": "active",
            "created_by": 1,
            "updated_by": 1,
            "created_at": "08:00 15/06/2026",
            "updated_at": "08:00 15/06/2026"
        }
    ]
}
```

**GET /variables?module=meeting** (trả về union variables + danh sách event):
```json
{
    "success": true,
    "data": {
        "module_key": "meeting",
        "module_label": "Cuộc họp",
        "variables": {
            "customer_name": "Tên người nhận",
            "gender": "Giới tính",
            "meeting_title": "Tiêu đề cuộc họp",
            "start_time": "Thời gian bắt đầu",
            "event": "Loại sự kiện",
            "code_id": "Mã phiên họp",
            "title": "Tiêu đề thông báo"
        },
        "events": [
            {"key": "meeting_published", "label": "Cuộc họp đã được phát hành"},
            {"key": "meeting_updated", "label": "Cuộc họp đã cập nhật thông tin"},
            {"key": "meeting_cancelled", "label": "Cuộc họp đã bị hủy"},
            {"key": "meeting_reminder_before", "label": "Nhắc trước cuộc họp"},
            {"key": "meeting_reminder_on", "label": "Nhắc đến giờ họp"},
            {"key": "meeting_reminder_after", "label": "Nhắc sau cuộc họp"}
        ]
    }
}
```

## Cách FE sử dụng

1. Admin chọn module → FE gọi `GET /variables?module=...` lấy available variables + danh sách event
2. FE hiển thị form: nhập `template_id`, nhập `variable_mapping` (chọn be_key từ available variables → nhập zns_key tương ứng trong ZNS template)
3. FE gọi `POST /` để tạo, `PUT /{id}` để cập nhật
4. Mỗi module chỉ có 1 template — FE kiểm tra `GET /` xem đã có chưa
5. Trong ZNS template trên Zalo, admin dùng biến `event` để phân nhánh nội dung theo loại thông báo

## Service: NotificationTemplateService

- `resolve(int $organizationId, string $moduleKey, string $channel): ?NotificationTemplate` — lấy template active, ưu tiên org-specific, fallback system
- `applyMapping(NotificationTemplate $template, array $beContext): array` — remap BE keys → ZNS keys theo `variable_mapping`
- `getVariables(string $moduleKey): array` — trả về events + union variables từ tất cả ContentBuilder trong module

## Permission

```
notifications.templates.index
notifications.templates.store
notifications.templates.update
notifications.templates.destroy
notifications.templates.variables
```

Guard: `web` (Spatie Permission)
