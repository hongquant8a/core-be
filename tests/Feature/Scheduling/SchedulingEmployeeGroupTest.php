<?php

namespace Tests\Feature\Scheduling;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\Scheduling\Models\SchedulingEmployee;
use App\Modules\Scheduling\Models\SchedulingEmployeeGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchedulingEmployeeGroupTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $admin;
    private SchedulingEmployee $emp1;
    private SchedulingEmployee $emp2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);
        $user1 = User::factory()->create(['name' => 'Staff One']);
        $user2 = User::factory()->create(['name' => 'Staff Two']);

        // Set up roles
        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Tổng hợp lịch');

        // Create Scheduling employees in orgA
        $this->emp1 = SchedulingEmployee::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $user1->id,
            'status' => true,
        ]);
        $this->emp2 = SchedulingEmployee::create([
            'organization_id' => $this->orgA->id,
            'user_id' => $user2->id,
            'status' => true,
        ]);

        setPermissionsTeamId($this->orgB->id);
        $this->admin->assignRole('Tổng hợp lịch');

        // Default context
        setPermissionsTeamId($this->orgA->id);
    }

    private function createGroup(int $orgId, string $name, bool $status = true): SchedulingEmployeeGroup
    {
        return SchedulingEmployeeGroup::create([
            'organization_id' => $orgId,
            'name' => $name,
            'description' => 'Mô tả nhóm test',
            'status' => $status,
        ]);
    }

    public function test_options_returns_active_groups(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createGroup($this->orgA->id, 'Nhóm A', true);
        $this->createGroup($this->orgA->id, 'Nhóm B', false);

        $res = $this->getJson('/api/scheduling-employee-groups/options', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $data = $res->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('Nhóm A', $data[0]['name']);
    }

    public function test_stats_returns_correct_counts(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createGroup($this->orgA->id, 'Nhóm 1', true);
        $this->createGroup($this->orgA->id, 'Nhóm 2', false);

        $res = $this->getJson('/api/scheduling-employee-groups/stats', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonPath('data.total', 2);
        $res->assertJsonPath('data.active', 1);
        $res->assertJsonPath('data.inactive', 1);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->admin);

        $this->createGroup($this->orgA->id, 'Nhóm Org A');
        $this->createGroup($this->orgB->id, 'Nhóm Org B');

        $res = $this->getJson('/api/scheduling-employee-groups', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $names = collect($res->json('data'))->pluck('name')->all();

        $this->assertContains('Nhóm Org A', $names);
        $this->assertNotContains('Nhóm Org B', $names);
    }

    public function test_store_creates_group_and_syncs_employees(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/scheduling-employee-groups', [
            'name' => 'Tổ Công Tác Đặc Biệt',
            'description' => 'Thành lập lâm thời',
            'status' => true,
            'employee_ids' => [$this->emp1->id, $this->emp2->id],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $groupId = $res->json('data.id');

        $this->assertDatabaseHas('scheduling_employee_groups', [
            'id' => $groupId,
            'name' => 'Tổ Công Tác Đặc Biệt',
            'organization_id' => $this->orgA->id,
        ]);

        $this->assertDatabaseHas('scheduling_employee_group_members', [
            'scheduling_employee_group_id' => $groupId,
            'scheduling_employee_id' => $this->emp1->id,
        ]);
        $this->assertDatabaseHas('scheduling_employee_group_members', [
            'scheduling_employee_group_id' => $groupId,
            'scheduling_employee_id' => $this->emp2->id,
        ]);
    }

    public function test_update_updates_group_and_syncs_correctly(): void
    {
        Sanctum::actingAs($this->admin);

        $group = $this->createGroup($this->orgA->id, 'Nhóm Gốc');
        $group->employees()->attach($this->emp1->id);

        $res = $this->putJson('/api/scheduling-employee-groups/' . $group->id, [
            'name' => 'Nhóm Cập Nhật',
            'employee_ids' => [$this->emp2->id], // Switch from emp1 to emp2
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertEquals('Nhóm Cập Nhật', $res->json('data.name'));

        $this->assertDatabaseMissing('scheduling_employee_group_members', [
            'scheduling_employee_group_id' => $group->id,
            'scheduling_employee_id' => $this->emp1->id,
        ]);
        $this->assertDatabaseHas('scheduling_employee_group_members', [
            'scheduling_employee_group_id' => $group->id,
            'scheduling_employee_id' => $this->emp2->id,
        ]);
    }

    public function test_destroy_deletes_group(): void
    {
        Sanctum::actingAs($this->admin);

        $group = $this->createGroup($this->orgA->id, 'Nhóm Xóa');
        $group->employees()->attach($this->emp1->id);

        $res = $this->deleteJson('/api/scheduling-employee-groups/' . $group->id, [], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertSoftDeleted('scheduling_employee_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('scheduling_employee_group_members', [
            'scheduling_employee_group_id' => $group->id,
        ]);
    }

    public function test_change_status_updates_status(): void
    {
        Sanctum::actingAs($this->admin);

        $group = $this->createGroup($this->orgA->id, 'Nhóm Hoạt Động', true);

        $res = $this->patchJson("/api/scheduling-employee-groups/{$group->id}/status", [
            'status' => false,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('scheduling_employee_groups', [
            'id' => $group->id,
            'status' => false,
        ]);
    }

    public function test_bulk_destroy_deletes_multiple_groups(): void
    {
        Sanctum::actingAs($this->admin);

        $group1 = $this->createGroup($this->orgA->id, 'Nhóm Hàng Loạt 1');
        $group2 = $this->createGroup($this->orgA->id, 'Nhóm Hàng Loạt 2');

        $res = $this->deleteJson('/api/scheduling-employee-groups/bulk-delete', [
            'ids' => [$group1->id, $group2->id],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertSoftDeleted('scheduling_employee_groups', ['id' => $group1->id]);
        $this->assertSoftDeleted('scheduling_employee_groups', ['id' => $group2->id]);
    }
}
