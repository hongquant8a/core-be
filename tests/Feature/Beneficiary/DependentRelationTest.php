<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DependentRelationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $admin;
    private Beneficiary $beneficiary1;
    private Beneficiary $beneficiary2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');

        $this->beneficiary1 = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Liệt sĩ 1', 'gender' => 'male', 'status' => 'active',
        ]);
        $this->beneficiary2 = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Liệt sĩ 2', 'gender' => 'male', 'status' => 'active',
        ]);
    }

    public function test_store_rejects_duplicate_id_number_in_same_org(): void
    {
        Sanctum::actingAs($this->admin);

        Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Thân nhân 1',
            'gender' => 'female', 'id_number' => '049333333333',
        ]);

        $res = $this->postJson('/api/beneficiary-dependents', [
            'full_name' => 'Thân nhân 2', 'gender' => 'female', 'id_number' => '049333333333',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_number']);
    }

    public function test_relation_active_for_minor_child(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con nhỏ', 'gender' => 'male',
            'date_of_birth' => now()->subYears(10), 'is_alive' => true,
        ]);

        $res = $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id,
            'relationship_type' => 'child',
            'eligible_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.status', 'active');
    }

    public function test_relation_expired_for_adult_child_without_eligibility_status(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con lớn', 'gender' => 'male',
            'date_of_birth' => now()->subYears(25), 'is_alive' => true, 'eligibility_status' => 'normal',
        ]);

        $res = $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id,
            'relationship_type' => 'child',
            'eligible_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.status', 'expired');
    }

    public function test_relation_active_for_adult_child_still_studying(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con đang học', 'gender' => 'female',
            'date_of_birth' => now()->subYears(20), 'is_alive' => true, 'eligibility_status' => 'studying',
        ]);

        $res = $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id,
            'relationship_type' => 'child',
            'eligible_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.status', 'active');
    }

    public function test_dependent_can_relate_to_multiple_beneficiaries(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Mẹ 2 liệt sĩ', 'gender' => 'female',
            'date_of_birth' => now()->subYears(70), 'is_alive' => true,
        ]);

        $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id, 'relationship_type' => 'mother', 'eligible_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary2->id, 'relationship_type' => 'mother', 'eligible_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->assertEquals(2, $dependent->dependentRelations()->count());
    }

    public function test_update_is_alive_false_expires_all_active_relations(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Thân nhân', 'gender' => 'female',
            'date_of_birth' => now()->subYears(50), 'is_alive' => true,
        ]);

        $dependent->dependentRelations()->create([
            'beneficiary_id' => $this->beneficiary1->id, 'relationship_type' => 'spouse',
            'eligible_from' => now()->subYear(), 'status' => 'active',
        ]);

        $res = $this->putJson("/api/beneficiary-dependents/{$dependent->id}", [
            'is_alive' => false,
            'death_date' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'dependent_id' => $dependent->id,
            'status' => 'expired',
        ]);
    }
}
