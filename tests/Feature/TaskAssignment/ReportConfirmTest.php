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

class ReportConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private function setupUser(): User
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::first();
        setPermissionsTeamId($this->org->id);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        Sanctum::actingAs($user);

        return $user;
    }

    private function makeReport(): TaskAssignmentItemReport
    {
        $doc = TaskAssignmentDocument::create([
            'name' => 'D', 'status' => 'draft', 'organization_id' => $this->org->id,
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $doc->id, 'name' => 'Item',
            'deadline_type' => 'no_deadline', 'organization_id' => $this->org->id,
        ]);

        return TaskAssignmentItemReport::create([
            'task_assignment_item_id' => $item->id,
            'report_document_content' => 'Xong',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_confirm_sets_manager_confirmed_and_locks(): void
    {
        $this->setupUser();
        $report = $this->makeReport();

        $res = $this->patchJson("/api/task-assignment-item-reports/{$report->id}/confirm", [
            'confirm_note' => 'Đạt quy định',
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
        $report->refresh();
        $this->assertTrue((bool) $report->manager_confirmed);
        $this->assertTrue((bool) $report->is_locked);
        $this->assertSame('Đạt quy định', $report->manager_confirm_note);
        $this->assertNotNull($report->manager_confirmed_at);
        $this->assertNotNull($report->locked_at);
    }

    public function test_confirm_rejects_when_already_locked(): void
    {
        $this->setupUser();
        $report = $this->makeReport();
        $report->update(['is_locked' => true, 'locked_at' => now()]);

        $res = $this->patchJson(
            "/api/task-assignment-item-reports/{$report->id}/confirm",
            [],
            ['X-Organization-Id' => $this->org->id]
        );

        $res->assertStatus(422);
    }

    public function test_update_rejects_when_locked(): void
    {
        $this->setupUser();
        $report = $this->makeReport();
        $report->update(['is_locked' => true, 'locked_at' => now()]);

        $res = $this->patchJson("/api/task-assignment-item-reports/{$report->id}", [
            'report_document_content' => 'Sửa lại',
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertStatus(422);
    }
}
