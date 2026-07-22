<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BeneficiaryCrudTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->orgB = Organization::firstOrCreate(['slug' => 'test-b'], ['name' => 'Org B', 'status' => 'active']);

        $this->admin = User::factory()->create(['name' => 'Admin User']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');
        setPermissionsTeamId($this->orgB->id);
        $this->admin->assignRole('Super Admin');

        setPermissionsTeamId($this->orgA->id);
    }

    public function test_store_creates_beneficiary_with_classifications(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn B',
            'gender' => 'male',
            'classifications' => [
                ['type' => 'war_invalid', 'decision_no' => 'QD-1', 'decision_date' => '2020-01-01', 'issued_by' => 'Sở LĐTBXH', 'is_primary' => true],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('beneficiaries', ['full_name' => 'Trần Văn B', 'organization_id' => $this->orgA->id]);
        $this->assertDatabaseHas('beneficiary_classifications', ['decision_no' => 'QD-1', 'is_primary' => true]);
    }

    public function test_store_rejects_duplicate_id_number_in_same_org(): void
    {
        Sanctum::actingAs($this->admin);

        Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Người A',
            'gender' => 'male', 'id_number' => '049123456789', 'status' => 'active',
        ]);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Người B', 'gender' => 'male', 'id_number' => '049123456789',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_number']);
    }

    public function test_store_allows_same_id_number_in_different_org(): void
    {
        Sanctum::actingAs($this->admin);

        Beneficiary::create([
            'organization_id' => $this->orgB->id, 'full_name' => 'Người Org B',
            'gender' => 'male', 'id_number' => '049999999999', 'status' => 'active',
        ]);

        // Cùng CCCD nhưng tổ chức khác → hợp lệ (unique theo organization_id).
        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Người Org A', 'gender' => 'male', 'id_number' => '049999999999',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
    }

    public function test_store_allows_classification_without_decision_details(): void
    {
        Sanctum::actingAs($this->admin);

        // Chỉ type là bắt buộc — decision_no/decision_date/issued_by bổ sung sau khi có
        // đủ giấy tờ, và không bắt buộc phải chọn is_primary ngay.
        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn D',
            'gender' => 'male',
            'classifications' => [
                ['type' => 'war_invalid'],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('beneficiary_classifications', ['type' => 'war_invalid', 'decision_no' => null, 'is_primary' => false]);
    }

    public function test_store_rejects_multiple_primary_classifications(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn C',
            'gender' => 'male',
            'classifications' => [
                ['type' => 'war_invalid', 'decision_no' => 'QD-1', 'decision_date' => '2020-01-01', 'issued_by' => 'Sở LĐTBXH', 'is_primary' => true],
                ['type' => 'disease_invalid', 'decision_no' => 'QD-2', 'decision_date' => '2020-01-01', 'issued_by' => 'Sở LĐTBXH', 'is_primary' => true],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('classifications');
    }

    public function test_index_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->admin);

        $bA = Beneficiary::create(['organization_id' => $this->orgA->id, 'full_name' => 'A Org', 'gender' => 'male', 'status' => 'active']);
        $bB = Beneficiary::create(['organization_id' => $this->orgB->id, 'full_name' => 'B Org', 'gender' => 'male', 'status' => 'active']);

        $res = $this->getJson('/api/beneficiaries', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertContains($bA->id, $ids);
        $this->assertNotContains($bB->id, $ids);
    }

    public function test_change_status_writes_status_history_and_stops_active_grants(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Người mất', 'gender' => 'male', 'status' => 'active',
        ]);

        $policy = \App\Modules\Beneficiary\Models\SubsidyPolicy::create([
            'amount' => 1000000, 'legal_basis' => 'Test', 'effective_from' => now()->subYear(),
        ]);

        \App\Modules\Beneficiary\Models\SubsidyGrant::create([
            'organization_id' => $this->orgA->id,
            'subject_type' => $beneficiary->getMorphClass(),
            'subject_id' => $beneficiary->id,
            'beneficiary_subsidy_policy_id' => $policy->id,
            'amount' => 1000000,
            'granted_from' => now()->subMonths(3),
            'status' => 'active',
        ]);

        $res = $this->patchJson("/api/beneficiaries/{$beneficiary->id}/status", [
            'status' => 'deceased',
            'reason' => 'Qua đời',
            'death_date' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();

        $this->assertDatabaseHas('beneficiary_status_histories', [
            'subject_id' => $beneficiary->id,
            'old_status' => 'active',
            'new_status' => 'deceased',
        ]);

        $this->assertDatabaseHas('beneficiary_subsidy_grants', [
            'subject_id' => $beneficiary->id,
            'status' => 'terminated',
        ]);
    }

    public function test_bulk_destroy_deletes_multiple_beneficiaries(): void
    {
        Sanctum::actingAs($this->admin);

        $b1 = Beneficiary::create(['organization_id' => $this->orgA->id, 'full_name' => 'X', 'gender' => 'male', 'status' => 'active']);
        $b2 = Beneficiary::create(['organization_id' => $this->orgA->id, 'full_name' => 'Y', 'gender' => 'female', 'status' => 'active']);

        $res = $this->deleteJson('/api/beneficiaries/bulk-delete', [
            'ids' => [$b1->id, $b2->id],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseMissing('beneficiaries', ['id' => $b1->id]);
        $this->assertDatabaseMissing('beneficiaries', ['id' => $b2->id]);
    }
}
