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

    public function test_submit_report_marks_assignment_done_then_manager_mark_done_closes_task(): void
    {
        $manager = $this->setupUser();

        $reporter = User::factory()->create();
        $reporter->assignRole('Super Admin');
        $dept = \App\Modules\TaskAssignment\Models\TaskAssignmentDepartment::create([
            'code' => 'D1', 'name' => 'D1', 'organization_id' => $this->org->id,
        ]);
        $doc = \App\Modules\TaskAssignment\Models\TaskAssignmentDocument::create([
            'name' => 'Doc', 'status' => 'issued', 'organization_id' => $this->org->id,
        ]);
        $item = \App\Modules\TaskAssignment\Models\TaskAssignmentItem::create([
            'task_assignment_document_id' => $doc->id,
            'name' => 'Item',
            'deadline_type' => 'no_deadline',
            'processing_status' => 'in_progress',
            'organization_id' => $this->org->id,
        ]);
        \DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $reporter->id,
            'department_id' => $dept->id,
            'department_role' => 'main',
            'assignment_role' => 'main',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Submit report → assignment auto 'done'
        \Laravel\Sanctum\Sanctum::actingAs($reporter);
        $res = $this->postJson('/api/task-assignment-item-reports', [
            'task_assignment_item_id' => $item->id,
            'report_document_content' => 'Đã xong',
        ], ['X-Organization-Id' => $this->org->id]);
        $res->assertStatus(201);
        $reportId = $res->json('data.id');

        $assignment = \DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $item->id)
            ->where('user_id', $reporter->id)
            ->first();
        $this->assertSame('done', $assignment->assignment_status);

        // Manager confirm → KHÔNG auto-close task (task vẫn in_progress)
        \Laravel\Sanctum\Sanctum::actingAs($manager);
        $this->patchJson("/api/task-assignment-item-reports/{$reportId}/confirm", [
            'confirm_note' => 'OK',
        ], ['X-Organization-Id' => $this->org->id])->assertOk();

        $item->refresh();
        $this->assertSame('in_progress', $item->processing_status, 'Confirm report KHÔNG auto đóng task');

        // Manager bấm close endpoint → task done
        $closeRes = $this->patchJson(
            "/api/task-assignment-items/{$item->id}/mark-done",
            [],
            ['X-Organization-Id' => $this->org->id]
        );
        $closeRes->assertOk();

        $item->refresh();
        $this->assertSame('done', $item->processing_status);
        $this->assertSame(100, (int) $item->completion_percent);
        $this->assertNotNull($item->completed_at);
    }

    public function test_mark_done_rejects_when_no_locked_report(): void
    {
        $this->setupUser();
        $report = $this->makeReport();   // chưa lock
        $itemId = $report->task_assignment_item_id;

        $res = $this->patchJson("/api/task-assignment-items/{$itemId}/mark-done", [], ['X-Organization-Id' => $this->org->id]);

        $res->assertStatus(422);
    }

    public function test_mark_done_rejects_when_task_already_done(): void
    {
        $this->setupUser();
        $report = $this->makeReport();
        $report->update(['is_locked' => true, 'locked_at' => now(), 'manager_confirmed' => true]);

        $item = $report->item;
        $item->update(['processing_status' => 'done', 'completed_at' => now()]);

        $res = $this->patchJson("/api/task-assignment-items/{$item->id}/mark-done", [], ['X-Organization-Id' => $this->org->id]);

        $res->assertStatus(422);
    }
}
