# Thiết kế cấu trúc dự án

Tài liệu mô tả cấu trúc thư mục hiện tại của hệ thống theo hướng modular.

Cập nhật: 2026-06-05.

## 1) Tổng quan thư mục gốc

```text
qlcv/
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
├── deploy.sh
└── phpunit.xml
```

## 2) Cấu trúc `app/` ngoài Modules

```text
app/
├── Console/
│   └── Commands/          # Artisan commands (cleanup seeds, sync migrations, simulate notifications)
├── Http/
│   ├── Controllers/       # Controller chung (DeployController)
│   └── Middleware/
├── Modules/               # Xem mục 3
├── Providers/             # AppServiceProvider, EventServiceProvider, NotificationServiceProvider
└── Services/
    └── Notification/      # Engine notification xuyên module (dispatcher, job, channel senders, content builders)
```

## 3) Cấu trúc module trong `app/Modules`

```text
app/Modules/
├── Auth/
│   ├── AuthController.php
│   ├── SsoController.php
│   ├── Jobs/
│   ├── Requests/
│   ├── Routes/
│   └── Services/
├── Core/
│   ├── *Controller.php      # Controllers dùng chung (NotificationConfig, NotificationLog, User, Setting, ...)
│   ├── Enums/
│   ├── Exports/
│   ├── Imports/
│   ├── Middleware/           # SetNotificationModule, EnsureRouteModelsBelongToOrganization, LogActivity
│   ├── Models/               # User, Organization, UserProfile, Setting, Notification*, LogActivity, ...
│   ├── Requests/
│   ├── Resources/            # PublicOptionResource, UserResource, NotificationLogResource
│   ├── Routes/
│   ├── Services/             # UserService, OrganizationService, LookupService, MediaService, LogActivityService
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
├── Meeting/                  # Module phức tạp - có thêm folder tùy chọn
│   ├── Controllers/
│   ├── Concerns/             # Trait dùng chung nội bộ module
│   ├── Enums/
│   ├── Events/               # Domain events (WS: discussion-registration.*, vote-topic.*, ...)
│   ├── Exports/
│   ├── Imports/
│   ├── Middleware/
│   ├── Models/
│   ├── Observers/
│   ├── Policies/             # Laravel authorization policies (MeetingPolicy, MeetingDiscussionRegistrationPolicy, ...)
│   ├── Requests/
│   ├── Resources/
│   ├── Routes/
│   └── Services/
└── Scheduling/
    ├── Controllers/          # Schedule, SchedulingEmployee, SchedulingEmployeeGroup, SchedulingSetting
    ├── Enums/                # ScheduleStatus, ModuleType, SessionType, Nature, NotificationChannel, NotificationStatus, ReminderSource
    ├── Exports/
    ├── Jobs/                 # SendScheduleNotificationJob
    ├── Models/               # Schedule, ScheduleReminder, ScheduleAttachment, ScheduleNotification, ScheduleNotificationRecipient, SchedulingEmployee, SchedulingEmployeeGroup, OrgSchedulingSettings, SchedulingSetting
    ├── Observers/            # ScheduleObserver
    ├── Requests/
    ├── Resources/            # ScheduleResource, ScheduleCollection, ScheduleReminderResource
    ├── Routes/
    └── Services/             # ScheduleService
```

> Các folder tùy chọn (`Concerns/`, `Events/`, `Middleware/`, `Policies/`) chỉ tạo khi thực sự cần — không bắt buộc cho mọi module.

## 4) Quy ước luồng xử lý

- `Controller`: nhận request, gọi `FormRequest` validate, điều phối `Service`, trả response chuẩn.
- `Service`: xử lý nghiệp vụ và transaction.
- `Model`: định nghĩa quan hệ + scope filter/sort.
- `Resource`: chuẩn hóa output API.
- `Routes`: tách riêng theo module và resource.

## 5) Vị trí tài liệu liên quan

- Tài liệu API (generate): `docs/api/`
- Phân tích nghiệp vụ/đề xuất: `docs/answer/`
- Thiết kế cơ sở dữ liệu: [docs/DATABASE_DESIGN.md](DATABASE_DESIGN.md) (index) + các file per-module
- Changelog cho FE khi BE đổi API: `docs/changelogs/`
- Hướng dẫn flow notification: `docs/guides/`
- Specs + plans cho feature lớn: `docs/superpowers/specs/` và `docs/superpowers/plans/`
- Onboarding dev mới: `docs/ONBOARDING.md`

## 6) Quy ước multi-tenant theo tổ chức

- Các module nghiệp vụ có dữ liệu theo tổ chức (Core, Meeting, TaskAssignment, Scheduling) có cột `organization_id` trên bảng chính.
- Mọi truy vấn CRUD/bulk/index/stats/export/import phải scope theo tổ chức hiện tại được middleware `set.permissions.team` thiết lập từ header `X-Organization-Id`.
- Model kế thừa `TenantModel` để tự động scope query và gán `organization_id` khi create.
- Không cho phép truy cập chéo tổ chức khi thao tác theo ID; khi không cùng tổ chức phải trả lỗi tương đương không tìm thấy/không có quyền.

## 7) Hệ thống Notification (dùng chung)

```
app/Services/Notification/
├── Channels/              # FcmChannel, MailChannel, ZaloChannel, ZaloZnsChannel, SmsChannel
├── Console/               # ProcessRemindersCommand (cron)
├── ContentBuilders/       # Per-event: SchedulePublishedContentBuilder, MeetingPublishedContentBuilder, ...
├── DTOs/                  # NotificationPayload, Recipient, SendResult
├── Enums/                 # NotificationEventEnum, NotificationModuleEnum, NotificationDeliveryStatusEnum, ...
├── Events/                # SchedulePublished, ScheduleUpdated, ScheduleCancelled, MeetingPublished, ...
├── Jobs/                  # SendDeliveryJob, SendScheduleReminderJob
├── Listeners/             # SendSchedulePublishedNotifications, SendMeetingPublishedNotifications, ...
├── NotificationService.php
└── Services/              # NotificationDispatcher, ContentBuilderRegistry, ReminderScheduler, ScheduleReminderScheduler, MeetingReminderScheduler
```

Mỗi module đăng ký events + listeners trong `NotificationServiceProvider`.
