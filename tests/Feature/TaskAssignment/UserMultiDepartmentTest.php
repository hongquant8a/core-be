<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use App\Modules\TaskAssignment\Services\TaskAssignmentDepartmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMultiDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private TaskAssignmentDepartmentService $service;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->service = app(TaskAssignmentDepartmentService::class);
    }

    public function test_user_can_belong_to_multiple_departments(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['name' => 'A', 'organization_id' => $this->org->id]);
        $deptB = TaskAssignmentDepartment::create(['name' => 'B', 'organization_id' => $this->org->id]);

        $this->service->syncUsers($deptA, [$user->id]);
        $this->service->syncUsers($deptB, [$user->id]);

        $this->assertSame(2, TaskAssignmentUser::where('user_id', $user->id)->where('organization_id', $this->org->id)->count());
    }

    public function test_first_attachment_becomes_primary_automatically(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['name' => 'A', 'organization_id' => $this->org->id]);

        $this->service->syncUsers($deptA, [$user->id]);

        $record = TaskAssignmentUser::where('user_id', $user->id)->first();
        $this->assertTrue((bool) $record->is_primary);
    }

    public function test_set_primary_swaps_flag(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['name' => 'A', 'organization_id' => $this->org->id]);
        $deptB = TaskAssignmentDepartment::create(['name' => 'B', 'organization_id' => $this->org->id]);
        $this->service->syncUsers($deptA, [$user->id]);
        $this->service->syncUsers($deptB, [$user->id]);

        $this->service->setPrimary($user->id, $deptB->id);

        $recA = TaskAssignmentUser::where('user_id', $user->id)->where('task_assignment_department_id', $deptA->id)->first();
        $recB = TaskAssignmentUser::where('user_id', $user->id)->where('task_assignment_department_id', $deptB->id)->first();
        $this->assertFalse((bool) $recA->is_primary);
        $this->assertTrue((bool) $recB->is_primary);
    }

    public function test_remove_primary_promotes_another(): void
    {
        $user = User::factory()->create();
        $deptA = TaskAssignmentDepartment::create(['name' => 'A', 'organization_id' => $this->org->id]);
        $deptB = TaskAssignmentDepartment::create(['name' => 'B', 'organization_id' => $this->org->id]);
        $this->service->syncUsers($deptA, [$user->id]);
        $this->service->syncUsers($deptB, [$user->id]);

        $this->service->removeUser($deptA, $user->id);

        $recB = TaskAssignmentUser::where('user_id', $user->id)->where('task_assignment_department_id', $deptB->id)->first();
        $this->assertTrue((bool) $recB->is_primary);
    }
}
