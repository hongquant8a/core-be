<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchedulingEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $admin;
    private User $staff1;
    private User $staff2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $this->staff1 = User::factory()->create(['name' => 'Staff One', 'email' => 'staff1@example.com']);
        $this->staff2 = User::factory()->create(['name' => 'Staff Two', 'email' => 'staff2@example.com']);

        // Set up role "Tổng hợp lịch" (which has full scheduling-employees permission)
        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Tổng hợp lịch');

        setPermissionsTeamId($this->orgB->id);
        $this->admin->assignRole('Tổng hợp lịch');

        // Default context
        setPermissionsTeamId($this->orgA->id);
    }

    private function createEmployee(int $orgId, int $userId, bool $status = true): SchedulingEmployee
    {
        return SchedulingEmployee::create([
            'organization_id' => $orgId,
            'user_id' => $userId,
            'status' => $status,
        ]);
    }

    public function test_options_returns_active_employees_only(): void
    {
        Sanctum::actingAs($this->admin);

        // Clear existing backfilled employees for a clean test
        SchedulingEmployee::query()->delete();

        $this->createEmployee($this->orgA->id, $this->staff1->id, true);
        $this->createEmployee($this->orgA->id, $this->staff2->id, false);

        $res = $this->getJson('/api/scheduling-employees/options', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals($this->staff1->id, $data[0]['user_id']);
        $this->assertEquals('Staff One', $data[0]['name']);
    }

    public function test_stats_returns_correct_counts(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $this->createEmployee($this->orgA->id, $this->staff1->id, true);
        $this->createEmployee($this->orgA->id, $this->staff2->id, false);

        $res = $this->getJson('/api/scheduling-employees/stats', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonPath('data.total', 2);
        $res->assertJsonPath('data.active', 1);
        $res->assertJsonPath('data.inactive', 1);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $this->createEmployee($this->orgA->id, $this->staff1->id, true);
        $this->createEmployee($this->orgB->id, $this->staff2->id, true);

        $res = $this->getJson('/api/scheduling-employees', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $userIds = collect($res->json('data'))->pluck('user_id')->all();

        $this->assertContains($this->staff1->id, $userIds);
        $this->assertNotContains($this->staff2->id, $userIds);
    }

    public function test_store_creates_employee_successfully(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $res = $this->postJson('/api/scheduling-employees', [
            'user_id' => $this->staff1->id,
            'status' => true,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('scheduling_employees', [
            'organization_id' => $this->orgA->id,
            'user_id' => $this->staff1->id,
            'status' => true,
        ]);
    }

    public function test_store_validates_uniqueness_per_tenant(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $this->createEmployee($this->orgA->id, $this->staff1->id);

        $res = $this->postJson('/api/scheduling-employees', [
            'user_id' => $this->staff1->id,
            'status' => true,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('user_id');
    }

    public function test_destroy_fails_if_employee_has_schedule_dependency(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $emp = $this->createEmployee($this->orgA->id, $this->staff1->id);

        // Create a schedule hosted by this employee
        Schedule::create([
            'organization_id' => $this->orgA->id,
            'module_type' => 'EXECUTIVE',
            'date' => '2026-06-01',
            'session' => 'MORNING',
            'title' => 'Họp giao ban',
            'content' => 'Họp điều hành',
            'host_user_id' => $this->staff1->id,
            'status' => 'APPROVED',
            'created_by' => $this->admin->id,
        ]);

        $res = $this->deleteJson('/api/scheduling-employees/' . $emp->id, [], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(409);
        $res->assertJsonPath('success', false);
        $this->assertDatabaseHas('scheduling_employees', ['id' => $emp->id]);
    }

    public function test_destroy_deletes_employee_successfully_when_no_dependency(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $emp = $this->createEmployee($this->orgA->id, $this->staff1->id);

        $res = $this->deleteJson('/api/scheduling-employees/' . $emp->id, [], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertSoftDeleted('scheduling_employees', ['id' => $emp->id]);
    }

    public function test_bulk_destroy_deletes_multiple_employees(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $emp1 = $this->createEmployee($this->orgA->id, $this->staff1->id);
        $emp2 = $this->createEmployee($this->orgA->id, $this->staff2->id);

        $res = $this->deleteJson('/api/scheduling-employees/bulk-delete', [
            'ids' => [$emp1->id, $emp2->id],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertSoftDeleted('scheduling_employees', ['id' => $emp1->id]);
        $this->assertSoftDeleted('scheduling_employees', ['id' => $emp2->id]);
    }

    public function test_change_status_updates_employee_status(): void
    {
        Sanctum::actingAs($this->admin);

        SchedulingEmployee::query()->delete();

        $emp = $this->createEmployee($this->orgA->id, $this->staff1->id, true);

        $res = $this->patchJson("/api/scheduling-employees/{$emp->id}/status", [
            'status' => false,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('scheduling_employees', [
            'id' => $emp->id,
            'status' => false,
        ]);
    }

    public function test_export_downloads_excel(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/scheduling-employees/export', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertTrue(
            str_contains(
                $res->headers->get('content-type'),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            )
        );
    }
}
