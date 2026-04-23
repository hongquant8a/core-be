<?php

namespace Tests\Feature\TaskAssignment;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTimingStatusTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::first();
        setPermissionsTeamId($this->org->id);
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);
    }

    private function makeReport(array $itemOverrides, ?string $reportCompletedAt): TaskAssignmentItemReport
    {
        $doc = TaskAssignmentDocument::create([
            'name' => 'D', 'status' => 'issued', 'organization_id' => $this->org->id,
        ]);
        $item = TaskAssignmentItem::create(array_merge([
            'task_assignment_document_id' => $doc->id,
            'name' => 'T',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->subDay(),
            'organization_id' => $this->org->id,
        ], $itemOverrides));

        return TaskAssignmentItemReport::create([
            'task_assignment_item_id' => $item->id,
            'completed_at' => $reportCompletedAt,
            'report_document_content' => 'Xong',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_timing_status_on_time_when_completed_before_deadline(): void
    {
        $report = $this->makeReport(
            ['end_at' => now()->addDay()],
            now()->format('Y-m-d H:i:s'),
        );

        $res = $this->getJson("/api/task-assignment-item-reports?task_assignment_item_id={$report->task_assignment_item_id}",
            ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $this->assertSame('on_time', $res->json('data.0.timing_status'));
    }

    public function test_timing_status_late_when_completed_after_deadline(): void
    {
        $report = $this->makeReport(
            ['end_at' => now()->subDays(5)],
            now()->subDay()->format('Y-m-d H:i:s'),
        );

        $res = $this->getJson("/api/task-assignment-item-reports?task_assignment_item_id={$report->task_assignment_item_id}",
            ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $this->assertSame('late', $res->json('data.0.timing_status'));
    }

    public function test_timing_status_null_when_no_deadline_or_no_completed_at(): void
    {
        // Task no_deadline
        $report1 = $this->makeReport(
            ['deadline_type' => 'no_deadline', 'end_at' => null],
            now()->format('Y-m-d H:i:s'),
        );
        $res1 = $this->getJson("/api/task-assignment-item-reports?task_assignment_item_id={$report1->task_assignment_item_id}",
            ['X-Organization-Id' => $this->org->id]);
        $this->assertNull($res1->json('data.0.timing_status'));

        // Report chưa có completed_at
        $report2 = $this->makeReport(['end_at' => now()->addDay()], null);
        $res2 = $this->getJson("/api/task-assignment-item-reports?task_assignment_item_id={$report2->task_assignment_item_id}",
            ['X-Organization-Id' => $this->org->id]);
        $this->assertNull($res2->json('data.0.timing_status'));
    }
}
