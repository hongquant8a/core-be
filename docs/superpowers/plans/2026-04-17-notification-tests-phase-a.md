# Notification Tests — Phase A (Core Flow + APIs) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Thêm feature + integration tests cho core notification flow (dispatcher, job, scheduler, observer, cron) + admin/user API endpoints. Đảm bảo regression safety cho hệ thống notification hiện tại (~40 tests mới).

**Architecture:** Dùng Laravel testing facilities (`RefreshDatabase`, `Queue::fake()`, `Event::fake()`, `Http::fake()`, `Mail::fake()`, `Carbon::setTestNow`) để cô lập và mock. Tạo factories + trait helper cho setup. Feature tests dùng `Tests\TestCase` (boot app), unit logic tests dùng `PHPUnit\Framework\TestCase`.

**Tech Stack:** Laravel 12, PHPUnit 11, Mockery, Sanctum for auth, Spatie Permission, MySQL (testing DB).

**Dependencies:** Notification system đã implement xong (Phases A-C từ `docs/superpowers/specs/2026-04-16-notification-events-reminders-design.md`). Hiện có 85 tests pass.

---

## File Structure

### Created
- `database/factories/Modules/Core/Models/NotificationFactory.php`
- `database/factories/Modules/Core/Models/NotificationDeliveryFactory.php`
- `database/factories/Modules/Core/Models/NotificationEventConfigFactory.php`
- `database/factories/Modules/Core/Models/NotificationScheduleFactory.php`
- `tests/Feature/Notification/NotificationDispatcherTest.php`
- `tests/Feature/Notification/SendDeliveryJobTest.php`
- `tests/Feature/Notification/ReminderSchedulerTest.php`
- `tests/Feature/Notification/TaskAssignmentItemObserverTest.php`
- `tests/Feature/Notification/ProcessRemindersCommandTest.php`
- `tests/Feature/Notification/NotificationConfigControllerTest.php` (replaces existing if outdated)
- `tests/Feature/Notification/NotificationLogControllerTest.php`
- `tests/Feature/Notification/MyNotificationControllerTest.php`
- `tests/Feature/Notification/SyncFcmTokenMiddlewareTest.php`
- `tests/Feature/Notification/DocumentIssuedFlowTest.php`
- `tests/Concerns/InteractsWithNotifications.php` (trait)

### Modified
- `app/Modules/Core/Models/Notification.php` — add `HasFactory` trait + `newFactory()`
- `app/Modules/Core/Models/NotificationDelivery.php` — add `HasFactory`
- `app/Modules/Core/Models/NotificationEventConfig.php` — add `HasFactory`
- `app/Modules/Core/Models/NotificationSchedule.php` — add `HasFactory`

---

## Task 1: Factories

**Files:**
- Create: `database/factories/Modules/Core/Models/NotificationFactory.php`
- Create: `database/factories/Modules/Core/Models/NotificationDeliveryFactory.php`
- Create: `database/factories/Modules/Core/Models/NotificationEventConfigFactory.php`
- Create: `database/factories/Modules/Core/Models/NotificationScheduleFactory.php`
- Modify: 4 corresponding Model files — add `HasFactory` trait + `newFactory()`.

- [ ] **Step 1: Create `NotificationFactory.php`**

```php
<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_key' => 'document_issued',
            'notifiable_type' => 'App\\Test\\Dummy',
            'notifiable_id' => 1,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'context' => [],
            'read_at' => null,
        ];
    }

    public function unread(): static
    {
        return $this->state(['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }
}
```

- [ ] **Step 2: Create `NotificationDeliveryFactory.php`**

```php
<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    public function definition(): array
    {
        return [
            'notification_id' => Notification::factory(),
            'channel' => 'sms',
            'status' => 'pending',
            'message_id' => null,
            'error_message' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 'sent',
            'message_id' => (string) fake()->randomNumber(),
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Unknown error'): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }
}
```

- [ ] **Step 3: Create `NotificationEventConfigFactory.php`**

```php
<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\NotificationEventConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationEventConfigFactory extends Factory
{
    protected $model = NotificationEventConfig::class;

    public function definition(): array
    {
        return [
            'module_key' => 'task_assignment',
            'event_key' => 'document_issued',
            'enabled' => false,
        ];
    }

    public function enabled(): static
    {
        return $this->state(['enabled' => true]);
    }
}
```

- [ ] **Step 4: Create `NotificationScheduleFactory.php`**

```php
<?php

namespace Database\Factories\Modules\Core\Models;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationScheduleFactory extends Factory
{
    protected $model = NotificationSchedule::class;

    public function definition(): array
    {
        return [
            'notification_event_config_id' => NotificationEventConfig::factory(),
            'moment' => null,
            'offset_minutes' => null,
            'channels' => ['sms', 'mail'],
            'label' => fake()->sentence(2),
            'sort_order' => 0,
        ];
    }

    public function instant(): static
    {
        return $this->state(['moment' => null, 'offset_minutes' => null]);
    }

    public function before(int $minutes = 60): static
    {
        return $this->state(['moment' => 'before', 'offset_minutes' => $minutes]);
    }

    public function on(): static
    {
        return $this->state(['moment' => 'on', 'offset_minutes' => null]);
    }

    public function after(int $minutes = 60): static
    {
        return $this->state(['moment' => 'after', 'offset_minutes' => $minutes]);
    }
}
```

- [ ] **Step 5: Update 4 models to register factories**

For `app/Modules/Core/Models/Notification.php` — add at top of class:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
// ...
class Notification extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Core\Models\NotificationFactory::new();
    }
    // ... rest unchanged
}
```

Repeat for `NotificationDelivery`, `NotificationEventConfig`, `NotificationSchedule` (each with its own factory class name).

- [ ] **Step 6: Smoke test factories via tinker**

Run: `cd d:/danatec/qlcv && php artisan tinker --execute='echo \App\Modules\Core\Models\Notification::factory()->count(3)->make()->count();'`
Expected: `3`

- [ ] **Step 7: Commit**

```bash
cd d:/danatec/qlcv
git add database/factories/Modules/Core/Models/Notification* app/Modules/Core/Models/Notification*.php
git commit -m "test(notification): add factories for Notification models"
```

---

## Task 2: InteractsWithNotifications trait (helper)

**Files:**
- Create: `tests/Concerns/InteractsWithNotifications.php`

- [ ] **Step 1: Create trait**

```php
<?php

namespace Tests\Concerns;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationModuleEnum;

trait InteractsWithNotifications
{
    /**
     * Seed 6 event configs (disabled) + 4 default reminder schedules.
     */
    protected function seedNotificationConfig(): void
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;
        foreach (NotificationEventEnum::cases() as $event) {
            NotificationEventConfig::firstOrCreate(
                ['module_key' => $moduleKey, 'event_key' => $event->value],
                ['enabled' => false]
            );
        }
    }

    /**
     * Enable 1 event với channels cho test.
     */
    protected function enableEvent(string $eventKey, array $channels): NotificationEventConfig
    {
        $moduleKey = NotificationModuleEnum::TaskAssignment->value;
        $config = NotificationEventConfig::firstOrCreate(
            ['module_key' => $moduleKey, 'event_key' => $eventKey],
            ['enabled' => true]
        );
        $config->update(['enabled' => true]);

        // Tạo schedule instant cho non-reminder (nếu chưa có)
        if (! str_starts_with($eventKey, 'reminder_')) {
            NotificationSchedule::firstOrCreate(
                ['notification_event_config_id' => $config->id, 'moment' => null, 'offset_minutes' => null],
                ['channels' => $channels, 'label' => 'Instant', 'sort_order' => 0]
            );

            return $config;
        }

        return $config;
    }

    /**
     * Tạo reminder schedule cho reminder event.
     */
    protected function addReminderSchedule(string $eventKey, string $moment, ?int $offsetMinutes, array $channels): NotificationSchedule
    {
        $config = NotificationEventConfig::where('module_key', NotificationModuleEnum::TaskAssignment->value)
            ->where('event_key', $eventKey)
            ->firstOrFail();

        return NotificationSchedule::create([
            'notification_event_config_id' => $config->id,
            'moment' => $moment,
            'offset_minutes' => $offsetMinutes,
            'channels' => $channels,
            'label' => "Test {$moment} {$offsetMinutes}",
            'sort_order' => 0,
        ]);
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `cd d:/danatec/qlcv && php -l tests/Concerns/InteractsWithNotifications.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Concerns/InteractsWithNotifications.php
git commit -m "test(notification): add InteractsWithNotifications trait"
```

---

## Task 3: NotificationDispatcher feature test (with DB)

**Files:**
- Create: `tests/Feature/Notification/NotificationDispatcherTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function fakeBuilder(string $title = 'T', string $body = 'B', array $ctx = []): ContentBuilder
    {
        $m = Mockery::mock(ContentBuilder::class);
        $m->shouldReceive('title')->andReturn($title);
        $m->shouldReceive('shortBody')->andReturn($body);
        $m->shouldReceive('inAppContext')->andReturn($ctx);

        return $m;
    }

    public function test_creates_notification_row_with_builder_content(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        $n = app(NotificationDispatcher::class)->dispatch(
            eventKey: 'document_issued',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms'],
            builder: $this->fakeBuilder('Hello', 'World', ['url' => '/x']),
        );

        $this->assertSame($user->id, $n->user_id);
        $this->assertSame('document_issued', $n->event_key);
        $this->assertSame('Hello', $n->title);
        $this->assertSame('World', $n->body);
        $this->assertSame(['url' => '/x'], $n->context);
        $this->assertSame(User::class, $n->notifiable_type);
        $this->assertSame($notifiable->id, $n->notifiable_id);
    }

    public function test_creates_one_delivery_per_channel(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        app(NotificationDispatcher::class)->dispatch(
            eventKey: 'document_issued',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms', 'mail', 'fcm'],
            builder: $this->fakeBuilder(),
        );

        $this->assertSame(3, NotificationDelivery::count());
        $channels = NotificationDelivery::pluck('channel')->sort()->values()->all();
        $this->assertSame(['fcm', 'mail', 'sms'], $channels);
        $this->assertSame(3, NotificationDelivery::where('status', 'pending')->count());
    }

    public function test_dispatches_one_job_per_delivery_on_notifications_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        app(NotificationDispatcher::class)->dispatch(
            eventKey: 'test',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms', 'mail'],
            builder: $this->fakeBuilder(),
        );

        Queue::assertPushed(SendDeliveryJob::class, 2);
        Queue::assertPushedOn('notifications', SendDeliveryJob::class);
    }

    public function test_empty_channels_creates_notification_without_deliveries(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        $n = app(NotificationDispatcher::class)->dispatch(
            eventKey: 'test',
            recipient: $user,
            notifiable: $notifiable,
            channels: [],
            builder: $this->fakeBuilder(),
        );

        $this->assertSame(1, Notification::count());
        $this->assertSame(0, NotificationDelivery::count());
        Queue::assertNothingPushed();
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationDispatcherTest`
Expected: PASS (4 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/NotificationDispatcherTest.php
git commit -m "test(notification): add NotificationDispatcher feature tests"
```

---

## Task 4: SendDeliveryJob feature test

**Files:**
- Create: `tests/Feature/Notification/SendDeliveryJobTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\Jobs\SendDeliveryJob;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendDeliveryJobTest extends TestCase
{
    use RefreshDatabase;
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function makeDelivery(string $channel = 'sms', string $status = 'pending'): NotificationDelivery
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'event_key' => 'test_event',
        ]);

        return NotificationDelivery::factory()->create([
            'notification_id' => $notification->id,
            'channel' => $channel,
            'status' => $status,
        ]);
    }

    public function test_noop_when_delivery_already_sent(): void
    {
        $delivery = $this->makeDelivery(status: 'sent');

        $registry = new ContentBuilderRegistry();
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldNotReceive('send');

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_marks_skipped_when_builder_returns_null(): void
    {
        $delivery = $this->makeDelivery();
        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(null);

        $registry = new ContentBuilderRegistry();
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldNotReceive('send');

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $d = $delivery->fresh();
        $this->assertSame('skipped', $d->status);
        $this->assertSame('Recipient missing field for channel', $d->error_message);
    }

    public function test_marks_sent_on_success(): void
    {
        $delivery = $this->makeDelivery();
        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(
            new NotificationPayload(['sms'], new Recipient(phone: '0905112233'), 'hi')
        );
        $registry = new ContentBuilderRegistry();
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()
            ->andReturn([new SendResult('sms', true, 'msg-42')]);

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $d = $delivery->fresh();
        $this->assertSame('sent', $d->status);
        $this->assertSame('msg-42', $d->message_id);
        $this->assertNotNull($d->sent_at);
        $this->assertNull($d->error_message);
    }

    public function test_marks_failed_on_provider_error(): void
    {
        $delivery = $this->makeDelivery();
        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(
            new NotificationPayload(['sms'], new Recipient(phone: '0905112233'), 'hi')
        );
        $registry = new ContentBuilderRegistry();
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')
            ->andReturn([new SendResult('sms', false, error: 'provider down')]);

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $d = $delivery->fresh();
        $this->assertSame('failed', $d->status);
        $this->assertSame('provider down', $d->error_message);
        $this->assertNull($d->sent_at);
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=SendDeliveryJobTest`
Expected: PASS (4 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/SendDeliveryJobTest.php
git commit -m "test(notification): add SendDeliveryJob feature tests"
```

---

## Task 5: ReminderScheduler test

**Files:**
- Create: `tests/Feature/Notification/ReminderSchedulerTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Services\ReminderScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class ReminderSchedulerTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    /**
     * Tạo 1 TaskAssignmentItem tối thiểu bypass factory (vì factory có nhiều dependency).
     */
    private function makeItem(string $status = 'todo', ?string $endAt = null): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'D', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('task_assignment_items')->insertGetId([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'department_id' => $deptId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => $status,
            'completion_percent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TaskAssignmentItem::findOrFail($id);
    }

    public function test_no_reminders_when_item_has_no_deadline(): void
    {
        $item = $this->makeItem(endAt: null);

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(0, TaskAssignmentReminder::count());
    }

    public function test_no_reminders_when_item_done(): void
    {
        $item = $this->makeItem(status: TaskProgressStatusEnum::Done->value, endAt: now()->addDays(2)->toDateTimeString());

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(0, TaskAssignmentReminder::count());
    }

    public function test_creates_reminder_rows_per_schedule(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 1440, ['mail']);
        $this->addReminderSchedule('reminder_on', 'on', null, ['sms']);
        $this->addReminderSchedule('reminder_after', 'after', 1440, ['mail']);

        $deadline = now()->addDays(3);
        $item = $this->makeItem(endAt: $deadline->toDateTimeString());

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(3, TaskAssignmentReminder::count());

        $before = TaskAssignmentReminder::where('moment', 'before')->first();
        $this->assertEqualsWithDelta(
            $deadline->copy()->subMinutes(1440)->timestamp,
            $before->remind_at->timestamp,
            5
        );

        $on = TaskAssignmentReminder::where('moment', 'on')->first();
        $this->assertEqualsWithDelta($deadline->timestamp, $on->remind_at->timestamp, 5);

        $after = TaskAssignmentReminder::where('moment', 'after')->first();
        $this->assertEqualsWithDelta(
            $deadline->copy()->addMinutes(1440)->timestamp,
            $after->remind_at->timestamp,
            5
        );
    }

    public function test_reschedule_deletes_pending_and_creates_new(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 1440, ['mail']);
        $item = $this->makeItem(endAt: now()->addDays(3)->toDateTimeString());
        $scheduler = app(ReminderScheduler::class);

        $scheduler->scheduleFor($item);
        $firstIds = TaskAssignmentReminder::pluck('id')->all();
        $this->assertCount(1, $firstIds);

        // Reschedule → old deleted, new created
        $scheduler->scheduleFor($item);
        $this->assertSame(1, TaskAssignmentReminder::count());
        $secondIds = TaskAssignmentReminder::pluck('id')->all();
        $this->assertNotEquals($firstIds, $secondIds);
    }

    public function test_cancel_pending_marks_status_cancelled(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 1440, ['mail']);
        $item = $this->makeItem(endAt: now()->addDays(3)->toDateTimeString());
        $scheduler = app(ReminderScheduler::class);
        $scheduler->scheduleFor($item);

        $scheduler->cancelPending($item);

        $this->assertSame(1, TaskAssignmentReminder::where('status', 'cancelled')->count());
        $this->assertSame(0, TaskAssignmentReminder::where('status', 'pending')->count());
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=ReminderSchedulerTest`
Expected: PASS (5 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/ReminderSchedulerTest.php
git commit -m "test(notification): add ReminderScheduler feature tests"
```

---

## Task 6: TaskAssignmentItemObserver test

**Files:**
- Create: `tests/Feature/Notification/TaskAssignmentItemObserverTest.php`

Observer được register trong `NotificationServiceProvider::boot()`, nên sẽ trigger tự động khi model change. Test kiểm tra hành vi end-to-end khi save/delete item.

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class TaskAssignmentItemObserverTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
        $this->addReminderSchedule('reminder_before', 'before', 1440, ['mail']);
    }

    private function makeItem(string $status = 'todo', ?string $endAt = null): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'D', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'department_id' => $deptId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => $status,
            'completion_percent' => 0,
        ]);
    }

    public function test_creates_reminder_on_item_creation_with_deadline(): void
    {
        $this->makeItem(endAt: now()->addDays(3)->toDateTimeString());

        $this->assertSame(1, TaskAssignmentReminder::count());
        $this->assertSame('pending', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_pending_when_status_becomes_done(): void
    {
        $item = $this->makeItem(endAt: now()->addDays(3)->toDateTimeString());
        $this->assertSame(1, TaskAssignmentReminder::where('status', 'pending')->count());

        $item->update(['processing_status' => TaskProgressStatusEnum::Done->value]);

        $this->assertSame(0, TaskAssignmentReminder::where('status', 'pending')->count());
        $this->assertSame(1, TaskAssignmentReminder::where('status', 'cancelled')->count());
    }

    public function test_reschedules_when_end_at_changes(): void
    {
        $item = $this->makeItem(endAt: now()->addDays(3)->toDateTimeString());
        $initialId = TaskAssignmentReminder::first()->id;

        $item->update(['end_at' => now()->addDays(5)->toDateTimeString()]);

        $this->assertSame(1, TaskAssignmentReminder::count());
        $this->assertNotSame($initialId, TaskAssignmentReminder::first()->id);
    }

    public function test_cancels_pending_on_delete(): void
    {
        $item = $this->makeItem(endAt: now()->addDays(3)->toDateTimeString());
        $this->assertSame(1, TaskAssignmentReminder::where('status', 'pending')->count());

        $item->delete();

        // CASCADE FK xóa reminder khi item bị xóa (task_assignment_item_id cascadeOnDelete)
        // → kết quả: 0 row còn lại
        $this->assertSame(0, TaskAssignmentReminder::count());
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=TaskAssignmentItemObserverTest`
Expected: PASS (4 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/TaskAssignmentItemObserverTest.php
git commit -m "test(notification): add TaskAssignmentItemObserver feature tests"
```

---

## Task 7: ProcessRemindersCommand test

**Files:**
- Create: `tests/Feature/Notification/ProcessRemindersCommandTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class ProcessRemindersCommandTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeItemWithAssignee(?string $endAt = null, string $status = 'todo'): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'D', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'department_id' => $deptId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => $status,
            'completion_percent' => 0,
        ]);
        $user = User::factory()->create(['email' => 'r@test.com']);
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $user->id,
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $item->fresh(['users']);
    }

    public function test_ignores_reminders_not_due(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addDays(3)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('pending', TaskAssignmentReminder::first()->status);
    }

    public function test_fires_due_reminder_creates_notifications(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []); // parent enabled
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString()); // due 30 min ago

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::count());
        $this->assertSame(1, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 1);

        $reminder = TaskAssignmentReminder::first();
        $this->assertSame('fired', $reminder->status);
        $this->assertNotNull($reminder->fired_at);
    }

    public function test_cancels_reminder_when_item_done(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());
        $item->update(['processing_status' => TaskProgressStatusEnum::Done->value]);

        // Observer sẽ cancel reminder khi done. Nhưng giả sử reminder còn pending (edge case)
        TaskAssignmentReminder::query()->update(['status' => 'pending']);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_reminder_when_parent_event_disabled(): void
    {
        Queue::fake();
        // Parent event_config enabled=false (mặc định do seedNotificationConfig)
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=ProcessRemindersCommandTest`
Expected: PASS (4 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/ProcessRemindersCommandTest.php
git commit -m "test(notification): add ProcessRemindersCommand feature tests"
```

---

## Task 8: NotificationConfigController tests (module-scoped API)

**Files:**
- Create: `tests/Feature/Notification/NotificationConfigControllerTest.php`
- Delete (if exists): `tests/Feature/NotificationConfigControllerTest.php` (replaced by this)

- [ ] **Step 1: Check if old test file exists + delete**

Run: `cd d:/danatec/qlcv && ls tests/Feature/NotificationConfigControllerTest.php 2>/dev/null`
If exists: `rm tests/Feature/NotificationConfigControllerTest.php`. Test coverage sẽ được thay thế bởi file mới với API module-scoped.

- [ ] **Step 2: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class NotificationConfigControllerTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seedNotificationConfig();
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_modules_endpoint_returns_registry(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->getJson('/api/notifications/modules');

        $res->assertOk();
        $res->assertJsonPath('data.0.key', 'task_assignment');
        $res->assertJsonPath('data.0.label', 'Giao việc');
        $this->assertCount(6, $res->json('data.0.events'));
        $this->assertTrue($res->json('data.0.events.3.is_reminder'));
    }

    public function test_event_config_index_returns_schedules_eager(): void
    {
        $this->actingAsSuperAdmin();
        $this->enableEvent('document_issued', ['sms', 'mail']);

        $res = $this->getJson('/api/task-assignment/notification-config/event-configs');

        $res->assertOk();
        $this->assertCount(6, $res->json('data'));
        $documentIssued = collect($res->json('data'))->firstWhere('event_key', 'document_issued');
        $this->assertTrue($documentIssued['enabled']);
        $this->assertCount(1, $documentIssued['schedules']);
        $this->assertSame(['sms', 'mail'], $documentIssued['schedules'][0]['channels']);
    }

    public function test_event_config_update_toggles_enabled(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->putJson('/api/task-assignment/notification-config/event-configs/document_issued', [
            'enabled' => true,
        ]);

        $res->assertOk();
        $cfg = NotificationEventConfig::where('event_key', 'document_issued')->first();
        $this->assertTrue($cfg->enabled);
    }

    public function test_schedule_store_for_reminder_event(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->postJson('/api/task-assignment/notification-config/event-configs/reminder_before/schedules', [
            'moment' => 'before',
            'offset_minutes' => 180,
            'channels' => ['sms', 'fcm'],
            'label' => 'Trước 3 giờ',
            'sort_order' => 5,
        ]);

        $res->assertCreated();
        $schedule = NotificationSchedule::latest()->first();
        $this->assertSame('before', $schedule->moment);
        $this->assertSame(180, $schedule->offset_minutes);
        $this->assertSame(['sms', 'fcm'], $schedule->channels);
    }

    public function test_schedule_store_for_non_reminder_forces_null_moment(): void
    {
        $this->actingAsSuperAdmin();

        $res = $this->postJson('/api/task-assignment/notification-config/event-configs/document_issued/schedules', [
            'moment' => 'before', // should be reset
            'offset_minutes' => 60, // should be reset
            'channels' => ['mail'],
            'label' => 'Extra instant',
        ]);

        $res->assertCreated();
        $schedule = NotificationSchedule::latest()->first();
        $this->assertNull($schedule->moment);
        $this->assertNull($schedule->offset_minutes);
    }

    public function test_schedule_update_by_id(): void
    {
        $this->actingAsSuperAdmin();
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        $res = $this->putJson("/api/notifications/schedules/{$schedule->id}", [
            'channels' => ['sms', 'mail'],
        ]);

        $res->assertOk();
        $this->assertSame(['sms', 'mail'], $schedule->fresh()->channels);
    }

    public function test_schedule_delete_by_id(): void
    {
        $this->actingAsSuperAdmin();
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        $res = $this->deleteJson("/api/notifications/schedules/{$schedule->id}");

        $res->assertOk();
        $this->assertNull(NotificationSchedule::find($schedule->id));
    }

    public function test_unauthenticated_returns_401(): void
    {
        $res = $this->getJson('/api/task-assignment/notification-config/event-configs');
        $res->assertUnauthorized();
    }

    public function test_user_without_permission_gets_403(): void
    {
        $user = User::factory()->create(); // no role
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/task-assignment/notification-config/event-configs');
        $res->assertForbidden();
    }
}
```

- [ ] **Step 3: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationConfigControllerTest`
Expected: PASS (9 tests).

- [ ] **Step 4: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/NotificationConfigControllerTest.php
git rm tests/Feature/NotificationConfigControllerTest.php 2>/dev/null || true
git commit -m "test(notification): add NotificationConfigController feature tests (module-scoped)"
```

---

## Task 9: NotificationLogController tests

**Files:**
- Create: `tests/Feature/Notification/NotificationLogControllerTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_index_returns_notifications_paginated(): void
    {
        $this->actingAsSuperAdmin();
        Notification::factory()->count(3)->create();

        $res = $this->getJson('/api/notifications/logs');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.total'));
    }

    public function test_index_filters_by_user_id(): void
    {
        $this->actingAsSuperAdmin();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $u1->id]);
        Notification::factory()->count(3)->create(['user_id' => $u2->id]);

        $res = $this->getJson("/api/notifications/logs?user_id={$u1->id}");

        $res->assertOk();
        $this->assertSame(2, $res->json('data.total'));
    }

    public function test_index_filters_by_event_key_and_date_range(): void
    {
        $this->actingAsSuperAdmin();
        Notification::factory()->create(['event_key' => 'document_issued', 'created_at' => now()->subDays(5)]);
        Notification::factory()->create(['event_key' => 'task_completed', 'created_at' => now()]);
        Notification::factory()->create(['event_key' => 'document_issued', 'created_at' => now()]);

        $res = $this->getJson('/api/notifications/logs?event_key=document_issued&from_date='.now()->subDays(1)->toDateString());

        $res->assertOk();
        $this->assertSame(1, $res->json('data.total'));
    }

    public function test_index_filters_by_delivery_channel_and_status(): void
    {
        $this->actingAsSuperAdmin();
        $n1 = Notification::factory()->create();
        NotificationDelivery::factory()->create(['notification_id' => $n1->id, 'channel' => 'sms', 'status' => 'sent']);
        $n2 = Notification::factory()->create();
        NotificationDelivery::factory()->create(['notification_id' => $n2->id, 'channel' => 'mail', 'status' => 'failed']);

        $res = $this->getJson('/api/notifications/logs?channel=sms&delivery_status=sent');

        $res->assertOk();
        $this->assertSame(1, $res->json('data.total'));
        $this->assertSame($n1->id, $res->json('data.data.0.id'));
    }

    public function test_show_returns_notification_with_deliveries(): void
    {
        $this->actingAsSuperAdmin();
        $n = Notification::factory()->create();
        NotificationDelivery::factory()->count(2)->create(['notification_id' => $n->id]);

        $res = $this->getJson("/api/notifications/logs/{$n->id}");

        $res->assertOk();
        $this->assertSame($n->id, $res->json('data.id'));
        $this->assertCount(2, $res->json('data.deliveries'));
    }

    public function test_stats_returns_aggregates(): void
    {
        $this->actingAsSuperAdmin();
        $n1 = Notification::factory()->create(['event_key' => 'document_issued']);
        $n2 = Notification::factory()->create(['event_key' => 'task_completed']);
        NotificationDelivery::factory()->create(['notification_id' => $n1->id, 'channel' => 'sms', 'status' => 'sent']);
        NotificationDelivery::factory()->create(['notification_id' => $n1->id, 'channel' => 'mail', 'status' => 'failed']);
        NotificationDelivery::factory()->create(['notification_id' => $n2->id, 'channel' => 'sms', 'status' => 'sent']);

        $res = $this->getJson('/api/notifications/logs/stats');

        $res->assertOk();
        $this->assertSame(2, $res->json('data.total'));
        $this->assertSame(1, $res->json('data.by_event.document_issued'));
        $this->assertSame(1, $res->json('data.by_event.task_completed'));
        $this->assertSame(2, $res->json('data.by_channel.sms'));
        $this->assertSame(1, $res->json('data.by_channel.mail'));
        $this->assertSame(2, $res->json('data.by_status.sent'));
        $this->assertSame(1, $res->json('data.by_status.failed'));
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationLogControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/NotificationLogControllerTest.php
git commit -m "test(notification): add NotificationLogController feature tests"
```

---

## Task 10: MyNotificationController tests

**Files:**
- Create: `tests/Feature/Notification/MyNotificationControllerTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_current_user_notifications(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Notification::factory()->count(2)->create(['user_id' => $u1->id]);
        Notification::factory()->count(5)->create(['user_id' => $u2->id]);
        Sanctum::actingAs($u1);

        $res = $this->getJson('/api/notifications/me');

        $res->assertOk();
        $this->assertSame(2, $res->json('data.total'));
    }

    public function test_unread_count(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/notifications/me/unread-count');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.unread_count'));
    }

    public function test_mark_as_read(): void
    {
        $user = User::factory()->create();
        $n = Notification::factory()->unread()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->patchJson("/api/notifications/me/{$n->id}/read");

        $res->assertOk();
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->patchJson('/api/notifications/me/read-all');

        $res->assertOk();
        $this->assertSame(3, $res->json('data.updated'));
        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_destroy_own_notification(): void
    {
        $user = User::factory()->create();
        $n = Notification::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $res = $this->deleteJson("/api/notifications/me/{$n->id}");

        $res->assertOk();
        $this->assertNull(Notification::find($n->id));
    }

    public function test_cannot_read_others_notification(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $n = Notification::factory()->unread()->create(['user_id' => $u2->id]);
        Sanctum::actingAs($u1);

        $res = $this->patchJson("/api/notifications/me/{$n->id}/read");

        $res->assertNotFound();
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=MyNotificationControllerTest`
Expected: PASS (6 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/MyNotificationControllerTest.php
git commit -m "test(notification): add MyNotificationController feature tests"
```

---

## Task 11: SyncFcmToken middleware test

**Files:**
- Create: `tests/Feature/Notification/SyncFcmTokenMiddlewareTest.php`

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncFcmTokenMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_fcm_token_when_header_present_and_differs(): void
    {
        $user = User::factory()->create(['fcm_token' => null]);
        Sanctum::actingAs($user);

        $this->withHeaders(['X-FCM-Token' => 'new-device-token-abc'])
            ->getJson('/api/user')
            ->assertOk();

        $this->assertSame('new-device-token-abc', $user->fresh()->fcm_token);
    }

    public function test_does_not_update_when_token_matches(): void
    {
        $user = User::factory()->create(['fcm_token' => 'existing-token']);
        $originalUpdatedAt = $user->updated_at;
        Sanctum::actingAs($user);

        sleep(1);
        $this->withHeaders(['X-FCM-Token' => 'existing-token'])
            ->getJson('/api/user')
            ->assertOk();

        // updateQuietly doesn't bump timestamps, but we just verify no change
        $this->assertSame('existing-token', $user->fresh()->fcm_token);
    }

    public function test_skips_when_no_header(): void
    {
        $user = User::factory()->create(['fcm_token' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/user')->assertOk();

        $this->assertNull($user->fresh()->fcm_token);
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=SyncFcmTokenMiddlewareTest`
Expected: PASS (3 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/SyncFcmTokenMiddlewareTest.php
git commit -m "test(notification): add SyncFcmToken middleware tests"
```

---

## Task 12: Integration test — DocumentIssued end-to-end flow

**Files:**
- Create: `tests/Feature/Notification/DocumentIssuedFlowTest.php`

Verifies the complete flow: event dispatch → listener handled → notifications + deliveries created → jobs queued.

- [ ] **Step 1: Write tests**

```php
<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class DocumentIssuedFlowTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeDocumentWithItemAndAssignees(int $assigneeCount): TaskAssignmentDocument
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'D', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued', 'issued_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'department_id' => $deptId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => 'no_deadline',
            'processing_status' => 'todo',
            'completion_percent' => 0,
        ]);
        for ($i = 0; $i < $assigneeCount; $i++) {
            $u = User::factory()->create();
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $item->id,
                'user_id' => $u->id,
                'assignment_status' => 'assigned',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return TaskAssignmentDocument::with('items.users')->find($docId);
    }

    public function test_event_fires_creates_notifications_and_deliveries_for_all_assignees(): void
    {
        Queue::fake();
        $this->enableEvent('document_issued', ['sms', 'mail']);
        $document = $this->makeDocumentWithItemAndAssignees(2);

        event(new DocumentIssued($document));

        // 2 assignees × 1 item = 2 notifications
        $this->assertSame(2, Notification::count());
        // Each with 2 channels (sms + mail) = 4 deliveries
        $this->assertSame(4, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 4);
    }

    public function test_does_nothing_when_event_config_disabled(): void
    {
        Queue::fake();
        // Default enabled=false (seed)
        $document = $this->makeDocumentWithItemAndAssignees(2);

        event(new DocumentIssued($document));

        $this->assertSame(0, Notification::count());
        Queue::assertNothingPushed();
    }

    public function test_does_nothing_when_instant_schedule_channels_empty(): void
    {
        Queue::fake();
        $this->enableEvent('document_issued', []); // empty channels
        $document = $this->makeDocumentWithItemAndAssignees(2);

        event(new DocumentIssued($document));

        $this->assertSame(0, Notification::count());
        Queue::assertNothingPushed();
    }
}
```

- [ ] **Step 2: Run**

Run: `cd d:/danatec/qlcv && php artisan test --filter=DocumentIssuedFlowTest`
Expected: PASS (3 tests).

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add tests/Feature/Notification/DocumentIssuedFlowTest.php
git commit -m "test(notification): add DocumentIssued integration flow test"
```

---

## Task 13: Final verification

- [ ] **Step 1: Run full suite**

Run: `cd d:/danatec/qlcv && php artisan test`
Expected: all tests pass (85 existing + ~40 new ≈ 125+ tests).

If any pre-existing test fails that was not caused by this plan, note it but do NOT fix as part of this plan.

- [ ] **Step 2: Run pint on new files**

Run: `cd d:/danatec/qlcv && vendor/bin/pint tests/Feature/Notification tests/Concerns database/factories/Modules/Core/Models/Notification*`
If changes applied, re-run tests.

- [ ] **Step 3: Verify test count grouped by file**

Run: `cd d:/danatec/qlcv && php artisan test --list-tests 2>&1 | grep -c "Tests\\\\Feature\\\\Notification"`
Expected: ≥ 40 (should match ~40 new tests).

- [ ] **Step 4: Commit pint changes if any**

```bash
cd d:/danatec/qlcv
git add -u
git diff --cached --stat
git commit -m "style(notification-tests): apply pint formatting" || echo "no changes"
```

---

## Acceptance criteria (Phase A Tests)

- [ ] Factories exist cho 4 Notification models với states tiện dụng
- [ ] `InteractsWithNotifications` trait dùng được cho setup test nhanh
- [ ] 4 feature test cho NotificationDispatcher (creates notification + deliveries + queues jobs)
- [ ] 4 feature test cho SendDeliveryJob (pending check, skipped, sent, failed)
- [ ] 5 feature test cho ReminderScheduler (no deadline, done, creates rows, reschedule, cancel)
- [ ] 4 feature test cho Observer (create, done, reschedule, delete)
- [ ] 4 feature test cho ProcessRemindersCommand (not due, due fire, item done, event disabled)
- [ ] 9 feature test cho NotificationConfigController (modules, list, update, schedules CRUD, auth/perm)
- [ ] 6 feature test cho NotificationLogController (list + filters + show + stats)
- [ ] 6 feature test cho MyNotificationController (list/unread-count/read/read-all/delete/scope)
- [ ] 3 feature test cho SyncFcmToken middleware
- [ ] 3 feature test cho DocumentIssued integration flow
- [ ] Full suite pass (~125 tests)

## Out of scope — chuyển sang Phase B (viết sau)

- Content builders chi tiết: 24 test (6 event × 4 channel) cho render output
- TaskCompleted, TaskConfirmed integration flow (tương tự DocumentIssued)
- Reminder before/on/after 3 event × channels dispatch integration
- Edge cases: user thiếu fcm_token, SMS phone invalid, Zalo template fail
- Permission gates cho từng route riêng lẻ
- Time-based edge cases (reminder fire at boundary, schedule update when reminder pending, etc.)
- E2E manual test với real providers

## Notes

- **DB-insert helpers** trong test dùng vì factory cho TaskAssignment models chưa đủ mature. Nếu sau này có factory → refactor tests.
- **Queue::fake** dùng cho mọi test — không test worker thật (khác range).
- **Event::fake** không dùng ở integration test vì cần listener thực thi. Dùng Laravel sync queue trong phpunit.xml đảm bảo listener chạy synchronously.
