<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Events\TaskConfirmed;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class TaskConfirmedFlowTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeItemWithAssignees(int $assigneeCount): TaskAssignmentItem
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
            'deadline_type' => 'no_deadline',
            'processing_status' => 'done',
            'completion_percent' => 100,
            'completed_at' => now(),
            'confirmed_at' => now(),
        ]);
        for ($i = 0; $i < $assigneeCount; $i++) {
            $u = User::factory()->create();
            DB::table('task_assignment_item_user')->insert([
                'task_assignment_item_id' => $item->id,
                'user_id' => $u->id,
                'department_id' => $deptId,
                'department_role' => 'member',
                'assignment_status' => 'done',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $item->fresh(['users']);
    }

    public function test_event_fires_creates_notifications_for_all_assignees(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('task_confirmed', ['mail']);
        $item = $this->makeItemWithAssignees(3);

        event(new TaskConfirmed($item));

        // 3 assignees × 1 channel = 3 notifications + 3 deliveries
        $this->assertSame(3, Notification::count());
        $this->assertSame(3, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 3);
    }

    public function test_does_nothing_when_disabled(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $item = $this->makeItemWithAssignees(2);

        event(new TaskConfirmed($item));

        $this->assertSame(0, Notification::count());
    }

    public function test_does_nothing_when_no_assignees(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('task_confirmed', ['mail']);
        $item = $this->makeItemWithAssignees(0);

        event(new TaskConfirmed($item));

        $this->assertSame(0, Notification::count());
    }
}
