<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
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
            'organization_id' => $this->resolveTestOrganization()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'Dept', 'status' => 'active',
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

    private function makeItemWithMultipleAssignees(int $count, ?string $endAt = null): TaskAssignmentItem
    {
        $item = $this->makeItemWithAssignee(endAt: $endAt);
        $deptId = DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $item->id)
            ->value('department_id');

        for ($i = 1; $i < $count; $i++) {
            $u = User::factory()->create(['email' => "r{$i}@test.com"]);
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $item->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'user_id' => $u->id,
                'assignment_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $item->fresh(['users']);
    }

    private function seedDuePendingReminders(int $count): void
    {
        $this->enableEvent('reminder_before', []);
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        $orgId = $this->resolveTestOrganization()->id;
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'name' => 'D', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'D', 'status' => 'issued',
            'organization_id' => $orgId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $itemId = DB::table('task_assignment_items')->insertGetId([
                'task_assignment_document_id' => $docId,
                'task_assignment_item_type_id' => $itemTypeId,
                'organization_id' => $orgId,
                'name' => "Bulk {$i}",
                'priority' => 'normal',
                'deadline_type' => 'has_deadline',
                'end_at' => now()->addMinutes(30),
                'processing_status' => 'todo',
                'completion_percent' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $itemId,
                'user_id' => $user->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('task_assignment_reminders')->insert([
                'task_assignment_item_id' => $itemId,
                'notification_schedule_id' => $schedule->id,
                'moment' => 'before',
                'remind_at' => now()->subMinute(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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

    public function test_fires_when_remind_at_equals_now_to_the_minute(): void
    {
        Carbon::setTestNow('2026-04-28 14:37:00');
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:00');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
        $this->assertSame(1, Notification::count());
    }

    public function test_does_not_fire_one_second_before_remind_at(): void
    {
        Carbon::setTestNow('2026-04-28 14:36:00');
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:00');
        Carbon::setTestNow('2026-04-28 14:36:59');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('pending', TaskAssignmentReminder::first()->status);
        $this->assertSame(0, Notification::count());
    }

    public function test_handles_end_at_with_non_zero_seconds(): void
    {
        Carbon::setTestNow('2026-04-28 14:38:00');
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:42');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
        $this->assertSame(1, Notification::count());
    }

    public function test_remind_at_stored_with_full_datetime_precision(): void
    {
        Carbon::setTestNow('2026-04-28 14:00:00');
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 30, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:42');

        $reminder = TaskAssignmentReminder::first();
        $this->assertSame(
            '2026-04-28 15:07:42',
            $reminder->remind_at->format('Y-m-d H:i:s'),
            'remind_at must preserve seconds (end_at - 30min)'
        );
    }

    public function test_fires_before_on_after_in_single_run(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->enableEvent('reminder_on', []);
        $this->enableEvent('reminder_after', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->addReminderSchedule('reminder_on', 'on', null, ['mail']);
        $this->addReminderSchedule('reminder_after', 'after', 60, ['mail']);
        // end_at = now - 90min → before(end-60) = 150min ago, on = 90min ago, after(end+60) = 30min ago — all 3 due
        $this->makeItemWithAssignee(endAt: now()->subMinutes(90)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(3, TaskAssignmentReminder::where('status', 'fired')->count());
        $this->assertSame(3, Notification::count());

        $eventKeys = Notification::pluck('event_key')->sort()->values()->all();
        $this->assertSame(['reminder_after', 'reminder_before', 'reminder_on'], $eventKeys);
    }

    public function test_fires_for_each_assigned_user(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithMultipleAssignees(3, endAt: now()->addMinutes(30)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(3, Notification::count());
        $this->assertSame(3, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 3);
    }

    public function test_fires_for_each_channel_in_schedule(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail', 'sms', 'zalo']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::count());
        $this->assertSame(3, NotificationDelivery::count());
        $channels = NotificationDelivery::pluck('channel')->sort()->values()->all();
        $this->assertSame(['mail', 'sms', 'zalo'], $channels);
        Queue::assertPushed(SendDeliveryJob::class, 3);
    }

    public function test_processes_more_than_100_reminders_in_chunks(): void
    {
        Queue::fake();
        $this->seedDuePendingReminders(150);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(150, TaskAssignmentReminder::where('status', 'fired')->count());
        $this->assertSame(0, TaskAssignmentReminder::where('status', 'pending')->count());
    }

    public function test_does_not_refire_already_fired_reminder(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Mark existing reminder as already fired
        TaskAssignmentReminder::query()->update([
            'status' => 'fired',
            'fired_at' => now()->subMinutes(5),
        ]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        Queue::assertNotPushed(SendDeliveryJob::class);
    }

    public function test_cancels_when_item_status_is_cancelled(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());
        $item->update(['processing_status' => 'cancelled']);
        TaskAssignmentReminder::query()->update(['status' => 'pending']); // Override observer cancel

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_document_has_no_organization(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Set organization_id to NULL — cast to int gives 0, triggering the cancel branch.
        // Cannot use 0 directly: FK constraint requires a valid organizations.id or NULL.
        DB::table('task_assignment_documents')
            ->where('id', $item->task_assignment_document_id)
            ->update(['organization_id' => null]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_item_hard_deleted(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Hard-delete bypassing observer. The reminder FK cascades, so the reminder row
        // is also deleted — the command never sees it, and no notification is created.
        DB::table('task_assignment_items')->where('id', $item->id)->delete();

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame(0, TaskAssignmentReminder::count()); // cascade-deleted with item
    }

    public function test_cancels_when_schedule_channels_empty(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Empty channels after observer creates reminder
        $schedule->update(['channels' => []]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }

    public function test_cancels_when_schedule_belongs_to_other_organization(): void
    {
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $schedule = $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: now()->addMinutes(30)->toDateTimeString());

        // Move schedule's parent event_config to a different org
        $otherOrg = \App\Modules\Core\Models\Organization::create([
            'slug' => 'other', 'name' => 'Other', 'status' => 'active',
        ]);
        \App\Modules\Core\Models\NotificationEventConfig::where('id', $schedule->notification_event_config_id)
            ->update(['organization_id' => $otherOrg->id]);

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::count());
        $this->assertSame('cancelled', TaskAssignmentReminder::first()->status);
    }
}
