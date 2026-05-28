<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use App\Modules\TaskAssignment\Requests\SyncDepartmentUsersRequest;
use App\Modules\TaskAssignment\Services\TaskAssignmentDepartmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentRepresentativeTest extends TestCase
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

    private function makeDept(string $code = 'A'): TaskAssignmentDepartment
    {
        return TaskAssignmentDepartment::create([

            'name' => $code,
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_sync_users_without_representative_does_not_set_any_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->service->syncUsers($dept, [$u1->id, $u2->id]);

        $this->assertSame(0, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->count());
    }

    public function test_sync_users_with_representative_sets_flag_correctly(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        $this->service->syncUsers($dept, [$u1->id, $u2->id, $u3->id], $u2->id);

        $reps = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->pluck('user_id')
            ->all();
        $this->assertSame([$u2->id], $reps);

        $nonReps = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', false)
            ->pluck('user_id')
            ->sort()
            ->values()
            ->all();
        $expected = collect([$u1->id, $u3->id])->sort()->values()->all();
        $this->assertSame($expected, $nonReps);
    }

    public function test_sync_users_with_rep_not_in_user_ids_fails_validation(): void
    {
        $request = new SyncDepartmentUsersRequest();
        $request->merge([
            'user_ids' => [1, 2, 3],
            'representative_user_id' => 99,
        ]);

        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('representative_user_id', $validator->errors()->toArray());
        $this->assertSame(
            'Người đại diện phải nằm trong danh sách thành viên được chọn.',
            $validator->errors()->first('representative_user_id'),
        );
    }

    public function test_sync_users_switching_representative_clears_old_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u1->id);
        $this->assertSame(1, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->where('user_id', $u1->id)
            ->count());

        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        $reps = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->pluck('user_id')
            ->all();
        $this->assertSame([$u2->id], $reps);
    }

    public function test_get_users_includes_is_representative_field(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        setPermissionsTeamId($this->org->id);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        $res = $this->withHeader('X-Organization-Id', (string) $this->org->id)
            ->getJson("/api/task-assignment-departments/{$dept->id}/users");

        $res->assertOk();
        $items = collect($res->json('data'));
        $this->assertCount(2, $items);
        $rep = $items->firstWhere('user_id', $u2->id);
        $non = $items->firstWhere('user_id', $u1->id);
        $this->assertTrue($rep['is_representative']);
        $this->assertFalse($non['is_representative']);
    }

    public function test_remove_user_who_is_rep_leaves_department_without_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        $this->service->removeUser($dept, $u2->id);

        $this->assertSame(0, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->count());
        $this->assertSame(1, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)->count());
    }

    public function test_sync_excluding_current_rep_clears_rep(): void
    {
        $dept = $this->makeDept();
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $this->service->syncUsers($dept, [$u1->id, $u2->id], $u2->id);

        // Re-sync without u2 — u2 row deleted, no rep remains
        $this->service->syncUsers($dept, [$u1->id]);

        $this->assertSame(0, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('is_representative', true)
            ->count());
        $this->assertSame(1, TaskAssignmentUser::where('task_assignment_department_id', $dept->id)->count());
    }

    public function test_rep_flag_isolated_per_organization(): void
    {
        $orgB = Organization::firstOrCreate(['slug' => 'org-b'], ['name' => 'Org B', 'status' => 'active']);

        // Setup org A: dept with rep
        $deptA = $this->makeDept('A');
        $userA = User::factory()->create();
        $this->service->syncUsers($deptA, [$userA->id], $userA->id);

        // Switch to org B and create another dept + user
        setPermissionsTeamId($orgB->id);
        $deptB = TaskAssignmentDepartment::create(['name' => 'B', 'organization_id' => $orgB->id]);
        $userB = User::factory()->create();
        $this->service->syncUsers($deptB, [$userB->id], $userB->id);

        // Verify org B has its own rep (scope is currently orgB)
        $repB = TaskAssignmentUser::where('organization_id', $orgB->id)
            ->where('is_representative', true)
            ->first();
        $this->assertNotNull($repB);
        $this->assertSame($userB->id, $repB->user_id);

        // Verify org A's rep was untouched (restore scope to orgA before querying)
        setPermissionsTeamId($this->org->id);
        $repA = TaskAssignmentUser::where('organization_id', $this->org->id)
            ->where('is_representative', true)
            ->first();
        $this->assertNotNull($repA);
        $this->assertSame($userA->id, $repA->user_id);
    }

    public function test_rep_flag_independent_of_is_primary(): void
    {
        $dept = $this->makeDept();
        $u = User::factory()->create();

        $this->service->syncUsers($dept, [$u->id], $u->id);

        $row = TaskAssignmentUser::where('task_assignment_department_id', $dept->id)
            ->where('user_id', $u->id)
            ->first();
        $this->assertTrue((bool) $row->is_primary, 'first member auto-marked is_primary');
        $this->assertTrue((bool) $row->is_representative, 'rep flag set explicitly');
    }
}
