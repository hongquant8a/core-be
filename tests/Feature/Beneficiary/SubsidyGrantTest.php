<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubsidyGrantTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private User $admin;
    private Beneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $this->orgA = Organization::firstOrCreate(['slug' => 'test-a'], ['name' => 'Org A', 'status' => 'active']);
        $this->admin = User::factory()->create(['name' => 'Admin User']);

        setPermissionsTeamId($this->orgA->id);
        $this->admin->assignRole('Super Admin');

        $this->beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'NCC', 'gender' => 'male', 'status' => 'active',
        ]);
    }

    public function test_store_auto_fills_amount_from_policy(): void
    {
        Sanctum::actingAs($this->admin);

        $policy = SubsidyPolicy::create(['amount' => 3500000, 'legal_basis' => 'Test', 'effective_from' => now()->subMonth()]);

        $res = $this->postJson('/api/beneficiary-subsidy-grants', [
            'subject_type' => 'beneficiary',
            'subject_id' => $this->beneficiary->id,
            'beneficiary_subsidy_policy_id' => $policy->id,
            'granted_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.amount', '3500000.00');
    }

    public function test_store_rejects_expired_policy(): void
    {
        Sanctum::actingAs($this->admin);

        $policy = SubsidyPolicy::create([
            'amount' => 3500000, 'legal_basis' => 'Test', 'effective_from' => now()->subYears(2),
            'effective_to' => now()->subMonth(),
        ]);

        $res = $this->postJson('/api/beneficiary-subsidy-grants', [
            'subject_type' => 'beneficiary',
            'subject_id' => $this->beneficiary->id,
            'beneficiary_subsidy_policy_id' => $policy->id,
            'granted_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('beneficiary_subsidy_policy_id');
    }

    public function test_change_status_to_terminated_requires_reason(): void
    {
        Sanctum::actingAs($this->admin);

        $policy = SubsidyPolicy::create(['amount' => 3500000, 'legal_basis' => 'Test', 'effective_from' => now()->subMonth()]);
        $grant = \App\Modules\Beneficiary\Models\SubsidyGrant::create([
            'organization_id' => $this->orgA->id,
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => $this->beneficiary->id,
            'beneficiary_subsidy_policy_id' => $policy->id,
            'amount' => 3500000,
            'granted_from' => now()->subMonth(),
            'status' => 'active',
        ]);

        $res = $this->patchJson("/api/beneficiary-subsidy-grants/{$grant->id}/status", [
            'status' => 'terminated',
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('termination_reason');
    }

    public function test_renew_policy_closes_old_and_migrates_active_grants(): void
    {
        Sanctum::actingAs($this->admin);

        $oldPolicy = SubsidyPolicy::create(['amount' => 3500000, 'legal_basis' => 'ND cũ', 'effective_from' => now()->subYear()]);
        $grant = \App\Modules\Beneficiary\Models\SubsidyGrant::create([
            'organization_id' => $this->orgA->id,
            'subject_type' => (new Beneficiary())->getMorphClass(),
            'subject_id' => $this->beneficiary->id,
            'beneficiary_subsidy_policy_id' => $oldPolicy->id,
            'amount' => 3500000,
            'granted_from' => now()->subYear(),
            'status' => 'active',
        ]);

        $res = $this->postJson("/api/beneficiary-subsidy-policies/{$oldPolicy->id}/renew", [
            'amount' => 4000000,
            'legal_basis' => 'ND mới',
            'effective_from' => now()->format('Y-m-d'),
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $newPolicyId = $res->json('data.id');

        $this->assertDatabaseHas('beneficiary_subsidy_grants', ['id' => $grant->id, 'status' => 'terminated']);
        $this->assertDatabaseHas('beneficiary_subsidy_grants', [
            'subject_id' => $this->beneficiary->id,
            'beneficiary_subsidy_policy_id' => $newPolicyId,
            'amount' => 4000000,
            'status' => 'active',
        ]);
        $this->assertNotNull($oldPolicy->fresh()->effective_to);
    }
}
