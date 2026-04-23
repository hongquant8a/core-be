<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Events\TaskCompleted;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class TaskCompletedFlowTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeItemWithManager(User $manager): TaskAssignmentItem
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

        return TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => 'no_deadline',
            'processing_status' => 'in_progress',
            'completion_percent' => 100,
            'assigned_by' => $manager->id,
        ]);
    }

    public function test_event_fires_creates_notification_for_manager(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('task_completed', ['sms', 'mail']);
        $manager = User::factory()->create(['email' => 'mgr@test.com']);
        $item = $this->makeItemWithManager($manager);

        event(new TaskCompleted($item));

        // 1 manager × 1 item × 2 channels = 1 notification + 2 deliveries
        $this->assertSame(1, Notification::count());
        $this->assertSame($manager->id, Notification::first()->user_id);
        $this->assertSame(2, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 2);
    }

    public function test_does_nothing_when_event_disabled(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $manager = User::factory()->create();
        $item = $this->makeItemWithManager($manager);

        event(new TaskCompleted($item));

        $this->assertSame(0, Notification::count());
        Queue::assertNothingPushed();
    }

    public function test_does_nothing_when_item_has_no_manager(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('task_completed', ['mail']);
        // Create item without assigned_by
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
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Orphan',
            'priority' => 'normal',
            'deadline_type' => 'no_deadline',
            'processing_status' => 'in_progress',
            'completion_percent' => 100,
            // no assigned_by
        ]);

        event(new TaskCompleted($item));

        $this->assertSame(0, Notification::count());
    }
}
