# Notification Phase A — Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây foundation cho notification system mới: tables, models, enums, `NotificationDispatcher` service, `SendDeliveryJob`, admin config API.

**Architecture:** 3 bảng mới (`notifications` polymorphic, `notification_deliveries`, `notification_event_configs`, `notification_schedules`); restructure `task_assignment_reminders`. Seed 6 event configs + default schedules. `NotificationDispatcher` là entrypoint: tạo notification + deliveries + dispatch `SendDeliveryJob` (gọi `NotificationService` đã có). CRUD API cho admin qua Core module.

**Tech Stack:** Laravel 12, PHP 8.2+, Eloquent, Laravel queue (database driver), Spatie Permission, PHPUnit 11, Mockery.

**Spec:** [docs/superpowers/specs/2026-04-16-notification-events-reminders-design.md](../specs/2026-04-16-notification-events-reminders-design.md) §3-9

---

## File Structure

### Created
- `database/migrations/{stamp}_create_notifications_table.php`
- `database/migrations/{stamp}_create_notification_deliveries_table.php`
- `database/migrations/{stamp}_create_notification_event_configs_table.php`
- `database/migrations/{stamp}_create_notification_schedules_table.php`
- `database/migrations/{stamp}_restructure_task_assignment_reminders_table.php`
- `app/Services/Notification/Enums/NotificationEventEnum.php`
- `app/Services/Notification/Enums/NotificationMomentEnum.php`
- `app/Modules/Core/Models/Notification.php`
- `app/Modules/Core/Models/NotificationDelivery.php`
- `app/Modules/Core/Models/NotificationEventConfig.php`
- `app/Modules/Core/Models/NotificationSchedule.php`
- `app/Services/Notification/Contracts/ContentBuilder.php`
- `app/Services/Notification/Services/NotificationDispatcher.php`
- `app/Services/Notification/Services/ContentBuilderRegistry.php`
- `app/Services/Notification/Jobs/SendDeliveryJob.php`
- `database/seeders/NotificationEventConfigSeeder.php`
- `database/seeders/NotificationScheduleSeeder.php`
- `app/Modules/Core/NotificationConfigController.php`
- `app/Modules/Core/Requests/UpdateNotificationEventConfigRequest.php`
- `app/Modules/Core/Requests/StoreNotificationScheduleRequest.php`
- `app/Modules/Core/Requests/UpdateNotificationScheduleRequest.php`
- Tests for each above

### Modified
- `app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php` (update fillable + casts)
- `database/seeders/DatabaseSeeder.php` (call new seeders)
- `database/seeders/PermissionSeeder.php` (add permissions)
- `app/Modules/Core/Routes/notification.php` (add config endpoints)

---

## Task 1: Migration — `notifications` table (polymorphic)

**Files:**
- Create: `database/migrations/2026_04_16_100000_create_notifications_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_key', 50);
            $table->morphs('notifiable');
            $table->string('title');
            $table->text('body');
            $table->json('context')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd d:/danatec/qlcv && php artisan migrate`
Expected: `2026_04_16_100000_create_notifications_table ... DONE`

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add database/migrations/2026_04_16_100000_create_notifications_table.php
git commit -m "feat(notification): add notifications table (polymorphic in-app list)"
```

---

## Task 2: Migration — `notification_deliveries` table

**Files:**
- Create: `database/migrations/2026_04_16_100001_create_notification_deliveries_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->enum('status', ['pending', 'sent', 'failed', 'skipped'])->default('pending');
            $table->string('message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd d:/danatec/qlcv && php artisan migrate`
Expected: DONE

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add database/migrations/2026_04_16_100001_create_notification_deliveries_table.php
git commit -m "feat(notification): add notification_deliveries table (per-channel push log)"
```

---

## Task 3: Migration — `notification_event_configs` table

**Files:**
- Create: `database/migrations/2026_04_16_100002_create_notification_event_configs_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_configs', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 50)->unique();
            $table->boolean('enabled')->default(false);
            $table->json('channels')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_configs');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd d:/danatec/qlcv && php artisan migrate`
Expected: DONE

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add database/migrations/2026_04_16_100002_create_notification_event_configs_table.php
git commit -m "feat(notification): add notification_event_configs table"
```

---

## Task 4: Migration — `notification_schedules` table

**Files:**
- Create: `database/migrations/2026_04_16_100003_create_notification_schedules_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->enum('moment', ['before', 'on', 'after']);
            $table->unsignedInteger('offset_minutes')->nullable();
            $table->json('channels')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('label');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['moment', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd d:/danatec/qlcv && php artisan migrate`
Expected: DONE

- [ ] **Step 3: Commit**

```bash
cd d:/danatec/qlcv
git add database/migrations/2026_04_16_100003_create_notification_schedules_table.php
git commit -m "feat(notification): add notification_schedules table"
```

---

## Task 5: Migration — restructure `task_assignment_reminders`

**Files:**
- Create: `database/migrations/2026_04_16_100004_restructure_task_assignment_reminders_table.php`
- Modify: `app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php`

- [ ] **Step 1: Create migration (drop + recreate — verify table empty first)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Safety: abort if table has data
        if (Schema::hasTable('task_assignment_reminders')) {
            $count = DB::table('task_assignment_reminders')->count();
            if ($count > 0) {
                throw new \RuntimeException("task_assignment_reminders has {$count} rows — refusing to drop. Manual migration needed.");
            }
        }

        Schema::dropIfExists('task_assignment_reminders');

        Schema::create('task_assignment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignment_item_id')->constrained('task_assignment_items')->cascadeOnDelete();
            $table->foreignId('notification_schedule_id')->constrained('notification_schedules')->cascadeOnDelete();
            $table->enum('moment', ['before', 'on', 'after']);
            $table->dateTime('remind_at');
            $table->enum('status', ['pending', 'fired', 'cancelled'])->default('pending');
            $table->dateTime('fired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_reminders');
    }
};
```

- [ ] **Step 2: Update model `app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php`**

Replace entire file with:

```php
<?php

namespace App\Modules\TaskAssignment\Models;

use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Eloquent\Model;

class TaskAssignmentReminder extends Model
{
    protected $table = 'task_assignment_reminders';

    protected $fillable = [
        'task_assignment_item_id',
        'notification_schedule_id',
        'moment',
        'remind_at',
        'status',
        'fired_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'fired_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(TaskAssignmentItem::class, 'task_assignment_item_id');
    }

    public function schedule()
    {
        return $this->belongsTo(NotificationSchedule::class, 'notification_schedule_id');
    }
}
```

- [ ] **Step 3: Run migration**

Run: `cd d:/danatec/qlcv && php artisan migrate`
Expected: DONE

- [ ] **Step 4: Commit**

```bash
cd d:/danatec/qlcv
git add database/migrations/2026_04_16_100004_restructure_task_assignment_reminders_table.php app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php
git commit -m "feat(notification): restructure task_assignment_reminders as schedule-only"
```

---

## Task 6: Enums — NotificationEventEnum + NotificationMomentEnum

**Files:**
- Create: `app/Services/Notification/Enums/NotificationEventEnum.php`
- Create: `app/Services/Notification/Enums/NotificationMomentEnum.php`
- Test: `tests/Unit/Services/Notification/Enums/NotificationEventEnumTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Tests\Unit\Services\Notification\Enums;

use App\Services\Notification\Enums\NotificationEventEnum;
use App\Services\Notification\Enums\NotificationMomentEnum;
use PHPUnit\Framework\TestCase;

class NotificationEventEnumTest extends TestCase
{
    public function test_event_values(): void
    {
        $this->assertSame('document_issued', NotificationEventEnum::DocumentIssued->value);
        $this->assertSame('task_completed', NotificationEventEnum::TaskCompleted->value);
        $this->assertSame('task_confirmed', NotificationEventEnum::TaskConfirmed->value);
        $this->assertSame('reminder_before', NotificationEventEnum::ReminderBefore->value);
        $this->assertSame('reminder_on', NotificationEventEnum::ReminderOn->value);
        $this->assertSame('reminder_after', NotificationEventEnum::ReminderAfter->value);
    }

    public function test_values_returns_all(): void
    {
        $values = NotificationEventEnum::values();
        $this->assertContains('document_issued', $values);
        $this->assertCount(6, $values);
    }

    public function test_moment_values(): void
    {
        $this->assertSame('before', NotificationMomentEnum::Before->value);
        $this->assertSame('on', NotificationMomentEnum::On->value);
        $this->assertSame('after', NotificationMomentEnum::After->value);
    }
}
```

- [ ] **Step 2: Run — expect fail**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationEventEnumTest`
Expected: FAIL — class not found

- [ ] **Step 3: Implement `NotificationEventEnum`**

```php
<?php

namespace App\Services\Notification\Enums;

enum NotificationEventEnum: string
{
    case DocumentIssued = 'document_issued';
    case TaskCompleted = 'task_completed';
    case TaskConfirmed = 'task_confirmed';
    case ReminderBefore = 'reminder_before';
    case ReminderOn = 'reminder_on';
    case ReminderAfter = 'reminder_after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 4: Implement `NotificationMomentEnum`**

```php
<?php

namespace App\Services\Notification\Enums;

enum NotificationMomentEnum: string
{
    case Before = 'before';
    case On = 'on';
    case After = 'after';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

- [ ] **Step 5: Run — expect pass**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationEventEnumTest`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
cd d:/danatec/qlcv
git add app/Services/Notification/Enums tests/Unit/Services/Notification/Enums
git commit -m "feat(notification): add NotificationEventEnum and NotificationMomentEnum"
```

---

## Task 7: Models — Notification, NotificationDelivery, NotificationEventConfig, NotificationSchedule

**Files:**
- Create: `app/Modules/Core/Models/Notification.php`
- Create: `app/Modules/Core/Models/NotificationDelivery.php`
- Create: `app/Modules/Core/Models/NotificationEventConfig.php`
- Create: `app/Modules/Core/Models/NotificationSchedule.php`
- Test: `tests/Unit/Modules/Core/Models/NotificationModelsTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Tests\Unit\Modules\Core\Models;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_belongs_to_user_and_has_deliveries(): void
    {
        $user = \App\Modules\Core\Models\User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'event_key' => 'document_issued',
            'notifiable_type' => 'App\\Test\\Dummy',
            'notifiable_id' => 1,
            'title' => 'Test',
            'body' => 'Body',
            'context' => ['foo' => 'bar'],
        ]);

        $this->assertSame($user->id, $notification->user->id);
        $this->assertSame(['foo' => 'bar'], $notification->context);

        NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'sms',
            'status' => 'pending',
        ]);

        $this->assertCount(1, $notification->deliveries);
    }

    public function test_event_config_casts_channels(): void
    {
        $cfg = NotificationEventConfig::create([
            'event_key' => 'document_issued',
            'enabled' => true,
            'channels' => ['sms', 'mail'],
        ]);

        $this->assertSame(['sms', 'mail'], $cfg->fresh()->channels);
        $this->assertTrue($cfg->fresh()->enabled);
    }

    public function test_schedule_casts_fields(): void
    {
        $sch = NotificationSchedule::create([
            'moment' => 'before',
            'offset_minutes' => 1440,
            'channels' => ['mail'],
            'enabled' => true,
            'label' => 'Trước 1 ngày',
            'sort_order' => 1,
        ]);

        $this->assertSame(['mail'], $sch->fresh()->channels);
        $this->assertSame(1440, $sch->fresh()->offset_minutes);
    }
}
```

- [ ] **Step 2: Run — expect fail (classes missing)**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationModelsTest`
Expected: FAIL

- [ ] **Step 3: Implement `app/Modules/Core/Models/Notification.php`**

```php
<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id',
        'event_key',
        'notifiable_type',
        'notifiable_id',
        'title',
        'body',
        'context',
        'read_at',
    ];

    protected $casts = [
        'context' => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function deliveries()
    {
        return $this->hasMany(NotificationDelivery::class);
    }
}
```

- [ ] **Step 4: Implement `app/Modules/Core/Models/NotificationDelivery.php`**

```php
<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $table = 'notification_deliveries';

    protected $fillable = [
        'notification_id',
        'channel',
        'status',
        'message_id',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
```

- [ ] **Step 5: Implement `app/Modules/Core/Models/NotificationEventConfig.php`**

```php
<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationEventConfig extends Model
{
    protected $table = 'notification_event_configs';

    protected $fillable = [
        'event_key',
        'enabled',
        'channels',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'channels' => 'array',
    ];
}
```

- [ ] **Step 6: Implement `app/Modules/Core/Models/NotificationSchedule.php`**

```php
<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSchedule extends Model
{
    protected $table = 'notification_schedules';

    protected $fillable = [
        'moment',
        'offset_minutes',
        'channels',
        'enabled',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'channels' => 'array',
        'enabled' => 'boolean',
    ];
}
```

- [ ] **Step 7: Run — expect pass**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationModelsTest`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
cd d:/danatec/qlcv
git add app/Modules/Core/Models/Notification.php app/Modules/Core/Models/NotificationDelivery.php app/Modules/Core/Models/NotificationEventConfig.php app/Modules/Core/Models/NotificationSchedule.php tests/Unit/Modules/Core/Models/NotificationModelsTest.php
git commit -m "feat(notification): add Notification, Delivery, EventConfig, Schedule models"
```

---

## Task 8: Seeders — default event configs + schedules

**Files:**
- Create: `database/seeders/NotificationEventConfigSeeder.php`
- Create: `database/seeders/NotificationScheduleSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Create `NotificationEventConfigSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Services\Notification\Enums\NotificationEventEnum;
use Illuminate\Database\Seeder;

class NotificationEventConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach (NotificationEventEnum::values() as $eventKey) {
            NotificationEventConfig::firstOrCreate(
                ['event_key' => $eventKey],
                ['enabled' => false, 'channels' => []]
            );
        }
    }
}
```

- [ ] **Step 2: Create `NotificationScheduleSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Modules\Core\Models\NotificationSchedule;
use Illuminate\Database\Seeder;

class NotificationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['moment' => 'before', 'offset_minutes' => 1440, 'channels' => ['mail'], 'label' => 'Nhắc trước 1 ngày', 'sort_order' => 1],
            ['moment' => 'before', 'offset_minutes' => 120,  'channels' => ['sms', 'fcm'], 'label' => 'Nhắc trước 2 giờ', 'sort_order' => 2],
            ['moment' => 'on',     'offset_minutes' => null, 'channels' => ['sms', 'mail', 'fcm'], 'label' => 'Đến hạn', 'sort_order' => 3],
            ['moment' => 'after',  'offset_minutes' => 1440, 'channels' => ['mail'], 'label' => 'Trễ 1 ngày', 'sort_order' => 4],
        ];

        foreach ($defaults as $d) {
            NotificationSchedule::firstOrCreate(
                ['moment' => $d['moment'], 'offset_minutes' => $d['offset_minutes']],
                ['channels' => $d['channels'], 'enabled' => true, 'label' => $d['label'], 'sort_order' => $d['sort_order']]
            );
        }
    }
}
```

- [ ] **Step 3: Update `database/seeders/DatabaseSeeder.php`**

Read the file first, then add calls:

```php
$this->call([
    PermissionSeeder::class,
    SettingSeeder::class,
    NotificationEventConfigSeeder::class,
    NotificationScheduleSeeder::class,
    // ... existing
]);
```

Exact edit depends on current contents — ensure both new seeder classes are called. If file uses `$this->call(PermissionSeeder::class)` individual pattern, append both new calls before or after existing ones.

- [ ] **Step 4: Run seeders**

Run: `cd d:/danatec/qlcv && php artisan db:seed --class=NotificationEventConfigSeeder && php artisan db:seed --class=NotificationScheduleSeeder`
Expected: both run cleanly, 6 event configs + 4 schedule rows inserted.

- [ ] **Step 5: Verify**

Run: `cd d:/danatec/qlcv && php artisan tinker --execute='echo \App\Modules\Core\Models\NotificationEventConfig::count().";".\App\Modules\Core\Models\NotificationSchedule::count();'`
Expected: `6;4`

- [ ] **Step 6: Commit**

```bash
cd d:/danatec/qlcv
git add database/seeders/NotificationEventConfigSeeder.php database/seeders/NotificationScheduleSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(notification): seed default event configs + reminder schedules"
```

---

## Task 9: ContentBuilder contract + registry

**Files:**
- Create: `app/Services/Notification/Contracts/ContentBuilder.php`
- Create: `app/Services/Notification/Services/ContentBuilderRegistry.php`
- Test: `tests/Unit/Services/Notification/Services/ContentBuilderRegistryTest.php`

- [ ] **Step 1: Create interface**

```php
<?php

namespace App\Services\Notification\Contracts;

use App\Modules\Core\Models\User;
use App\Services\Notification\DTOs\NotificationPayload;

interface ContentBuilder
{
    /**
     * Build a payload for a specific channel, or null to skip (vd recipient missing field).
     */
    public function build(string $channelKey, User $recipient, mixed ...$args): ?NotificationPayload;

    /**
     * In-app notification title (displayed in list).
     */
    public function title(User $recipient, mixed ...$args): string;

    /**
     * In-app short body (displayed in list).
     */
    public function shortBody(User $recipient, mixed ...$args): string;

    /**
     * Extra context for in-app (vd url, action).
     */
    public function inAppContext(User $recipient, mixed ...$args): array;
}
```

- [ ] **Step 2: Write test for registry**

```php
<?php

namespace Tests\Unit\Services\Notification\Services;

use App\Services\Notification\Services\ContentBuilderRegistry;
use App\Services\Notification\Contracts\ContentBuilder;
use PHPUnit\Framework\TestCase;

class ContentBuilderRegistryTest extends TestCase
{
    public function test_register_and_resolve_builder(): void
    {
        $registry = new ContentBuilderRegistry();
        $builder = $this->createMock(ContentBuilder::class);

        $registry->register('document_issued', $builder);

        $this->assertSame($builder, $registry->for('document_issued'));
    }

    public function test_throws_when_not_registered(): void
    {
        $registry = new ContentBuilderRegistry();
        $this->expectException(\RuntimeException::class);
        $registry->for('nonexistent');
    }
}
```

- [ ] **Step 3: Run test — expect fail**

Run: `cd d:/danatec/qlcv && php artisan test --filter=ContentBuilderRegistryTest`
Expected: FAIL

- [ ] **Step 4: Implement `ContentBuilderRegistry`**

```php
<?php

namespace App\Services\Notification\Services;

use App\Services\Notification\Contracts\ContentBuilder;
use RuntimeException;

class ContentBuilderRegistry
{
    /** @var array<string, ContentBuilder> */
    private array $builders = [];

    public function register(string $eventKey, ContentBuilder $builder): void
    {
        $this->builders[$eventKey] = $builder;
    }

    public function for(string $eventKey): ContentBuilder
    {
        if (! isset($this->builders[$eventKey])) {
            throw new RuntimeException("No ContentBuilder registered for event: {$eventKey}");
        }

        return $this->builders[$eventKey];
    }
}
```

- [ ] **Step 5: Run — expect pass**

Run: `cd d:/danatec/qlcv && php artisan test --filter=ContentBuilderRegistryTest`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
cd d:/danatec/qlcv
git add app/Services/Notification/Contracts/ContentBuilder.php app/Services/Notification/Services/ContentBuilderRegistry.php tests/Unit/Services/Notification/Services/ContentBuilderRegistryTest.php
git commit -m "feat(notification): add ContentBuilder contract and registry"
```

---

## Task 10: NotificationDispatcher service

**Files:**
- Create: `app/Services/Notification/Services/NotificationDispatcher.php`
- Test: `tests/Unit/Services/Notification/Services/NotificationDispatcherTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Tests\Unit\Services\Notification\Services;

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

    public function test_creates_notification_and_deliveries_and_dispatches_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create(); // use User as a stand-in Model with id

        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('title')->andReturn('T');
        $builder->shouldReceive('shortBody')->andReturn('B');
        $builder->shouldReceive('inAppContext')->andReturn(['k' => 'v']);

        $dispatcher = new NotificationDispatcher();
        $notification = $dispatcher->dispatch(
            eventKey: 'test_event',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms', 'mail'],
            builder: $builder,
            builderArgs: ['arg1'],
        );

        $this->assertSame($user->id, $notification->user_id);
        $this->assertSame('T', $notification->title);
        $this->assertSame('B', $notification->body);
        $this->assertSame(['k' => 'v'], $notification->context);

        $this->assertCount(2, NotificationDelivery::where('notification_id', $notification->id)->get());
        Queue::assertPushed(SendDeliveryJob::class, 2);
    }
}
```

- [ ] **Step 2: Run test — expect fail**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationDispatcherTest`
Expected: FAIL — classes missing

- [ ] **Step 3: Implement `NotificationDispatcher`**

```php
<?php

namespace App\Services\Notification\Services;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Database\Eloquent\Model;

class NotificationDispatcher
{
    /**
     * Create notification + deliveries + dispatch jobs.
     *
     * @param  array<string>  $channels
     * @param  array<mixed>  $builderArgs  args to pass into builder methods
     */
    public function dispatch(
        string $eventKey,
        User $recipient,
        Model $notifiable,
        array $channels,
        ContentBuilder $builder,
        array $builderArgs = [],
    ): Notification {
        $notification = Notification::create([
            'user_id' => $recipient->id,
            'event_key' => $eventKey,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->getKey(),
            'title' => $builder->title($recipient, ...$builderArgs),
            'body' => $builder->shortBody($recipient, ...$builderArgs),
            'context' => $builder->inAppContext($recipient, ...$builderArgs),
        ]);

        foreach ($channels as $channelKey) {
            $delivery = NotificationDelivery::create([
                'notification_id' => $notification->id,
                'channel' => $channelKey,
                'status' => 'pending',
            ]);
            SendDeliveryJob::dispatch($delivery->id, $builderArgs)->onQueue('notifications');
        }

        return $notification;
    }
}
```

- [ ] **Step 4: Run test — expect still fail (SendDeliveryJob missing)**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationDispatcherTest`
Expected: FAIL referring to `SendDeliveryJob` class not found.

- [ ] **Step 5: Create stub `SendDeliveryJob` (full implementation Task 11)**

Create file `app/Services/Notification/Jobs/SendDeliveryJob.php`:

```php
<?php

namespace App\Services\Notification\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId, public array $builderArgs = []) {}

    public function handle(): void
    {
        // Full implementation in Task 11
    }
}
```

- [ ] **Step 6: Run test — expect pass**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationDispatcherTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
cd d:/danatec/qlcv
git add app/Services/Notification/Services/NotificationDispatcher.php app/Services/Notification/Jobs/SendDeliveryJob.php tests/Unit/Services/Notification/Services/NotificationDispatcherTest.php
git commit -m "feat(notification): add NotificationDispatcher service + SendDeliveryJob stub"
```

---

## Task 11: SendDeliveryJob full implementation

**Files:**
- Modify: `app/Services/Notification/Jobs/SendDeliveryJob.php`
- Test: `tests/Unit/Services/Notification/Jobs/SendDeliveryJobTest.php`

- [ ] **Step 1: Write test**

```php
<?php

namespace Tests\Unit\Services\Notification\Jobs;

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
        $notification = Notification::create([
            'user_id' => $user->id,
            'event_key' => 'test_evt',
            'notifiable_type' => 'Dummy',
            'notifiable_id' => 1,
            'title' => 't', 'body' => 'b', 'context' => [],
        ]);
        return NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => $channel,
            'status' => $status,
        ]);
    }

    public function test_skips_if_delivery_not_pending(): void
    {
        $delivery = $this->makeDelivery(status: 'sent');
        $job = new SendDeliveryJob($delivery->id, []);
        $job->handle();
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_marks_skipped_when_builder_returns_null(): void
    {
        $delivery = $this->makeDelivery();

        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(null);

        $registry = new ContentBuilderRegistry();
        $registry->register('test_evt', $builder);
        $this->app->instance(ContentBuilderRegistry::class, $registry);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldNotReceive('send');
        $this->app->instance(NotificationService::class, $svc);

        (new SendDeliveryJob($delivery->id, []))->handle();

        $this->assertSame('skipped', $delivery->fresh()->status);
    }

    public function test_marks_sent_on_success(): void
    {
        $delivery = $this->makeDelivery();

        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(new NotificationPayload(['sms'], new Recipient(phone: '0905'), 'hi'));

        $registry = new ContentBuilderRegistry();
        $registry->register('test_evt', $builder);
        $this->app->instance(ContentBuilderRegistry::class, $registry);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->andReturn([new SendResult('sms', true, 'msg-1')]);
        $this->app->instance(NotificationService::class, $svc);

        (new SendDeliveryJob($delivery->id, []))->handle();

        $fresh = $delivery->fresh();
        $this->assertSame('sent', $fresh->status);
        $this->assertSame('msg-1', $fresh->message_id);
        $this->assertNotNull($fresh->sent_at);
    }

    public function test_marks_failed_on_error(): void
    {
        $delivery = $this->makeDelivery();

        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(new NotificationPayload(['sms'], new Recipient(phone: '0905'), 'hi'));

        $registry = new ContentBuilderRegistry();
        $registry->register('test_evt', $builder);
        $this->app->instance(ContentBuilderRegistry::class, $registry);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->andReturn([new SendResult('sms', false, error: 'boom')]);
        $this->app->instance(NotificationService::class, $svc);

        (new SendDeliveryJob($delivery->id, []))->handle();

        $fresh = $delivery->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('boom', $fresh->error_message);
    }
}
```

- [ ] **Step 2: Run — expect fail**

Run: `cd d:/danatec/qlcv && php artisan test --filter=SendDeliveryJobTest`
Expected: FAIL — handle() empty

- [ ] **Step 3: Replace `SendDeliveryJob::handle()` with full implementation**

```php
<?php

namespace App\Services\Notification\Jobs;

use App\Modules\Core\Models\NotificationDelivery;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId, public array $builderArgs = []) {}

    public function handle(
        ContentBuilderRegistry $registry,
        NotificationService $notifier,
    ): void {
        $delivery = NotificationDelivery::with('notification.user')->find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'pending') {
            return;
        }

        $notification = $delivery->notification;
        $recipient = $notification->user;

        $builder = $registry->for($notification->event_key);

        $payload = $builder->build($delivery->channel, $recipient, ...$this->builderArgs);

        if ($payload === null) {
            $delivery->update([
                'status' => 'skipped',
                'error_message' => 'Recipient missing field for channel',
            ]);
            return;
        }

        $results = $notifier->send($payload);
        $result = $results[0];

        $delivery->update([
            'status' => $result->success ? 'sent' : 'failed',
            'message_id' => $result->messageId,
            'error_message' => $result->error,
            'sent_at' => $result->success ? now() : null,
        ]);
    }
}
```

- [ ] **Step 4: Run test — expect pass**

Run: `cd d:/danatec/qlcv && php artisan test --filter=SendDeliveryJobTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
cd d:/danatec/qlcv
git add app/Services/Notification/Jobs/SendDeliveryJob.php tests/Unit/Services/Notification/Jobs/SendDeliveryJobTest.php
git commit -m "feat(notification): implement SendDeliveryJob (fetch builder, call NotificationService, update delivery status)"
```

---

## Task 12: Bind ContentBuilderRegistry as singleton

**Files:**
- Modify: `app/Providers/NotificationServiceProvider.php`

- [ ] **Step 1: Read current provider**

Run: `cat d:/danatec/qlcv/app/Providers/NotificationServiceProvider.php`

- [ ] **Step 2: Update `register()` to also bind ContentBuilderRegistry as singleton**

Add inside `register()` method (above the `NotificationService` singleton binding):

```php
$this->app->singleton(\App\Services\Notification\Services\ContentBuilderRegistry::class);
```

This makes the registry a shared instance; future Phase B/C tasks will register builders via `$registry->register(...)` in provider boot.

- [ ] **Step 3: Verify**

Run: `cd d:/danatec/qlcv && php artisan tinker --execute='var_dump(app(\App\Services\Notification\Services\ContentBuilderRegistry::class) === app(\App\Services\Notification\Services\ContentBuilderRegistry::class));'`
Expected: `bool(true)`

- [ ] **Step 4: Commit**

```bash
cd d:/danatec/qlcv
git add app/Providers/NotificationServiceProvider.php
git commit -m "feat(notification): bind ContentBuilderRegistry as singleton"
```

---

## Task 13: PermissionSeeder — add notification admin permissions

**Files:**
- Modify: `database/seeders/PermissionSeeder.php`

- [ ] **Step 1: Read current seeder to locate `$PERMISSIONS` and `$RESOURCE_LABELS` arrays**

Run: `grep -n "notifications" d:/danatec/qlcv/database/seeders/PermissionSeeder.php`

(Existing entries: `'notifications' => ['test']` and `'notifications' => 'Thông báo'`.)

- [ ] **Step 2: Edit `$PERMISSIONS` array**

Find the line `'notifications' => ['test'],` and replace with:

```php
// Core - Cấu hình & test thông báo
'notifications' => ['test'],
'notifications.event-configs' => ['index', 'update'],
'notifications.schedules' => ['index', 'store', 'update', 'destroy'],
```

- [ ] **Step 3: Edit `$RESOURCE_LABELS` array**

Find the line `'notifications' => 'Thông báo',` and add AFTER it:

```php
'notifications.event-configs' => 'Cấu hình sự kiện thông báo',
'notifications.schedules' => 'Cấu hình lịch nhắc',
```

- [ ] **Step 4: Run seeder**

Run: `cd d:/danatec/qlcv && php artisan db:seed --class=PermissionSeeder`
Expected: completes. Super Admin now has 4 new permissions (`notifications.event-configs.index/update`, `notifications.schedules.index/store/update/destroy`).

- [ ] **Step 5: Verify**

Run: `cd d:/danatec/qlcv && php artisan tinker --execute='echo \App\Modules\Core\Models\Permission::where("name","like","notifications.%")->count();'`
Expected: `7` (1 existing `notifications.test` + 6 new)

- [ ] **Step 6: Commit**

```bash
cd d:/danatec/qlcv
git add database/seeders/PermissionSeeder.php
git commit -m "feat(notification): add event-configs + schedules admin permissions"
```

---

## Task 14: NotificationConfigController + FormRequests + routes

**Files:**
- Create: `app/Modules/Core/NotificationConfigController.php`
- Create: `app/Modules/Core/Requests/UpdateNotificationEventConfigRequest.php`
- Create: `app/Modules/Core/Requests/StoreNotificationScheduleRequest.php`
- Create: `app/Modules/Core/Requests/UpdateNotificationScheduleRequest.php`
- Modify: `app/Modules/Core/Routes/notification.php`
- Test: `tests/Feature/NotificationConfigControllerTest.php`

- [ ] **Step 1: Write feature test**

```php
<?php

namespace Tests\Feature;

use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\NotificationEventConfigSeeder::class);
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_list_event_configs(): void
    {
        $this->actingAsSuperAdmin();
        $res = $this->getJson('/api/notifications/event-configs');
        $res->assertOk();
        $res->assertJsonPath('success', true);
        $this->assertCount(6, $res->json('data'));
    }

    public function test_update_event_config(): void
    {
        $this->actingAsSuperAdmin();
        $res = $this->putJson('/api/notifications/event-configs/document_issued', [
            'enabled' => true,
            'channels' => ['sms', 'mail'],
        ]);
        $res->assertOk();
        $cfg = NotificationEventConfig::where('event_key', 'document_issued')->first();
        $this->assertTrue($cfg->enabled);
        $this->assertSame(['sms', 'mail'], $cfg->channels);
    }

    public function test_list_schedules(): void
    {
        $this->actingAsSuperAdmin();
        NotificationSchedule::create(['moment' => 'before', 'offset_minutes' => 60, 'channels' => ['sms'], 'enabled' => true, 'label' => 'T', 'sort_order' => 1]);
        $res = $this->getJson('/api/notifications/schedules');
        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_create_schedule(): void
    {
        $this->actingAsSuperAdmin();
        $res = $this->postJson('/api/notifications/schedules', [
            'moment' => 'before', 'offset_minutes' => 60, 'channels' => ['sms'], 'enabled' => true, 'label' => 'T', 'sort_order' => 1,
        ]);
        $res->assertCreated();
        $this->assertSame(1, NotificationSchedule::count());
    }

    public function test_update_schedule(): void
    {
        $this->actingAsSuperAdmin();
        $s = NotificationSchedule::create(['moment' => 'before', 'offset_minutes' => 60, 'channels' => ['sms'], 'enabled' => true, 'label' => 'T', 'sort_order' => 1]);
        $res = $this->putJson("/api/notifications/schedules/{$s->id}", ['label' => 'Updated']);
        $res->assertOk();
        $this->assertSame('Updated', $s->fresh()->label);
    }

    public function test_delete_schedule(): void
    {
        $this->actingAsSuperAdmin();
        $s = NotificationSchedule::create(['moment' => 'before', 'offset_minutes' => 60, 'channels' => ['sms'], 'enabled' => true, 'label' => 'T', 'sort_order' => 1]);
        $res = $this->deleteJson("/api/notifications/schedules/{$s->id}");
        $res->assertOk();
        $this->assertSame(0, NotificationSchedule::count());
    }
}
```

- [ ] **Step 2: Run test — expect fail**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationConfigControllerTest`
Expected: FAIL — route not found

- [ ] **Step 3: Create `UpdateNotificationEventConfigRequest.php`**

```php
<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationEventConfigRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'channels' => 'array',
            'channels.*' => 'in:sms,mail,zalo,fcm',
        ];
    }
}
```

- [ ] **Step 4: Create `StoreNotificationScheduleRequest.php`**

```php
<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'moment' => 'required|in:before,on,after',
            'offset_minutes' => 'nullable|integer|min:0',
            'channels' => 'required|array|min:1',
            'channels.*' => 'in:sms,mail,zalo,fcm',
            'enabled' => 'boolean',
            'label' => 'required|string|max:255',
            'sort_order' => 'integer',
        ];
    }
}
```

- [ ] **Step 5: Create `UpdateNotificationScheduleRequest.php`**

```php
<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationScheduleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'moment' => 'sometimes|in:before,on,after',
            'offset_minutes' => 'nullable|integer|min:0',
            'channels' => 'sometimes|array|min:1',
            'channels.*' => 'in:sms,mail,zalo,fcm',
            'enabled' => 'sometimes|boolean',
            'label' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer',
        ];
    }
}
```

- [ ] **Step 6: Create `NotificationConfigController.php`**

```php
<?php

namespace App\Modules\Core;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\NotificationEventConfig;
use App\Modules\Core\Models\NotificationSchedule;
use App\Modules\Core\Requests\StoreNotificationScheduleRequest;
use App\Modules\Core\Requests\UpdateNotificationEventConfigRequest;
use App\Modules\Core\Requests\UpdateNotificationScheduleRequest;

/**
 * @group Core - Notification Config
 */
class NotificationConfigController extends Controller
{
    public function eventConfigIndex()
    {
        return $this->success(NotificationEventConfig::orderBy('event_key')->get());
    }

    public function eventConfigUpdate(UpdateNotificationEventConfigRequest $request, string $eventKey)
    {
        $cfg = NotificationEventConfig::where('event_key', $eventKey)->firstOrFail();
        $cfg->update($request->validated());
        return $this->success($cfg->fresh());
    }

    public function scheduleIndex()
    {
        return $this->success(NotificationSchedule::orderBy('sort_order')->orderBy('id')->get());
    }

    public function scheduleStore(StoreNotificationScheduleRequest $request)
    {
        $schedule = NotificationSchedule::create($request->validated());
        return $this->success($schedule, 'Đã tạo lịch nhắc', 201);
    }

    public function scheduleUpdate(UpdateNotificationScheduleRequest $request, NotificationSchedule $schedule)
    {
        $schedule->update($request->validated());
        return $this->success($schedule->fresh());
    }

    public function scheduleDestroy(NotificationSchedule $schedule)
    {
        $schedule->delete();
        return $this->success(null, 'Đã xóa lịch nhắc');
    }
}
```

- [ ] **Step 7: Update `app/Modules/Core/Routes/notification.php`**

Replace existing content (keeps test route, adds config routes):

```php
<?php

use App\Modules\Core\NotificationConfigController;
use App\Modules\Core\NotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/test', [NotificationController::class, 'test'])
    ->middleware('permission:notifications.test,web');

// Event configs
Route::get('/event-configs', [NotificationConfigController::class, 'eventConfigIndex'])
    ->middleware('permission:notifications.event-configs.index,web');
Route::put('/event-configs/{eventKey}', [NotificationConfigController::class, 'eventConfigUpdate'])
    ->middleware('permission:notifications.event-configs.update,web');

// Schedules
Route::get('/schedules', [NotificationConfigController::class, 'scheduleIndex'])
    ->middleware('permission:notifications.schedules.index,web');
Route::post('/schedules', [NotificationConfigController::class, 'scheduleStore'])
    ->middleware('permission:notifications.schedules.store,web');
Route::put('/schedules/{schedule}', [NotificationConfigController::class, 'scheduleUpdate'])
    ->middleware('permission:notifications.schedules.update,web');
Route::delete('/schedules/{schedule}', [NotificationConfigController::class, 'scheduleDestroy'])
    ->middleware('permission:notifications.schedules.destroy,web');
```

- [ ] **Step 8: Run test — expect pass**

Run: `cd d:/danatec/qlcv && php artisan test --filter=NotificationConfigControllerTest`
Expected: PASS (6 tests)

- [ ] **Step 9: Commit**

```bash
cd d:/danatec/qlcv
git add app/Modules/Core/NotificationConfigController.php app/Modules/Core/Requests/UpdateNotificationEventConfigRequest.php app/Modules/Core/Requests/StoreNotificationScheduleRequest.php app/Modules/Core/Requests/UpdateNotificationScheduleRequest.php app/Modules/Core/Routes/notification.php tests/Feature/NotificationConfigControllerTest.php
git commit -m "feat(notification): add event-configs + schedules CRUD API"
```

---

## Task 15: Final verification

- [ ] **Step 1: Run full test suite**

Run: `cd d:/danatec/qlcv && php artisan test`
Expected: all tests pass — new Notification tests + existing 70+ tests, no regressions.

- [ ] **Step 2: Apply pint formatting**

Run: `cd d:/danatec/qlcv && vendor/bin/pint app/Services/Notification app/Modules/Core/Models/Notification.php app/Modules/Core/Models/NotificationDelivery.php app/Modules/Core/Models/NotificationEventConfig.php app/Modules/Core/Models/NotificationSchedule.php app/Modules/Core/NotificationConfigController.php app/Modules/Core/Requests/UpdateNotificationEventConfigRequest.php app/Modules/Core/Requests/StoreNotificationScheduleRequest.php app/Modules/Core/Requests/UpdateNotificationScheduleRequest.php app/Modules/TaskAssignment/Models/TaskAssignmentReminder.php database/seeders/NotificationEventConfigSeeder.php database/seeders/NotificationScheduleSeeder.php tests/Unit/Services/Notification tests/Unit/Modules/Core tests/Feature/NotificationConfigControllerTest.php`

- [ ] **Step 3: Rerun tests**

Run: `cd d:/danatec/qlcv && php artisan test`
Expected: all pass.

- [ ] **Step 4: Commit pint if any files changed**

```bash
cd d:/danatec/qlcv
git status --short
git add -u
git diff --cached --stat
# Only commit if there are changes
git commit -m "style(notification): apply pint formatting" || echo "no changes"
```

---

## Acceptance criteria (Phase A)

- [ ] 5 migrations applied, 4 new tables + 1 restructured.
- [ ] 4 new models work with factories/creation.
- [ ] Seed populated 6 event configs + 4 default schedules.
- [ ] 7 permissions exist for admin (Super Admin gets them all).
- [ ] CRUD API works: GET/PUT event-configs, CRUD schedules.
- [ ] `NotificationDispatcher` creates notifications + deliveries + dispatches jobs (verified via test).
- [ ] `SendDeliveryJob` reads delivery, calls builder, calls NotificationService, updates status.
- [ ] Full test suite passes.

Phase B (event-triggered notifications) and Phase C (scheduled reminders + user-facing API) will build on this foundation.
