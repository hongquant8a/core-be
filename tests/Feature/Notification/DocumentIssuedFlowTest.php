<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\Events\DocumentIssued;
use App\Services\Notification\Jobs\SendDeliveryJob;
use App\Services\Notification\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class DocumentIssuedFlowTest extends TestCase
{
    use InteractsWithNotifications;
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
            'name' => 'D', 'code' => 'DEPT-'.uniqid(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued', 'issued_at' => now(),
            'organization_id' => $this->resolveTestOrganization()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
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
                'department_id' => $deptId,
                'department_role' => 'member',
                'assignment_status' => 'assigned',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return TaskAssignmentDocument::with('items.users')->find($docId);
    }

    public function test_event_fires_creates_notifications_and_deliveries_for_all_assignees(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('document_issued', ['sms', 'mail']);
        $document = $this->makeDocumentWithItemAndAssignees(2);

        event(new DocumentIssued($document));

        // 2 assignees × 1 item = 2 notifications, each with 2 channels = 4 deliveries
        $this->assertSame(2, Notification::count());
        $this->assertSame(4, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 4);
    }

    public function test_does_nothing_when_event_config_disabled(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        // Default enabled=false (seed)
        $document = $this->makeDocumentWithItemAndAssignees(2);

        event(new DocumentIssued($document));

        $this->assertSame(0, Notification::count());
        Queue::assertNothingPushed();
    }

    public function test_does_nothing_when_instant_schedule_channels_empty(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('document_issued', []); // empty channels
        $document = $this->makeDocumentWithItemAndAssignees(2);

        event(new DocumentIssued($document));

        $this->assertSame(0, Notification::count());
        Queue::assertNothingPushed();
    }

    /**
     * Full chain: event → listener → dispatcher → job → NotificationService → delivery status updated.
     * Uses sync queue (from phpunit.xml) — jobs run inline. Mock NotificationService
     * to avoid real provider calls but let all other components run for real.
     */
    public function test_real_queue_end_to_end_updates_delivery_status(): void
    {
        // Mock only the final provider-call layer
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->andReturn([new SendResult('mail', true, 'msg-1')]);
        $this->app->instance(NotificationService::class, $svc);

        $this->enableEvent('document_issued', ['mail']);
        $document = $this->makeDocumentWithItemAndAssignees(1);

        // NO Queue::fake() — sync driver runs jobs inline
        event(new DocumentIssued($document));

        // Full chain should have updated delivery status
        $this->assertSame(1, Notification::count());
        $delivery = NotificationDelivery::first();
        $this->assertNotNull($delivery);
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('msg-1', $delivery->message_id);
        $this->assertNotNull($delivery->sent_at);
    }
}
