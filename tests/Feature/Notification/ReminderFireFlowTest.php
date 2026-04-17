<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\Jobs\SendDeliveryJob;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class ReminderFireFlowTest extends TestCase
{
    use InteractsWithNotifications;
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeItemWithAssignee(string $endAt): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'D', 'code' => 'DEPT-'.uniqid(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'organization_id' => $this->resolveTestOrganization()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => 'has_deadline',
            'end_at' => $endAt,
            'processing_status' => 'todo',
            'completion_percent' => 0,
        ]);
        $u = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $u->id,
            'department_id' => $deptId,
            'department_role' => 'member',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $item->fresh(['users']);
    }

    public function test_reminder_before_fires_creates_notification_and_delivery(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        // end_at in 30min → reminder "before 60min" scheduled at now-30min → past due
        $this->makeItemWithAssignee(now()->addMinutes(30)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::count());
        $n = Notification::first();
        $this->assertSame('reminder_before', $n->event_key);
        $this->assertSame(1, NotificationDelivery::count());
        $this->assertSame('mail', NotificationDelivery::first()->channel);
        Queue::assertPushed(SendDeliveryJob::class, 1);
        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
    }

    public function test_reminder_on_fires_when_deadline_passed(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('reminder_on', []);
        $this->addReminderSchedule('reminder_on', 'on', null, ['sms']);

        // end_at in the past → reminder_on due
        $this->makeItemWithAssignee(now()->subMinutes(5)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::count());
        $this->assertSame('reminder_on', Notification::first()->event_key);
        Queue::assertPushed(SendDeliveryJob::class, 1);
    }

    public function test_reminder_after_fires_offset_past_deadline(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('reminder_after', []);
        $this->addReminderSchedule('reminder_after', 'after', 60, ['mail']);

        // end_at 2h ago → after 60min reminder at (end_at + 60min) = 1h ago → due
        $this->makeItemWithAssignee(now()->subHours(2)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::count());
        $this->assertSame('reminder_after', Notification::first()->event_key);
    }

    public function test_real_queue_end_to_end_reminder_delivery(): void
    {
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->andReturn([new SendResult('mail', true, 'msg-r1')]);
        $this->app->instance(NotificationService::class, $svc);

        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(now()->addMinutes(30)->toDateTimeString());

        // NO Queue::fake — sync queue runs job inline
        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $delivery = NotificationDelivery::first();
        $this->assertNotNull($delivery);
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('msg-r1', $delivery->message_id);
    }
}
