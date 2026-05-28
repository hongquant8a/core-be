<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskAssignmentDocumentStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\Events\TaskAssigned;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class TaskAssignedFlowTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeIssuedDocWithItem(?string $endAt = null): array
    {
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
        $document = TaskAssignmentDocument::create([
            'task_assignment_type_id' => $typeId,
            'name' => 'Doc',
            'status' => TaskAssignmentDocumentStatusEnum::Issued->value,
            'issued_at' => now(),
            'organization_id' => $this->resolveTestOrganization()->id,
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $document->id,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => 'todo',
            'completion_percent' => 0,
        ]);

        return [$document, $item, $deptId];
    }

    public function test_event_fires_creates_notification_and_delivery_for_assigned_user(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        $this->enableEvent('task_assigned', ['mail']);

        [$document, $item] = $this->makeIssuedDocWithItem();
        $user = User::factory()->create();

        event(new TaskAssigned($item, $user));

        $this->assertSame(1, Notification::count());
        $this->assertSame($user->id, Notification::first()->user_id);
        $this->assertSame('task_assigned', Notification::first()->event_key);
        $this->assertSame(1, NotificationDelivery::count());
        Queue::assertPushed(SendDeliveryJob::class, 1);
    }

    public function test_does_nothing_when_event_config_disabled(): void
    {
        Queue::fake([SendDeliveryJob::class]);
        // Don't enable — defaults to disabled
        [$document, $item] = $this->makeIssuedDocWithItem();
        $user = User::factory()->create();

        event(new TaskAssigned($item, $user));

        $this->assertSame(0, Notification::count());
        Queue::assertNothingPushed();
    }

    public function test_item_service_fires_event_only_when_doc_is_issued(): void
    {
        Event::fake([TaskAssigned::class]);
        $this->enableEvent('task_assigned', ['mail']);

        // Doc draft → fire NOT expected
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
        $draftDoc = TaskAssignmentDocument::create([
            'task_assignment_type_id' => $typeId,
            'name' => 'Draft',
            'status' => 'draft',
            'organization_id' => $this->resolveTestOrganization()->id,
        ]);

        $user = User::factory()->create();
        $itemSvc = app(\App\Modules\TaskAssignment\Services\TaskAssignmentItemService::class);
        $itemSvc->store([
            'task_assignment_document_id' => $draftDoc->id,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task in draft',
            'priority' => 'normal',
            'deadline_type' => 'no_deadline',
            'processing_status' => 'todo',
            'completion_percent' => 0,
            'users' => [
                ['user_id' => $user->id, 'department_id' => $deptId, 'department_role' => 'main', 'assignment_role' => 'main'],
            ],
        ]);

        Event::assertNotDispatched(TaskAssigned::class);

        // Doc issued → fire expected
        $issuedDoc = TaskAssignmentDocument::create([
            'task_assignment_type_id' => $typeId,
            'name' => 'Issued',
            'status' => TaskAssignmentDocumentStatusEnum::Issued->value,
            'issued_at' => now(),
            'organization_id' => $this->resolveTestOrganization()->id,
        ]);
        $user2 = User::factory()->create();
        $itemSvc->store([
            'task_assignment_document_id' => $issuedDoc->id,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task in issued',
            'priority' => 'normal',
            'deadline_type' => 'no_deadline',
            'processing_status' => 'todo',
            'completion_percent' => 0,
            'users' => [
                ['user_id' => $user2->id, 'department_id' => $deptId, 'department_role' => 'main', 'assignment_role' => 'main'],
            ],
        ]);

        Event::assertDispatched(TaskAssigned::class, fn ($e) => $e->user->id === $user2->id);
        Event::assertDispatchedTimes(TaskAssigned::class, 1);
    }

    public function test_update_fires_event_only_for_newly_added_users(): void
    {
        Event::fake([TaskAssigned::class]);
        $this->enableEvent('task_assigned', ['mail']);

        [$document, $item, $deptId] = $this->makeIssuedDocWithItem();
        $existing = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $existing->id,
            'department_id' => $deptId,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $newUser = User::factory()->create();
        $itemSvc = app(\App\Modules\TaskAssignment\Services\TaskAssignmentItemService::class);
        $itemSvc->update($item, [
            'users' => [
                ['user_id' => $existing->id, 'department_id' => $deptId, 'department_role' => 'main', 'assignment_role' => 'main'],
                ['user_id' => $newUser->id, 'department_id' => $deptId, 'department_role' => 'main', 'assignment_role' => 'cooperate'],
            ],
        ]);

        // Only the new user triggers event — existing user already in pivot
        Event::assertDispatched(TaskAssigned::class, fn ($e) => $e->user->id === $newUser->id);
        Event::assertDispatchedTimes(TaskAssigned::class, 1);
    }

    public function test_transfer_fires_event_for_new_assignee(): void
    {
        Event::fake([TaskAssigned::class]);
        $this->enableEvent('task_assigned', ['mail']);

        [$document, $item, $deptId] = $this->makeIssuedDocWithItem();
        $fromUser = User::factory()->create();
        $toUser = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $fromUser->id,
            'department_id' => $deptId,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($fromUser);
        $transferSvc = app(\App\Modules\TaskAssignment\Services\TaskAssignmentTransferService::class);
        $transferSvc->transfer($item, ['to_user_id' => $toUser->id]);

        Event::assertDispatched(TaskAssigned::class, fn ($e) => $e->user->id === $toUser->id);
        Event::assertDispatchedTimes(TaskAssigned::class, 1);
    }

    public function test_revert_document_cancels_pending_reminders_and_deliveries(): void
    {
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->enableEvent('task_assigned', ['mail']);

        $deadline = now()->addMinutes(30)->toDateTimeString();
        [$document, $item, $deptId] = $this->makeIssuedDocWithItem($deadline);
        $user = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $user->id,
            'department_id' => $deptId,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Item observer creates reminder when end_at set; verify present
        $item->touch(); // re-trigger observer to ensure reminder exists
        $this->assertSame(1, \App\Modules\TaskAssignment\Models\TaskAssignmentReminder::where('task_assignment_item_id', $item->id)->count());

        // Create a pending Notification + Delivery row simulating an in-flight job
        $notification = \App\Modules\Core\Models\Notification::create([
            'user_id' => $user->id,
            'organization_id' => $this->resolveTestOrganization()->id,
            'event_key' => 'task_assigned',
            'notifiable_type' => \App\Modules\TaskAssignment\Models\TaskAssignmentItem::class,
            'notifiable_id' => $item->id,
            'title' => 'x',
            'body' => 'x',
        ]);
        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'mail',
            'status' => 'pending',
        ]);

        // Revert
        $docSvc = app(\App\Modules\TaskAssignment\Services\TaskAssignmentDocumentService::class);
        $docSvc->changeStatus($document, 'draft');

        // Reminders cancelled + delivery skipped
        $this->assertSame(
            'cancelled',
            \App\Modules\TaskAssignment\Models\TaskAssignmentReminder::where('task_assignment_item_id', $item->id)->first()->status
        );
        $this->assertSame('skipped', $delivery->fresh()->status);
        $this->assertSame('Document reverted to draft', $delivery->fresh()->error_message);
    }

    public function test_destroy_document_cleans_up_orphan_notifications_and_deliveries(): void
    {
        [$document, $item, $deptId] = $this->makeIssuedDocWithItem();
        $user = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $user->id,
            'department_id' => $deptId,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Tạo Notification + Delivery trỏ đến item
        $notification = \App\Modules\Core\Models\Notification::create([
            'user_id' => $user->id,
            'organization_id' => $this->resolveTestOrganization()->id,
            'event_key' => 'task_assigned',
            'notifiable_type' => \App\Modules\TaskAssignment\Models\TaskAssignmentItem::class,
            'notifiable_id' => $item->id,
            'title' => 'x',
            'body' => 'x',
        ]);
        NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'mail',
            'status' => 'pending',
        ]);

        $this->assertSame(1, \App\Modules\Core\Models\Notification::count());
        $this->assertSame(1, NotificationDelivery::count());

        $docSvc = app(\App\Modules\TaskAssignment\Services\TaskAssignmentDocumentService::class);
        $docSvc->destroy($document);

        // Notification + Delivery (cascade FK) bị xóa
        $this->assertSame(0, \App\Modules\Core\Models\Notification::count());
        $this->assertSame(0, NotificationDelivery::count());
    }

    public function test_re_issue_recreates_reminders(): void
    {
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);

        $deadline = now()->addMinutes(30)->toDateTimeString();
        [$document, $item, $deptId] = $this->makeIssuedDocWithItem($deadline);
        $user = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $user->id,
            'department_id' => $deptId,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item->touch();
        $this->assertSame(1, \App\Modules\TaskAssignment\Models\TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->count());

        $docSvc = app(\App\Modules\TaskAssignment\Services\TaskAssignmentDocumentService::class);
        // Revert → reminders cancelled
        $docSvc->changeStatus($document, 'draft');
        $this->assertSame(0, \App\Modules\TaskAssignment\Models\TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->count());

        // Re-issue → reminders re-created
        $docSvc->changeStatus($document->fresh(), 'issued');
        $this->assertSame(1, \App\Modules\TaskAssignment\Models\TaskAssignmentReminder::where('task_assignment_item_id', $item->id)
            ->where('status', 'pending')
            ->count());
    }
}
