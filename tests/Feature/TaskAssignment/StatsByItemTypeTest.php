<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatsByItemTypeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private TaskAssignmentDocument $doc;
    private TaskAssignmentItemType $itemType;

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
        $this->itemType = TaskAssignmentItemType::create([
            'name' => 'Loại CV Test', 'status' => 'active', 'organization_id' => $this->org->id,
        ]);
    }

    private function makeTask(array $overrides = []): TaskAssignmentItem
    {
        return TaskAssignmentItem::create(array_merge([
            'task_assignment_document_id' => $this->doc->id,
            'task_assignment_item_type_id' => $this->itemType->id,
            'name' => 'Task',
            'processing_status' => 'todo',
            'deadline_type' => 'no_deadline',
            'end_at' => null,
            'completed_at' => null,
            'organization_id' => $this->org->id,
        ], $overrides));
    }

    // ── statsByItemType ───────────────────────────────────────────────────────────

    public function test_stats_by_item_type_returns_all_item_types_even_with_zero_tasks(): void
    {
        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame($this->itemType->id, $res->json('data.0.item_type_id'));
        $this->assertSame(0, $res->json('data.0.total'));
        $this->assertSame(0, $res->json('data.0.todo'));
        $this->assertSame(0, $res->json('data.0.done'));
        $this->assertSame(0, $res->json('data.0.timing_stats.upcoming'));
        $this->assertSame(0, $res->json('data.0.timing_stats.overdue'));
    }

    public function test_stats_by_item_type_counts_status_buckets_correctly(): void
    {
        $this->makeTask(['processing_status' => 'todo']);
        $this->makeTask(['processing_status' => 'in_progress']);
        $this->makeTask(['processing_status' => 'done', 'completed_at' => now()]);
        $this->makeTask(['processing_status' => 'paused']);
        $this->makeTask(['processing_status' => 'cancelled']);

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $row = $res->json('data.0');
        $this->assertSame(5, $row['total']);
        $this->assertSame(1, $row['todo']);
        $this->assertSame(1, $row['in_progress']);
        $this->assertSame(1, $row['done']);
        $this->assertSame(1, $row['paused']);
        $this->assertSame(1, $row['cancelled']);
    }

    public function test_stats_by_item_type_timing_upcoming(): void
    {
        // up coming: active status, no deadline or end_at >= now
        $this->makeTask(['processing_status' => 'todo', 'deadline_type' => 'no_deadline', 'end_at' => null]);
        $this->makeTask(['processing_status' => 'in_progress', 'deadline_type' => 'has_deadline', 'end_at' => now()->addDay()]);

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertSame(2, $res->json('data.0.timing_stats.upcoming'));
        $this->assertSame(0, $res->json('data.0.timing_stats.overdue'));
    }

    public function test_stats_by_item_type_timing_overdue(): void
    {
        // overdue: active, has_deadline, end_at < now
        $this->makeTask(['processing_status' => 'todo', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDay()]);
        $this->makeTask(['processing_status' => 'in_progress', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDays(2)]);

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertSame(2, $res->json('data.0.timing_stats.overdue'));
        $this->assertSame(0, $res->json('data.0.timing_stats.upcoming'));
    }

    public function test_stats_by_item_type_timing_late(): void
    {
        // late: done, has_deadline, completed_at > end_at
        $this->makeTask([
            'processing_status' => 'done',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->subDays(5),
            'completed_at' => now()->subDay(),
        ]);

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertSame(1, $res->json('data.0.timing_stats.late'));
        $this->assertSame(0, $res->json('data.0.timing_stats.early'));
        $this->assertSame(0, $res->json('data.0.timing_stats.on_time'));
    }

    public function test_stats_by_item_type_timing_early(): void
    {
        // early: done, has_deadline, DATE(completed_at) < DATE(end_at)
        $this->makeTask([
            'processing_status' => 'done',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->addDays(5),
            'completed_at' => now()->addDay(),
        ]);

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertSame(1, $res->json('data.0.timing_stats.early'));
        $this->assertSame(0, $res->json('data.0.timing_stats.late'));
        $this->assertSame(0, $res->json('data.0.timing_stats.on_time'));
    }

    public function test_stats_by_item_type_timing_on_time(): void
    {
        // on_time: done, same day
        $this->makeTask([
            'processing_status' => 'done',
            'deadline_type' => 'has_deadline',
            'end_at' => now(),
            'completed_at' => now(),
        ]);
        // on_time: done, no deadline
        $this->makeTask([
            'processing_status' => 'done',
            'deadline_type' => 'no_deadline',
            'end_at' => null,
            'completed_at' => now(),
        ]);

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $this->assertSame(2, $res->json('data.0.timing_stats.on_time'));
    }

    public function test_stats_by_item_type_comprehensive(): void
    {
        // Tạo đủ loại task để verify tất cả bucket = total
        $this->makeTask(['processing_status' => 'todo', 'deadline_type' => 'no_deadline', 'end_at' => null]);          // upcoming
        $this->makeTask(['processing_status' => 'todo', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDay()]); // overdue
        $this->makeTask(['processing_status' => 'done', 'deadline_type' => 'has_deadline', 'end_at' => now()->subDays(3), 'completed_at' => now()]); // late
        $this->makeTask(['processing_status' => 'done', 'deadline_type' => 'has_deadline', 'end_at' => now()->addDays(3), 'completed_at' => now()]); // early
        $this->makeTask(['processing_status' => 'done', 'deadline_type' => 'no_deadline', 'end_at' => null, 'completed_at' => now()]); // on_time
        $this->makeTask(['processing_status' => 'cancelled']); // cancelled

        $res = $this->getJson('/api/task-assignment-items/stats-by-item-type', [
            'X-Organization-Id' => $this->org->id,
        ]);

        $res->assertOk();
        $row = $res->json('data.0');
        $this->assertSame(6, $row['total']);
        $ts = $row['timing_stats'];
        $this->assertSame(1, $ts['upcoming']);
        $this->assertSame(1, $ts['overdue']);
        $this->assertSame(1, $ts['late']);
        $this->assertSame(1, $ts['early']);
        $this->assertSame(1, $ts['on_time']);
        $this->assertSame(1, $ts['cancelled']);
    }
}
