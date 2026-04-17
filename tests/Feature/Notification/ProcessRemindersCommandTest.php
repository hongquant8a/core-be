<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Console\Scheduling\Schedule;
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
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'code' => 'DEPT', 'name' => 'Dept', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
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
            'department_id' => $deptId,
            'department_role' => 'main',
            'user_id' => $user->id,
            'assignment_role' => 'main',
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

    public function test_command_registered_in_scheduler_every_minute(): void
    {
        $schedule = app(Schedule::class);
        $events = collect($schedule->events())->filter(
            fn ($e) => str_contains($e->command ?? '', 'notifications:process-reminders')
        );

        $this->assertCount(1, $events, 'Command not registered in scheduler');
        /** @var \Illuminate\Console\Scheduling\Event $event */
        $event = $events->first();
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
