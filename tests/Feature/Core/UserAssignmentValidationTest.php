<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Validate cho cơ chế assignments.*.organization_ids — distinct phải scope per-role,
 * không phải global cross-roles.
 */
class UserAssignmentValidationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Role $roleA;

    private Role $roleB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->org = Organization::firstOrCreate(['slug' => 'assign-test'], ['name' => 'Org', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        Sanctum::actingAs($admin);

        $this->roleA = Role::firstOrCreate(['name' => 'Đại biểu', 'guard_name' => 'web']);
        $this->roleB = Role::firstOrCreate(['name' => 'Nhân viên', 'guard_name' => 'web']);
    }

    public function test_update_allows_same_org_in_different_roles(): void
    {
        // Bug fix: trước đây Laravel `distinct` rule trên `assignments.*.organization_ids.*`
        // áp cross-wildcard → 2 role khác nhau cùng có org_id=1 bị flag trùng.
        $user = User::factory()->create();

        $res = $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'assignments' => [
                ['role_id' => $this->roleA->id, 'organization_ids' => [$this->org->id]],
                ['role_id' => $this->roleB->id, 'organization_ids' => [$this->org->id]],
            ],
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertOk();
    }

    public function test_update_rejects_duplicate_org_within_same_role(): void
    {
        // Distinct vẫn áp đúng intent: org bị trùng trong cùng 1 role → reject.
        $user = User::factory()->create();

        $res = $this->putJson("/api/users/{$user->id}", [
            'name' => $user->name,
            'assignments' => [
                ['role_id' => $this->roleA->id, 'organization_ids' => [$this->org->id, $this->org->id]],
            ],
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['assignments.0.organization_ids']);
    }

    public function test_store_allows_same_org_in_different_roles(): void
    {
        $res = $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'multirole@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'assignments' => [
                ['role_id' => $this->roleA->id, 'organization_ids' => [$this->org->id]],
                ['role_id' => $this->roleB->id, 'organization_ids' => [$this->org->id]],
            ],
        ], ['X-Organization-Id' => $this->org->id]);

        $res->assertStatus(201);
    }
}
