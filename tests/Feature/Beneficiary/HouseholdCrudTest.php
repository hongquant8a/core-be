<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdCrudTest extends TestCase
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

    public function test_store_generates_household_code_when_empty(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/beneficiary-households', [
            'head_name' => 'Nguyễn Văn A',
            'address' => '12 Trần Phú',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertNotEmpty($res->json('data.household_code'));
    }

    public function test_store_allows_household_without_address(): void
    {
        Sanctum::actingAs($this->admin);

        // Chỉ head_name là bắt buộc — address có thể bổ sung sau khi xác minh thực địa.
        $res = $this->postJson('/api/beneficiary-households', [
            'head_name' => 'Nguyễn Văn B',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.address', null);
    }

    public function test_household_observer_updates_member_count_when_beneficiary_assigned(): void
    {
        Sanctum::actingAs($this->admin);

        $household = Household::create([
            'organization_id' => $this->orgA->id,
            'household_code' => 'HGD-TEST-01',
            'head_name' => 'Chủ hộ',
            'address' => 'Địa chỉ test',
        ]);

        $this->assertEquals(0, $household->member_count);

        Beneficiary::create([
            'organization_id' => $this->orgA->id,
            'household_id' => $household->id,
            'full_name' => 'Thành viên 1',
            'gender' => 'male',
            'status' => 'active',
        ]);

        $this->assertEquals(1, $household->fresh()->member_count);
    }

    public function test_household_observer_decrements_member_count_when_reassigned(): void
    {
        Sanctum::actingAs($this->admin);

        $householdA = Household::create([
            'organization_id' => $this->orgA->id, 'household_code' => 'HGD-A', 'head_name' => 'A', 'address' => 'X',
        ]);
        $householdB = Household::create([
            'organization_id' => $this->orgA->id, 'household_code' => 'HGD-B', 'head_name' => 'B', 'address' => 'Y',
        ]);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'household_id' => $householdA->id,
            'full_name' => 'Thành viên', 'gender' => 'male', 'status' => 'active',
        ]);

        $this->assertEquals(1, $householdA->fresh()->member_count);

        $beneficiary->update(['household_id' => $householdB->id]);

        $this->assertEquals(0, $householdA->fresh()->member_count);
        $this->assertEquals(1, $householdB->fresh()->member_count);
    }
}
