<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');
    }

    private function seedData(): void
    {
        $area = ResidentialArea::create(['organization_id' => $this->orgA->id, 'name' => 'Tổ 1']);
        $household = Household::create([
            'organization_id' => $this->orgA->id, 'residential_area_id' => $area->id, 'head_name' => 'Chủ hộ',
        ]);

        $b1 = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'household_id' => $household->id,
            'full_name' => 'Thương binh', 'gender' => 'male', 'status' => 'active', 'date_of_birth' => '1950-01-01',
        ]);
        $b1->classifications()->create(['type' => 'war_invalid', 'is_primary' => true]);

        $b2 = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'household_id' => $household->id,
            'full_name' => 'Liệt sĩ', 'gender' => 'female', 'status' => 'deceased', 'birth_year' => '1940',
        ]);
        $b2->classifications()->create(['type' => 'martyr', 'is_primary' => true]);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con', 'gender' => 'male', 'date_of_birth' => '2015-01-01',
        ]);
        $dependent->dependentRelations()->create(['beneficiary_id' => $b1->id, 'relationship_type' => 'child']);
    }

    public function test_overview_returns_full_dashboard_structure(): void
    {
        Sanctum::actingAs($this->admin);
        $this->seedData();

        $res = $this->getJson('/api/beneficiary-statistics/overview', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => [
                'summary' => ['total_beneficiaries', 'active_beneficiaries', 'total_dependents', 'total_households', 'total_residential_areas'],
                'by_type' => [['key', 'label', 'total']],
                'by_status' => [['key', 'label', 'total']],
                'by_residential_area' => [['key', 'label', 'total']],
                'by_gender' => [['key', 'label', 'total']],
                'by_age_group' => [['key', 'label', 'total']],
                'by_relationship' => [['key', 'label', 'total']],
                'new_by_month' => ['year', 'data' => [['key', 'label', 'total']]],
            ],
        ]);

        $res->assertJsonPath('data.summary.total_beneficiaries', 2);
        $res->assertJsonPath('data.summary.active_beneficiaries', 1);
        $res->assertJsonPath('data.summary.total_dependents', 1);

        // by_type có đủ 12 nhóm (kể cả nhóm 0), war_invalid = 1.
        $this->assertCount(12, $res->json('data.by_type'));
        $warInvalid = collect($res->json('data.by_type'))->firstWhere('key', 'war_invalid');
        $this->assertSame(1, $warInvalid['total']);

        // by_residential_area: Tổ 1 có 2 người có công.
        $to1 = collect($res->json('data.by_residential_area'))->firstWhere('label', 'Tổ 1');
        $this->assertSame(2, $to1['total']);

        // by_relationship: child = 1.
        $child = collect($res->json('data.by_relationship'))->firstWhere('key', 'child');
        $this->assertSame(1, $child['total']);
    }

    public function test_endpoints_scoped_to_tenant(): void
    {
        Sanctum::actingAs($this->admin);
        $this->seedData();

        // Org khác không thấy dữ liệu của org A.
        $orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);
        setPermissionsTeamId($orgB->id);
        $this->admin->assignRole('Super Admin');

        $res = $this->getJson('/api/beneficiary-statistics/overview', ['X-Organization-Id' => $orgB->id]);

        $res->assertOk();
        $res->assertJsonPath('data.summary.total_beneficiaries', 0);
    }

    public function test_new_by_month_respects_year_param(): void
    {
        Sanctum::actingAs($this->admin);
        $this->seedData();

        $res = $this->getJson('/api/beneficiary-statistics/new-by-month?year=2000', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonPath('data.year', 2000);
        $this->assertCount(12, $res->json('data.data'));
        // Không có hồ sơ nào tạo năm 2000.
        $this->assertSame(0, collect($res->json('data.data'))->sum('total'));
    }
}
