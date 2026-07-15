# Kiến trúc Hệ thống — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Tài liệu tham chiếu nhanh về tech stack, patterns và các quyết định kiến trúc. Đọc [guide/GETTING_STARTED.md](../guide/GETTING_STARTED.md) trước nếu bạn là dev mới.

---

## Tech Stack

| Layer | Công nghệ | Version | Ghi chú |
|---|---|---|---|
| Framework | Laravel | 12 | PHP 8.2+ |
| Database | MySQL | 8.x | qua Sail/Docker |
| Auth | laravel/sanctum | Bearer token | — |
| Permission | spatie/laravel-permission | v7, teams mode | Guard: `web` |
| Media | spatie/laravel-medialibrary | — | Tất cả upload qua MediaService |
| Excel | maatwebsite/excel | — | Export + Import |
| Push notification | kreait/firebase-php | — | FCM multi-device |
| API docs | knuckleswtf/scribe | — | Sinh từ PHPDoc controller |
| Tree structure | kalnoy/nestedset | — | Organization tree |
| Geo lookup | stevebauman/location | — | IP → country cho log |
| Queue/Worker | Laravel Horizon | — | Redis driver |
| WebSocket | Laravel Reverb | — | Realtime presence |
| Cache / Lock | Redis (predis/predis) | — | Không dùng phpredis extension |
| Test | PHPUnit | 11 | — |
| Dev runner | Laravel Sail | — | Docker compose |

**Timezone:** `Asia/Ho_Chi_Minh` — KHÔNG giả định UTC.

---

## Pattern kiến trúc

### Modular Monolith

Toàn bộ code nghiệp vụ tổ chức thành module trong `/app/Modules/`:

```
app/Modules/
  Auth/              ← Đăng nhập, SSO, password
  Core/              ← User, role, permission, org, setting, log, notification config
  Meeting/           ← Quản lý cuộc họp
  TaskAssignment/    ← Giao việc, báo cáo, reminder
```

**Tại sao Modular Monolith thay Microservice:** Đội nhỏ, overhead vận hành microservice lớn, các module chia sẻ cùng DB và auth model. Chi tiết: [decisions/](../decisions/).

### DDD Light

- **Entity** = Eloquent Model
- **Service** = Application Service (business logic)
- **Event** = Domain Event (fire sau action nghiệp vụ)
- Không dùng Repository pattern — dùng thẳng Eloquent trong Service

### Luồng request chuẩn

```
HTTP Request
  → Middleware pipeline (auth:sanctum → set.permissions.team → log.activity)
  → Controller (validate FormRequest → gọi Service → trả response)
  → Service (business logic, DB::transaction nếu cần)
  → Event (nếu có side-effect)
  → Listener → Job → Queue
```

---

## Cấu trúc thư mục chuẩn 1 module

```
app/Modules/{Module}/
  Controllers/         ← Mỏng: validate → gọi service → trả response
  Services/            ← Business logic ({Resource}Service)
  Models/              ← Eloquent + HasOrganizationScope nếu tenant
  Requests/            ← FormRequest với rules/messages/attributes/bodyParameters
  Resources/           ← API resource (model → JSON)
  Routes/              ← Route file riêng, require từ routes/api.php
  Enums/               ← Status, type enums với values() + rule()
  Exports/             ← Maatwebsite export class
  Imports/             ← Maatwebsite import class
  Observers/           ← Eloquent lifecycle (data integrity only)
  Events/              ← Domain events (khi có EDA)
  Listeners/           ← Event handlers
  Jobs/                ← Background jobs
  Notifications/       ← Laravel Notification class
  Console/Commands/    ← Artisan commands + schedule
  Policies/            ← Authorization policy (tùy chọn)
  Concerns/            ← Trait nội bộ module (tùy chọn)
```

Module tham chiếu: `app/Modules/Meeting/` — có đủ tất cả các folder.

---

## Quy ước đặt tên

| Loại | Convention | Ví dụ |
|---|---|---|
| DB column / API field | `snake_case` | `organization_id`, `created_at` |
| PHP class | `PascalCase` | `TaskAssignmentService` |
| PHP method | `camelCase` | `bulkUpdateStatus()` |
| Permission | `{resource-kebab}.{actionCamel}` | `meeting-rooms.bulkDestroy` |
| URL path | `kebab-case`, plural | `/task-assignment-items` |
| Enum class | `{Name}Enum` | `MeetingStatusEnum` |
| Service class | `{Resource}Service` | `MeetingRoomService` |
| Event class | PascalCase, động từ quá khứ | `HoSoPheDuyetEvent` |
| Listener class | `XuLyKhiXxx` / `SendXxxOn` | `GuiThongBaoKhiPheDuyet` |

---

## Các quyết định quan trọng

| Quyết định | Lý do tóm tắt |
|---|---|
| Guard `web` cho cả API Sanctum | Spatie Permission teams mode cần 1 guard duy nhất |
| `DELETE /bulk-delete` thay `POST` | RESTful, Laravel parse JSON body cho DELETE bình thường |
| `ShouldDispatchAfterCommit` cho Event | Tránh Listener nhận Event trước khi transaction commit |
| `predis/predis` thay phpredis extension | Dễ cài hơn, không cần PHP extension, đủ dùng cho tải hiện tại |
| `withoutGlobalScope` khi seed/import | `HasOrganizationScope` active sẽ filter sai khi không có org context |

Chi tiết từng quyết định: xem thư mục [decisions/](../decisions/).
