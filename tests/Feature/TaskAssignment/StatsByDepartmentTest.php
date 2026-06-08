<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatsByDepartmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private TaskAssignmentDocument $doc;
    private TaskAssignmentDepartment $deptA;
    private TaskAssignmentDepartment $deptB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::first();
        setPermissionsTeamId($this->org->id);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        $this->doc = TaskAssignmentDocument::create([
            'name' => 'Test Doc', 'status' => 'issued', 'organization_id' => $this->org->id,
        ]);
        $this->deptA = TaskAssignmentDepartment::create([
            'name' => 'Phòng A', 'status' => 'active', 'organization_id' => $this->org->id,
        ]);
        $this->deptB = TaskAssignmentDepartment::create([
            'name' => 'Phòng B', 'status' => 'active', 'organization_id' => $this->org->id,
        ]);
    }

    private function makeTaskForDept(TaskAssignmentDepartment $dept, array $overrides = []): TaskAssignmentItem
    {
        return TaskAssignmentItem::create(array_merge([
            'task_assignment_document_id' => $this->doc->id,
            'name' => 'Task',
            'processing_status' => 'todo',
            'deadline_type' => 'no_deadline',
            'end_at' => null,
            'completed_at' => null,
            'organization_id' => $this->org->id,
        ], $overrides));
    }

    private function assignUserToDept(TaskAssignmentItem $item, TaskAssignmentDepartment $dept): void
    {
        $user = User::factory()->create();
        $user->assignRole('Nhân viên');
        TaskAssignmentUser::create([
            'user_id' => $user->id,
            'task_assignment_department_id' => $dept->id,
            'status' => 'active',
            'organization_id' => $this->org->id,
        ]);
        $item->users()->attach($user->id, [
            'department_id' => $dept->id,
            'department_role' => 'member',
            'assignment_role' => 'assignee',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }

    // ── statsByDepartment ─────────────────────────────────────────────────────────

    public function test_stats_by_department_returns_all_departments_even_with_zero_tasks(): void
    {
        $res = $this->getJson('/api/task-assignment-items/stats-by-department', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertCount(2, $res->json('data'));

        $deptARow = collect($res->json('data'))->firstWhere('department_id', $this->deptA->id);
        $this->assertNotNull($deptARow);
        $this->assertSame(0, $deptARow['total']);
        $this->assertSame(0, $deptARow['todo']);
        $this->assertSame(0, $deptARow['done']);
        $this->assertSame(0, $deptARow['timing_stats']['upcoming']);
    }

    public function test_stats_by_department_counts_status_buckets(): void
    {
        $t1 = $this->makeTaskForDept($this->deptA, ['processing_status' => 'todo']);
        $this->assignUserToDept($t1, $this->deptA);

        $t2 = $this->makeTaskForDept($this->deptA, ['processing_status' => 'done', 'completed_at' => now()]);
        $this->assignUserToDept($t2, $this->deptA);

        // Dept B: 1 task
        $t3 = $this->makeTaskForDept($this->deptB, ['processing_status' => 'in_progress']);
        $this->assignUserToDept($t3, $this->deptB);

        $res = $this->getJson('/api/task-assignment-items/stats-by-department', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $deptA = collect($res->json('data'))->firstWhere('department_id', $this->deptA->id);
        $deptB = collect($res->json('data'))->firstWhere('department_id', $this->deptB->id);

        $this->assertSame(2, $deptA['total']);
        $this->assertSame(1, $deptA['todo']);
        $this->assertSame(1, $deptA['done']);

        $this->assertSame(1, $deptB['total']);
        $this->assertSame(1, $deptB['in_progress']);
    }

    public function test_stats_by_department_timing_stats(): void
    {
        // Dept A: overdue
        $t1 = $this->makeTaskForDept($this->deptA, [
            'processing_status' => 'todo', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDay(),
        ]);
        $this->assignUserToDept($t1, $this->deptA);

        // Dept B: on_time (done, no deadline)
        $t2 = $this->makeTaskForDept($this->deptB, [
            'processing_status' => 'done', 'deadline_type' => 'no_deadline',
            'end_at' => null, 'completed_at' => now(),
        ]);
        $this->assignUserToDept($t2, $this->deptB);

        $res = $this->getJson('/api/task-assignment-items/stats-by-department', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $deptA = collect($res->json('data'))->firstWhere('department_id', $this->deptA->id);
        $deptB = collect($res->json('data'))->firstWhere('department_id', $this->deptB->id);

        $this->assertSame(1, $deptA['timing_stats']['overdue']);
        $this->assertSame(0, $deptA['timing_stats']['upcoming']);
        $this->assertSame(0, $deptA['timing_stats']['on_time']);

        $this->assertSame(1, $deptB['timing_stats']['on_time']);
        $this->assertSame(0, $deptB['timing_stats']['overdue']);
    }

    public function test_stats_by_department_respects_from_date_to_date_filter(): void
    {
        $t1 = $this->makeTaskForDept($this->deptA, [
            'processing_status' => 'done', 'completed_at' => now()->subDays(10), 'created_at' => now()->subDays(10),
        ]);
        $this->assignUserToDept($t1, $this->deptA);

        $t2 = $this->makeTaskForDept($this->deptA, [
            'processing_status' => 'todo', 'created_at' => now()->subDays(2),
        ]);
        $this->assignUserToDept($t2, $this->deptA);

        // Filter chỉ trong 5 ngày gần đây
        $res = $this->getJson(
            '/api/task-assignment-items/stats-by-department?from_date=' . now()->subDays(5)->toDateString()
            . '&to_date=' . now()->toDateString(),
            ['X-Organization-Id' => $this->org->id],
        );

        $res->assertOk();
        $deptA = collect($res->json('data'))->firstWhere('department_id', $this->deptA->id);

        $this->assertSame(1, $deptA['total']);
        $this->assertSame(1, $deptA['todo']);
        $this->assertSame(1, $deptA['new_in_period']);
        $this->assertSame(0, $deptA['done_in_period']);
    }

    public function test_stats_by_department_filter_by_department_id(): void
    {
        $t1 = $this->makeTaskForDept($this->deptA, ['processing_status' => 'todo']);
        $this->assignUserToDept($t1, $this->deptA);

        $t2 = $this->makeTaskForDept($this->deptB, ['processing_status' => 'done', 'completed_at' => now()]);
        $this->assignUserToDept($t2, $this->deptB);

        $res = $this->getJson(
            '/api/task-assignment-items/stats-by-department?department_id=' . $this->deptA->id,
            ['X-Organization-Id' => $this->org->id],
        );

        $res->assertOk();
        $this->assertCount(2, $res->json('data')); // vẫn trả về cả 2 dept

        $deptA = collect($res->json('data'))->firstWhere('department_id', $this->deptA->id);
        $deptB = collect($res->json('data'))->firstWhere('department_id', $this->deptB->id);

        $this->assertSame(1, $deptA['total']);
        $this->assertSame(0, $deptB['total']);
    }

    public function test_stats_by_department_timing_comprehensive(): void
    {
        // Dept A: mỗi loại timing 1 task
        $upcoming = $this->makeTaskForDept($this->deptA, ['processing_status' => 'todo', 'deadline_type' => 'no_deadline', 'end_at' => null]);
        $this->assignUserToDept($upcoming, $this->deptA);

        $overdue = $this->makeTaskForDept($this->deptA, ['processing_status' => 'in_progress', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDay()]);
        $this->assignUserToDept($overdue, $this->deptA);

        $late = $this->makeTaskForDept($this->deptA, ['processing_status' => 'done', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDays(3), 'completed_at' => now()]);
        $this->assignUserToDept($late, $this->deptA);

        $early = $this->makeTaskForDept($this->deptA, ['processing_status' => 'done', 'deadline_type' => 'has_deadline', 'end_at' => now()->addDays(3), 'completed_at' => now()]);
        $this->assignUserToDept($early, $this->deptA);

        $onTime = $this->makeTaskForDept($this->deptA, ['processing_status' => 'done', 'deadline_type' => 'no_deadline', 'end_at' => null, 'completed_at' => now()]);
        $this->assignUserToDept($onTime, $this->deptA);

        $cancelled = $this->makeTaskForDept($this->deptA, ['processing_status' => 'cancelled']);
        $this->assignUserToDept($cancelled, $this->deptA);

        $res = $this->getJson('/api/task-assignment-items/stats-by-department', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $deptA = collect($res->json('data'))->firstWhere('department_id', $this->deptA->id);

        $this->assertSame(6, $deptA['total']);
        $ts = $deptA['timing_stats'];
        $this->assertSame(1, $ts['upcoming']);
        $this->assertSame(1, $ts['overdue']);
        $this->assertSame(1, $ts['late']);
        $this->assertSame(1, $ts['early']);
        $this->assertSame(1, $ts['on_time']);
        $this->assertSame(1, $ts['cancelled']);
    }
}
