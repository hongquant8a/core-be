<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OverdueFilterTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private TaskAssignmentDocument $doc;

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
            'name' => 'D', 'status' => 'issued', 'organization_id' => $this->org->id,
        ]);
    }

    private function makeTask(array $overrides = []): TaskAssignmentItem
    {
        return TaskAssignmentItem::create(array_merge([
            'task_assignment_document_id' => $this->doc->id,
            'name' => 'T',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->addDay(),
            'processing_status' => 'in_progress',
            'organization_id' => $this->org->id,
        ], $overrides));
    }

    public function test_filter_is_overdue_returns_only_late_active_tasks(): void
    {
        $overdueTask = $this->makeTask(['end_at' => now()->subDay(), 'processing_status' => 'in_progress']);
        $onTimeTask = $this->makeTask(['end_at' => now()->addDay(), 'processing_status' => 'in_progress']);
        $doneTask = $this->makeTask(['end_at' => now()->subDay(), 'processing_status' => 'done']);

        $res = $this->getJson('/api/task-assignment-items?is_overdue=1', ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($overdueTask->id, $ids);
        $this->assertNotContains($onTimeTask->id, $ids);
        $this->assertNotContains($doneTask->id, $ids);
    }

    public function test_resource_exposes_is_overdue_flag(): void
    {
        $task = $this->makeTask(['end_at' => now()->subDay(), 'processing_status' => 'in_progress']);

        $res = $this->getJson("/api/task-assignment-items/{$task->id}", ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $res->assertJsonPath('data.is_overdue', true);
    }

    public function test_filter_processing_status_overdue_matches_stats_count(): void
    {
        $overdue = $this->makeTask(['end_at' => now()->subDay(), 'processing_status' => 'in_progress']);
        $onTime = $this->makeTask(['end_at' => now()->addDay(), 'processing_status' => 'in_progress']);
        $done = $this->makeTask(['end_at' => now()->subDay(), 'processing_status' => 'done']);

        // Backward-compat: ?processing_status=overdue mapped to is_overdue predicate.
        $res = $this->getJson('/api/task-assignment-items?processing_status=overdue', ['X-Organization-Id' => $this->org->id]);
        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($overdue->id, $ids);
        $this->assertNotContains($onTime->id, $ids);
        $this->assertNotContains($done->id, $ids);
    }
}
