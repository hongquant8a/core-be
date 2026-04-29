<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\UserService;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserDestroyGuardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->service = app(UserService::class);
    }

    private function makeItemAssignedTo(int $userId, string $itemStatus = 'todo'): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'code' => 'D-'.uniqid(), 'name' => 'D', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $document = TaskAssignmentDocument::create([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'organization_id' => $this->org->id,
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $document->id,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Task',
            'priority' => 'normal',
            'deadline_type' => 'no_deadline',
            'processing_status' => $itemStatus,
            'completion_percent' => 0,
        ]);
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $userId,
            'department_id' => $deptId,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $item;
    }

    public function test_destroy_blocks_when_user_has_active_assignment(): void
    {
        $user = User::factory()->create();
        $this->makeItemAssignedTo($user->id, 'todo');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/đang có công việc chưa hoàn tất/');

        $this->service->destroy($user);
    }

    public function test_destroy_succeeds_when_user_has_no_assignments(): void
    {
        $user = User::factory()->create();

        $this->service->destroy($user);

        $this->assertNull(User::find($user->id));
    }

    public function test_destroy_succeeds_when_user_assignments_all_on_done_items(): void
    {
        $user = User::factory()->create();
        $this->makeItemAssignedTo($user->id, 'done');

        $this->service->destroy($user);

        $this->assertNull(User::find($user->id));
    }

    public function test_destroy_succeeds_when_user_assignments_all_on_cancelled_items(): void
    {
        $user = User::factory()->create();
        $this->makeItemAssignedTo($user->id, 'cancelled');

        $this->service->destroy($user);

        $this->assertNull(User::find($user->id));
    }

    public function test_bulk_destroy_blocks_if_any_user_has_active_assignment(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->makeItemAssignedTo($u2->id, 'in_progress');

        $this->expectException(ValidationException::class);

        $this->service->bulkDestroy([$u1->id, $u2->id]);
        // Both should remain (transaction-like effect: validation fails before delete)
        $this->assertNotNull(User::find($u1->id));
        $this->assertNotNull(User::find($u2->id));
    }

    public function test_bulk_destroy_succeeds_when_no_user_has_active_assignment(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->service->bulkDestroy([$u1->id, $u2->id]);

        $this->assertNull(User::find($u1->id));
        $this->assertNull(User::find($u2->id));
    }
}
