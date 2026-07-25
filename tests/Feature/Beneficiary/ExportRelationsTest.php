<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Exports\BeneficiaryExport;
use App\Modules\Beneficiary\Exports\DependentExport;
use App\Modules\Beneficiary\Exports\HouseholdExport;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportRelationsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $admin = User::factory()->create(['name' => 'Admin']);
        setPermissionsTeamId($this->orgA->id);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
    }

    public function test_beneficiary_export_lists_relations_joined_by_semicolon(): void
    {
        $household = Household::create(['organization_id' => $this->orgA->id, 'head_name' => 'Chủ hộ']);

        $b = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'household_id' => $household->id,
            'full_name' => 'Thương binh A', 'gender' => 'male', 'status' => 'active',
        ]);
        $b->classifications()->create(['type' => 'war_invalid', 'is_primary' => true]);
        $b->classifications()->create(['type' => 'agent_orange_victim']);
        $b->documents()->create(['organization_id' => $this->orgA->id, 'name' => 'Giấy A']);
        $b->documents()->create(['organization_id' => $this->orgA->id, 'name' => 'Giấy B']);

        $d1 = Dependent::create(['organization_id' => $this->orgA->id, 'full_name' => 'Con 1', 'gender' => 'male']);
        $d2 = Dependent::create(['organization_id' => $this->orgA->id, 'full_name' => 'Con 2', 'gender' => 'female']);
        $b->dependents()->attach([$d1->id => ['relationship_type' => 'child'], $d2->id => ['relationship_type' => 'child']]);

        $row = (new BeneficiaryExport())->collection()->firstWhere('full_name', 'Thương binh A');

        // 2 loại đối tượng, ngăn cách "; "
        $this->assertStringContainsString(';', $row['classifications']);
        $this->assertStringContainsString('Thương binh', $row['classifications']);
        $this->assertStringContainsString('nhiễm chất độc hóa học', $row['classifications']);

        $this->assertSame('Con 1; Con 2', $row['dependents']);
        $this->assertSame('Giấy A; Giấy B', $row['documents']);
    }

    public function test_dependent_export_lists_linked_beneficiaries_with_relationship(): void
    {
        $d = Dependent::create(['organization_id' => $this->orgA->id, 'full_name' => 'Mẹ', 'gender' => 'female']);
        $b1 = Beneficiary::create(['organization_id' => $this->orgA->id, 'full_name' => 'Liệt sĩ 1', 'gender' => 'male', 'status' => 'deceased']);
        $b2 = Beneficiary::create(['organization_id' => $this->orgA->id, 'full_name' => 'Liệt sĩ 2', 'gender' => 'male', 'status' => 'deceased']);
        $d->beneficiaries()->attach([$b1->id => ['relationship_type' => 'mother'], $b2->id => ['relationship_type' => 'mother']]);

        $row = (new DependentExport())->collection()->firstWhere('full_name', 'Mẹ');

        $this->assertStringContainsString('Liệt sĩ 1 (Mẹ)', $row['beneficiaries']);
        $this->assertStringContainsString(';', $row['beneficiaries']);
    }

    public function test_household_export_lists_members_joined_by_semicolon(): void
    {
        $household = Household::create(['organization_id' => $this->orgA->id, 'head_name' => 'Chủ hộ']);
        Beneficiary::create(['organization_id' => $this->orgA->id, 'household_id' => $household->id, 'full_name' => 'NCC 1', 'gender' => 'male', 'status' => 'active']);
        Dependent::create(['organization_id' => $this->orgA->id, 'household_id' => $household->id, 'full_name' => 'TN 1', 'gender' => 'female']);

        $row = (new HouseholdExport())->collection()->firstWhere('head_name', 'Chủ hộ');

        $this->assertSame('NCC 1', $row['beneficiaries']);
        $this->assertSame('TN 1', $row['dependents']);
    }
}
