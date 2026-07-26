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

    public function test_store_creates_dependent_with_residential_area(): void
    {
        Sanctum::actingAs($this->admin);

        $area = \App\Modules\Beneficiary\Models\ResidentialArea::create([
            'organization_id' => $this->orgA->id, 'name' => 'Tổ 1',
        ]);

        $res = $this->postJson('/api/beneficiary-dependents', [
            'full_name' => 'Thân nhân', 'gender' => 'female',
            'residential_area_id' => $area->id, 'phone' => '0905000111',
            'latitude' => 16.0678, 'longitude' => 108.2208,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('beneficiary_dependents', [
            'full_name' => 'Thân nhân', 'residential_area_id' => $area->id, 'phone' => '0905000111',
        ]);
    }

    public function test_store_relation_creates_relationship(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con', 'gender' => 'male',
            'date_of_birth' => now()->subYears(10),
        ]);

        $res = $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id,
            'relationship_type' => 'child',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.relationship_type', 'child');
        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'dependent_id' => $dependent->id,
            'beneficiary_id' => $this->beneficiary1->id,
            'relationship_type' => 'child',
        ]);
    }

    public function test_dependent_can_relate_to_multiple_beneficiaries(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Mẹ 2 liệt sĩ', 'gender' => 'female',
            'date_of_birth' => now()->subYears(70),
        ]);

        $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id, 'relationship_type' => 'mother',
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary2->id, 'relationship_type' => 'mother',
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->assertEquals(2, $dependent->dependentRelations()->count());
    }

    /** Đặt thân nhân chính mới phải tự hạ thân nhân chính cũ của CÙNG hồ sơ xuống phụ. */
    public function test_store_relation_keeps_only_one_primary_dependent_per_beneficiary(): void
    {
        Sanctum::actingAs($this->admin);

        $first = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con cả', 'gender' => 'male',
        ]);
        $second = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con thứ', 'gender' => 'female',
        ]);

        $this->postJson("/api/beneficiary-dependents/{$first->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id, 'relationship_type' => 'child', 'is_primary' => true,
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->postJson("/api/beneficiary-dependents/{$second->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id, 'relationship_type' => 'child', 'is_primary' => true,
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'beneficiary_id' => $this->beneficiary1->id, 'dependent_id' => $first->id, 'is_primary' => false,
        ]);
        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'beneficiary_id' => $this->beneficiary1->id, 'dependent_id' => $second->id, 'is_primary' => true,
        ]);

        // Hồ sơ khác không bị ảnh hưởng.
        $this->postJson("/api/beneficiary-dependents/{$first->id}/relations", [
            'beneficiary_id' => $this->beneficiary2->id, 'relationship_type' => 'child', 'is_primary' => true,
        ], ['X-Organization-Id' => $this->orgA->id])->assertCreated();

        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'beneficiary_id' => $this->beneficiary2->id, 'dependent_id' => $first->id, 'is_primary' => true,
        ]);
        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'beneficiary_id' => $this->beneficiary1->id, 'dependent_id' => $second->id, 'is_primary' => true,
        ]);
    }

    /** `spouse` đã tách thành `wife`/`husband`, và bổ sung cháu + anh/chị/em. */
    public function test_store_relation_accepts_new_relationship_types_and_rejects_spouse(): void
    {
        Sanctum::actingAs($this->admin);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Vợ', 'gender' => 'female',
            'date_of_birth' => now()->subYears(65),
        ]);

        foreach (['wife', 'grandchild', 'older_brother', 'older_sister', 'younger_sibling'] as $type) {
            $relation = $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
                'beneficiary_id' => $this->beneficiary1->id,
                'relationship_type' => $type,
            ], ['X-Organization-Id' => $this->orgA->id]);

            $relation->assertCreated();
            $relation->assertJsonPath('data.relationship_type', $type);

            $dependent->dependentRelations()->delete();
        }

        $res = $this->postJson("/api/beneficiary-dependents/{$dependent->id}/relations", [
            'beneficiary_id' => $this->beneficiary1->id,
            'relationship_type' => 'spouse',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('relationship_type');
    }
}
