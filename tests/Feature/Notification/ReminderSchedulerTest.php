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
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'organization_id' => $this->resolveTestOrganization()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = DB::table('task_assignment_items')->insertGetId([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => $status,
            'completion_percent' => 0,
            'organization_id' => $this->resolveTestOrganization()->id,
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

    public function test_remind_at_on_moment_preserves_seconds(): void
    {
        $this->addReminderSchedule('reminder_on', 'on', null, ['mail']);
        $deadline = '2026-04-28 15:37:42';
        $item = $this->makeItem(endAt: $deadline);

        app(ReminderScheduler::class)->scheduleFor($item);

        $reminder = TaskAssignmentReminder::where('moment', 'on')->first();
        $this->assertNotNull($reminder);
        $this->assertSame(
            '2026-04-28 15:37:42',
            $reminder->remind_at->format('Y-m-d H:i:s'),
            'on-moment must equal end_at exactly, including seconds'
        );
    }

    public function test_remind_at_after_moment_preserves_seconds(): void
    {
        $this->addReminderSchedule('reminder_after', 'after', 60, ['mail']);
        $deadline = '2026-04-28 15:37:42';
        $item = $this->makeItem(endAt: $deadline);

        app(ReminderScheduler::class)->scheduleFor($item);

        $reminder = TaskAssignmentReminder::where('moment', 'after')->first();
        $this->assertNotNull($reminder);
        $this->assertSame(
            '2026-04-28 16:37:42',
            $reminder->remind_at->format('Y-m-d H:i:s'),
            'after-moment must be end_at + offset minutes, with seconds preserved'
        );
    }

    public function test_reschedule_preserves_seconds_after_end_at_change(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 30, ['mail']);
        $item = $this->makeItem(endAt: '2026-04-28 15:37:42');
        $scheduler = app(ReminderScheduler::class);
        $scheduler->scheduleFor($item);

        $this->assertSame('2026-04-28 15:07:42', TaskAssignmentReminder::first()->remind_at->format('Y-m-d H:i:s'));

        // Change end_at to a different time-of-day with different seconds
        DB::table('task_assignment_items')->where('id', $item->id)->update(['end_at' => '2026-04-29 16:42:13']);
        $item->refresh();
        $scheduler->scheduleFor($item);

        $this->assertSame(1, TaskAssignmentReminder::count());
        $this->assertSame(
            '2026-04-29 16:12:13',
            TaskAssignmentReminder::first()->remind_at->format('Y-m-d H:i:s'),
            'reschedule must recompute with new end_at, preserving its seconds'
        );
    }

    public function test_creates_multiple_reminders_for_same_event_with_different_offsets(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->addReminderSchedule('reminder_before', 'before', 30, ['sms']);
        $deadline = '2026-04-28 15:37:42';
        $item = $this->makeItem(endAt: $deadline);

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(2, TaskAssignmentReminder::where('moment', 'before')->count());
        $remindAts = TaskAssignmentReminder::where('moment', 'before')
            ->orderBy('remind_at')
            ->pluck('remind_at')
            ->map(fn ($t) => $t->format('Y-m-d H:i:s'))
            ->all();
        $this->assertSame(['2026-04-28 14:37:42', '2026-04-28 15:07:42'], $remindAts);
    }

    public function test_skips_before_schedule_with_null_offset(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', null, ['mail']);
        $item = $this->makeItem(endAt: '2026-04-28 15:37:42');

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(0, TaskAssignmentReminder::count());
    }

    public function test_skips_after_schedule_with_null_offset(): void
    {
        $this->addReminderSchedule('reminder_after', 'after', null, ['mail']);
        $item = $this->makeItem(endAt: '2026-04-28 15:37:42');

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(0, TaskAssignmentReminder::count());
    }

    public function test_no_reminders_when_item_status_cancelled(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItem(status: 'cancelled', endAt: '2026-04-28 15:37:42');

        app(ReminderScheduler::class)->scheduleFor($item);

        $this->assertSame(0, TaskAssignmentReminder::count());
    }

    public function test_no_reminders_when_document_has_no_organization(): void
    {
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItem(endAt: '2026-04-28 15:37:42');
        // Null out the document's organization (FK allows null; scheduler treats as 0)
        DB::table('task_assignment_documents')
            ->where('id', $item->task_assignment_document_id)
            ->update(['organization_id' => null]);

        app(ReminderScheduler::class)->scheduleFor($item->fresh(['document']));

        $this->assertSame(0, TaskAssignmentReminder::count());
    }

    public function test_creates_reminders_even_when_event_config_disabled(): void
    {
        // seedNotificationConfig creates configs with enabled=false by default.
        // Don't enable. Just attach a schedule.
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $item = $this->makeItem(endAt: '2026-04-28 15:37:42');

        app(ReminderScheduler::class)->scheduleFor($item);

        // Documented behavior: scheduler ignores enabled flag.
        // The fire-time command (ProcessRemindersCommand) is the one that filters.
        $this->assertSame(1, TaskAssignmentReminder::count());
        $this->assertSame('pending', TaskAssignmentReminder::first()->status);
    }
}
