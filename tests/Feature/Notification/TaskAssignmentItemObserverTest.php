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

    /**
     * Create via Eloquent so observer fires.
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
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
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
        $this->assertSame(0, TaskAssignmentReminder::count());
    }
}
