<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnumControllerTest extends TestCase
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

    public function test_index_returns_all_enums_with_value_and_label(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/beneficiary-enums', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonStructure([
            'data' => [
                'beneficiary_type', 'beneficiary_status', 'gender',
                'dependent_eligibility', 'dependent_relationship', 'dependent_relation_status',
                'subsidy_status', 'document_type', 'visit_occasion', 'schedule_status',
            ],
        ]);
        $res->assertJsonCount(12, 'data.beneficiary_type');
        $res->assertJsonFragment(['value' => 'martyr', 'label' => 'Liệt sĩ']);
        $res->assertJsonFragment(['value' => 'vietnamese_heroic_mother', 'label' => 'Bà mẹ Việt Nam anh hùng']);
    }

    public function test_index_requires_authentication(): void
    {
        $res = $this->getJson('/api/beneficiary-enums');

        $res->assertUnauthorized();
    }

    public function test_index_requires_organization_header(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->getJson('/api/beneficiary-enums');

        $res->assertUnprocessable();
    }
}
