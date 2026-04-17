# Tích hợp Notification cho module mới — Developer Guide

Guide dành cho dev muốn thêm notification vào module mới (vd News, Inventory, Ticket). Notification system đã có sẵn — bạn chỉ cần wire module vào đúng extension point.

---

## Tiền đề — system đã có sẵn

- `NotificationService` singleton với 4 channel (`sms`, `mail`, `zalo`, `fcm`) đã implement
- `NotificationDispatcher` — tạo notification record + deliveries + dispatch queued jobs
- `SendDeliveryJob` — thực thi gửi qua channel tương ứng
- Bảng `notifications`, `notification_deliveries`, `notification_event_configs`, `notification_schedules`
- Scheduler cronjob `notifications:process-reminders` chạy mỗi phút (nếu bạn dùng reminders)
- Blade email layout shared: `resources/views/emails/notification-layout.blade.php`

---

## 7 bước tích hợp

### Bước 1. Đăng ký module vào `NotificationModuleEnum`

File: [app/Services/Notification/Enums/NotificationModuleEnum.php](../../app/Services/Notification/Enums/NotificationModuleEnum.php)

Thêm case mới:
```php
enum NotificationModuleEnum: string
{
    case TaskAssignment = 'task_assignment';
    case News = 'news';                // ← thêm

    public function label(): string
    {
        return match ($this) {
            self::TaskAssignment => 'Giao việc',
            self::News => 'Tin tức',    // ← thêm
        };
    }
    // ...
}
```

### Bước 2. Định nghĩa events của module trong `NotificationEventEnum`

File: [app/Services/Notification/Enums/NotificationEventEnum.php](../../app/Services/Notification/Enums/NotificationEventEnum.php)

Thêm event cases + map vào module:
```php
enum NotificationEventEnum: string
{
    // ... existing cases ...
    case PostPublished = 'post_published';   // ← thêm
    case PostCommented = 'post_commented';   // ← thêm

    public function module(): NotificationModuleEnum
    {
        return match ($this) {
            self::DocumentIssued, self::TaskCompleted, ... => NotificationModuleEnum::TaskAssignment,
            self::PostPublished, self::PostCommented => NotificationModuleEnum::News,  // ← thêm
        };
    }

    public function label(): string
    {
        return match ($this) {
            // ... existing ...
            self::PostPublished => 'Bài viết mới xuất bản',     // ← thêm
            self::PostCommented => 'Có bình luận mới',
        };
    }
}
```

Lưu ý: event_key phải **unique toàn hệ thống** (dù mỗi module có config riêng).

### Bước 3. Tạo Event classes (Laravel events)

Đặt tại `app/Services/Notification/Events/` (hoặc trong module nếu muốn):
```php
// app/Services/Notification/Events/PostPublished.php
namespace App\Services\Notification\Events;

use App\Modules\News\Models\Post;

class PostPublished
{
    public function __construct(public Post $post) {}
}
```

1 event = 1 class data-holder đơn giản.

### Bước 4. Tạo ContentBuilder cho từng event

Implement `ContentBuilder` interface. Builder chịu trách nhiệm build content cho mỗi channel (4 channel: sms/mail/zalo/fcm).

Đặt tại `app/Services/Notification/ContentBuilders/`:
```php
class PostPublishedContentBuilder implements ContentBuilder
{
    public function build(string $channelKey, User $recipient, Model $notifiable, mixed ...$extraArgs): ?NotificationPayload
    {
        if (! $notifiable instanceof Post) return null;

        return match ($channelKey) {
            'sms' => $this->toSms($recipient, $notifiable),
            'mail' => $this->toMail($recipient, $notifiable),
            'zalo' => $this->toZalo($recipient, $notifiable),
            'fcm' => $this->toFcm($recipient, $notifiable),
            default => null,
        };
    }

    public function title(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return 'Bài viết mới';
    }

    public function shortBody(User $recipient, Model $notifiable, mixed ...$extraArgs): string
    {
        return "Bài viết \"{$notifiable->title}\" đã được xuất bản.";
    }

    public function inAppContext(User $recipient, Model $notifiable, mixed ...$extraArgs): array
    {
        return ['url' => "/posts/{$notifiable->id}"];
    }

    private function toSms(User $r, Post $p): ?NotificationPayload
    {
        if (! $r->phone) return null;
        $text = "Bai viet moi: {$p->title}. Tran trong !";
        return new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $r->phone, name: $r->name),
            content: Str::ascii($text),
        );
    }

    private function toMail(User $r, Post $p): ?NotificationPayload
    {
        if (! $r->email) return null;
        $html = view('notifications.post_published.email', compact('r', 'p'))->render();
        return new NotificationPayload(
            channels: ['mail'],
            recipient: new Recipient(email: $r->email, name: $r->name),
            content: $html,
            subject: "Bài viết mới: {$p->title}",
        );
    }

    // toZalo(), toFcm() tương tự — xem ContentBuilder có sẵn làm mẫu
}
```

**Xem tham khảo:** [DocumentIssuedContentBuilder](../../app/Services/Notification/ContentBuilders/DocumentIssuedContentBuilder.php), [ReminderContentBuilder](../../app/Services/Notification/ContentBuilders/ReminderContentBuilder.php).

**Rule:** Nếu recipient thiếu field (vd không có phone cho sms) → return `null`. Hệ thống tự mark delivery `skipped`.

### Bước 5. Tạo Blade template cho channel `mail`

File: `resources/views/notifications/{event_key}/email.blade.php`

```blade
@extends('emails.notification-layout', ['subjectText' => 'Bài viết mới'])

@section('body')
    <p>Xin chào <strong>{{ $r->name }}</strong>,</p>
    <p>Bài viết <strong>{{ $p->title }}</strong> đã được xuất bản.</p>
    <p>{{ $p->excerpt }}</p>
@endsection
```

### Bước 6. Tạo Listener + đăng ký

Listener phải implement `ShouldQueue` (async). Inject `NotificationDispatcher` + `ContentBuilderRegistry`.

```php
// app/Services/Notification/Listeners/SendPostPublishedNotifications.php
class SendPostPublishedNotifications implements ShouldQueue
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private ContentBuilderRegistry $registry,
    ) {}

    public function handle(PostPublished $event): void
    {
        $config = NotificationEventConfig::forModule('news')
            ->where('event_key', 'post_published')
            ->first();
        if (! $config || ! $config->enabled || empty($config->channels)) return;

        $builder = $this->registry->for('post_published');
        $recipients = User::whereHas('subscriptions', ...)->get();  // logic tự define

        foreach ($recipients as $user) {
            $this->dispatcher->dispatch(
                eventKey: 'post_published',
                recipient: $user,
                notifiable: $event->post,
                channels: $config->channels,
                builder: $builder,
            );
        }
    }
}
```

**Đăng ký** trong [app/Providers/NotificationServiceProvider.php](../../app/Providers/NotificationServiceProvider.php) method `boot()`:
```php
public function boot(): void
{
    $registry = $this->app->make(ContentBuilderRegistry::class);
    // ... existing ...
    $registry->register('post_published', $this->app->make(PostPublishedContentBuilder::class));

    // ... existing ...
    Event::listen(PostPublished::class, SendPostPublishedNotifications::class);
}
```

### Bước 7. Dispatch event từ service layer của module

Trong service khi nghiệp vụ xảy ra:
```php
// app/Modules/News/Services/PostService.php
public function publish(Post $post): Post
{
    $post->update(['status' => 'published', 'published_at' => now()]);
    event(new PostPublished($post));  // ← dispatch
    return $post;
}
```

### Bước 8. Tạo route config API cho module

Mỗi module có route file riêng để admin cấu hình events/schedules của module mình. FE không cần biết `module_key`.

File: `app/Modules/News/Routes/notification_config.php`:
```php
<?php
use App\Modules\Core\NotificationConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware('notification.module:news')->group(function () {
    Route::get('/event-configs', [NotificationConfigController::class, 'eventConfigIndex'])
        ->middleware('permission:notifications.event-configs.index,web');
    Route::put('/event-configs/{eventKey}', [NotificationConfigController::class, 'eventConfigUpdate'])
        ->middleware('permission:notifications.event-configs.update,web');
    Route::get('/schedules', [NotificationConfigController::class, 'scheduleIndex'])
        ->middleware('permission:notifications.schedules.index,web');
    Route::post('/schedules', [NotificationConfigController::class, 'scheduleStore'])
        ->middleware('permission:notifications.schedules.store,web');
});
```

Đăng ký prefix trong [routes/api.php](../../routes/api.php) (trong khối middleware auth):
```php
Route::prefix('news/notification-config')->group(function () {
    require base_path('app/Modules/News/Routes/notification_config.php');
});
```

FE gọi `/api/news/notification-config/event-configs` — middleware tự gán `module_key = 'news'`.

### Bước 9. Seed default event configs

Chạy `NotificationEventConfigSeeder` — seeder auto tạo 1 row cho mọi event_key (bao gồm event mới của module News) dựa trên enum. Không cần thay đổi seeder.

```bash
php artisan db:seed --class=NotificationEventConfigSeeder
```

Default `enabled: false`, admin sẽ tự bật + chọn channel qua API.

---

## (Optional) Thêm scheduled reminders

Chỉ cần nếu module có entity có deadline (vd Post với `deadline_at`). Reminder system của TaskAssignment đã là mẫu — copy pattern:

1. Định nghĩa 3 event mới: `reminder_news_before`, `reminder_news_on`, `reminder_news_after` (nếu muốn tách riêng với reminder TaskAssignment). Hoặc tái dùng `reminder_before/on/after` nếu business logic giống hệt.

   **Recommend:** nếu business logic giống, **tái dùng 3 event reminder hiện tại** và xử lý đa dạng notifiable trong `ReminderContentBuilder` (polymorphic via `notifiable` param).

2. Tạo observer cho model của module (tham khảo [TaskAssignmentItemObserver](../../app/Modules/TaskAssignment/Observers/TaskAssignmentItemObserver.php)):
   - `saved` → create reminder records
   - `status=completed` → cancel pending
   - `deleted` → cancel all

3. Register observer trong provider.

4. Seed schedules cho module qua `NotificationScheduleSeeder` với `module_key = 'news'`.

5. `ProcessRemindersCommand` đã generic — tự nó load item qua notifiable polymorphic. **Chỉ cần** đảm bảo model của module có relation `users` (danh sách recipient) hoặc adapter tương đương.

> **Lưu ý:** `ProcessRemindersCommand` hiện hardcode assume notifiable là `TaskAssignmentItem` với `->users` relation. Nếu module khác có pattern recipient khác, cần refactor command thành generic hơn (vd inject a `RecipientResolver` strategy per module). Để sau — giai đoạn này TaskAssignment là use case duy nhất.

---

## Permissions

Tất cả module dùng chung bộ permission (không tạo permission mới per module):
- `notifications.event-configs.index/update`
- `notifications.schedules.index/store/update/destroy`

Admin cần có permission này để config bất kỳ module nào. Nếu muốn phân quyền chi tiết hơn per-module (vd team A chỉ config module X), cần extend permission system sau.

---

## Checklist

- [ ] Thêm case vào `NotificationModuleEnum` + `label()`
- [ ] Thêm events vào `NotificationEventEnum` + `module()` + `label()`
- [ ] Tạo Event classes (`app/Services/Notification/Events/`)
- [ ] Tạo ContentBuilder per event
- [ ] Tạo blade template email per event
- [ ] Tạo Listener queued per event
- [ ] Register ContentBuilder + Event::listen trong `NotificationServiceProvider::boot()`
- [ ] Dispatch event từ service layer module
- [ ] Tạo route file `Routes/notification_config.php` trong module
- [ ] Đăng ký route prefix trong `routes/api.php`
- [ ] Run `NotificationEventConfigSeeder` để tạo default configs
- [ ] (Optional) Setup reminder observer nếu module có deadline

## Test manual

1. Admin vào API `POST /api/notifications/modules/{new-module}/event-configs/{event}` set `enabled=true` + `channels=['mail']`
2. Ensure channel đã enabled trong Settings (vd `email_enabled=1`)
3. Trigger nghiệp vụ (vd publish post) → event fire → listener queue
4. `php artisan queue:work --queue=notifications,default`
5. Check `storage/logs/notification-*.log` + mailbox recipient

---

## Tài liệu tham khảo

- Spec Phase A-C: [docs/superpowers/specs/2026-04-16-notification-events-reminders-design.md](../superpowers/specs/2026-04-16-notification-events-reminders-design.md)
- API docs FE: [docs/api/notification-config.md](../api/notification-config.md), [docs/api/notification-me.md](../api/notification-me.md)
- Test notification endpoint: [docs/api/notification.md](../api/notification.md)
- Mẫu tham khảo: module TaskAssignment (tất cả file `app/Services/Notification/**` + `app/Modules/TaskAssignment/Observers/**`)
