# Thiết kế cấu trúc dự án

Tài liệu mô tả cấu trúc thư mục hiện tại của hệ thống theo hướng modular.

## 1) Tổng quan thư mục gốc

```text
quandh-core/
├── app/
├── bootstrap/
├── config/
├── database/
├── docs/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── artisan
├── compose.yaml
├── composer.json
├── package.json
└── phpunit.xml
```

## 2) Cấu trúc `app/` ngoài Modules

```text
app/
├── Console/
│   └── Commands/          # Artisan commands (cleanup seeds, simulate notifications/reminders)
├── Http/
│   └── Controllers/       # Controller chung (DeployController, ...)
├── Modules/               # Xem mục 2b
├── Providers/             # AppServiceProvider, HorizonServiceProvider, NotificationServiceProvider
└── Services/
    ├── Notification/      # Engine notification xuyên module (dispatcher, job, channel senders)
    └── Zalo/              # Zalo OA integration service
```

## 2b) Cấu trúc module trong `app/Modules`

```text
app/Modules/
├── Auth/
│   ├── AuthController.php   # Controller ở root (lịch sử)
│   ├── SsoController.php
│   ├── Jobs/
│   ├── Requests/
│   ├── Routes/
│   └── Services/
├── Core/
│   ├── *Controller.php      # Controllers đặt thẳng ở root Core (lịch sử, không phải lỗi)
│   ├── Enums/
│   ├── Exports/
│   ├── Imports/
│   ├── Middleware/
│   ├── Models/              # User, Organization, UserPreference, …
│   ├── Observers/
│   ├── Requests/
│   ├── Resources/
│   ├── Routes/
│   ├── Services/
│   ├── Support/
│   └── Traits/
├── TaskAssignment/
│   ├── Controllers/
│   ├── Enums/
│   ├── Exports/
│   ├── Imports/
│   ├── Models/
│   ├── Observers/
│   ├── Requests/
│   ├── Resources/
│   ├── Routes/
│   └── Services/
└── Meeting/                 # Module phức tạp — có thêm folder tùy chọn
    ├── Controllers/
    ├── Concerns/            # Trait dùng chung nội bộ module
    ├── Enums/
    ├── Events/              # Domain events
    ├── Exports/
    ├── Imports/
    ├── Middleware/
    ├── Models/
    ├── Observers/
    ├── Policies/            # Laravel authorization policies
    ├── Requests/
    ├── Resources/
    ├── Routes/
    └── Services/
```

> Các folder tùy chọn (`Concerns/`, `Events/`, `Middleware/`, `Policies/`) chỉ tạo khi thực sự cần — không bắt buộc cho mọi module.

## 3) Quy ước luồng xử lý

- `Controller`: nhận request, gọi `FormRequest` validate, điều phối `Service`, trả response chuẩn.
- `Service`: xử lý nghiệp vụ và transaction.
- `Model`: định nghĩa quan hệ + scope filter/sort.
- `Resource`: chuẩn hóa output API.
- `Routes`: tách riêng theo module và resource.

## 4) Vị trí tài liệu liên quan

- Tài liệu API (generate): `docs/api/`
- Phân tích nghiệp vụ/đề xuất: `docs/answer/`
- Thiết kế cơ sở dữ liệu: `docs/DATABASE_DESIGN.md`
- Changelog cho FE khi BE đổi API: `docs/changelogs/` (format `.md` hoặc `.txt`)
- Hướng dẫn flow notification: `docs/guides/`
- Specs + plans cho feature lớn: `docs/superpowers/specs/` và `docs/superpowers/plans/`
- Onboarding dev mới: `docs/ONBOARDING.md`

## 5) Kiểm tra cập nhật tài liệu khi thay đổi kiến trúc

Khi thêm module mới hoặc thay đổi cấu trúc lớn, cần cập nhật đồng thời:

- `docs/STRUCTURE_DESIGN.md` (file này).
- `docs/DATABASE_DESIGN.md` nếu có migration mới.
- `docs/api/*.md` và tài liệu Scribe nếu thay đổi controller/endpoint API.

## 6) Quy ước multi-tenant theo tổ chức

- Các module nghiệp vụ có dữ liệu theo tổ chức (hiện tại: `TaskAssignment`, `Meeting`) phải có cột `organization_id` trên bảng chính.
- Mọi truy vấn CRUD/bulk/index/stats/export/import phải scope theo tổ chức hiện tại được middleware `set.permissions.team` thiết lập từ header `X-Organization-Id`.
- Model dùng trait `HasOrganizationScope` để tự động scope query và gán `organization_id` khi create — không cần `where('organization_id', ...)` thủ công trong service.
- Không cho phép truy cập chéo tổ chức khi thao tác theo ID; khi không cùng tổ chức phải trả lỗi tương đương không tìm thấy/không có quyền.
- Middleware dùng chung: `Core/Middleware/EnsureRouteModelsBelongToOrganization.php` để kiểm tra đồng loạt model route (`{meeting}`, `{taskAssignmentItem}`, ...) thuộc đúng `organization_id` hiện tại.
