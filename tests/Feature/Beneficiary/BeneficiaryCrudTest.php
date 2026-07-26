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

    public function test_store_links_existing_dependents_and_creates_documents(): void
    {
        Sanctum::actingAs($this->admin);

        $area = ResidentialArea::create(['organization_id' => $this->orgA->id, 'name' => 'Tổ 5']);
        $household = Household::create(['organization_id' => $this->orgA->id, 'head_name' => 'Chủ hộ']);
        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con A', 'gender' => 'male',
        ]);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn E',
            'gender' => 'male',
            'household_id' => $household->id,
            'residential_area_id' => $area->id,
            'dependents' => [
                ['dependent_id' => $dependent->id, 'relationship_type' => 'child', 'note' => 'Con ruột'],
            ],
            'documents' => [
                ['name' => 'Giấy chứng nhận', 'note' => 'Bản sao'],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $res->assertJsonPath('data.residential_area.name', 'Tổ 5');
        $res->assertJsonPath('data.dependents.0.dependent.full_name', 'Con A');
        $res->assertJsonPath('data.dependents.0.relationship_type', 'child');
        $res->assertJsonPath('data.documents.0.name', 'Giấy chứng nhận');

        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'dependent_id' => $dependent->id, 'relationship_type' => 'child',
        ]);
        $this->assertDatabaseHas('beneficiary_documents', [
            'name' => 'Giấy chứng nhận', 'organization_id' => $this->orgA->id,
        ]);
    }

    public function test_store_rejects_unknown_dependent(): void
    {
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn F',
            'gender' => 'male',
            'dependents' => [['dependent_id' => 999999, 'relationship_type' => 'child']],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('dependents.0.dependent_id');
    }

    /** Gửi mảng nào thì thay thế TOÀN BỘ mảng đó — dòng cũ bị xóa hết rồi tạo lại. */
    public function test_update_replaces_whole_section(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Trần Văn G', 'gender' => 'male', 'status' => 'active',
        ]);
        $old = $beneficiary->documents()->create(['organization_id' => $this->orgA->id, 'name' => 'Tài liệu cũ']);
        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con B', 'gender' => 'female',
        ]);

        $res = $this->putJson("/api/beneficiaries/{$beneficiary->id}", [
            'documents' => [['name' => 'Tài liệu mới']],
            'dependents' => [['dependent_id' => $dependent->id, 'relationship_type' => 'child']],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertDatabaseMissing('beneficiary_documents', ['id' => $old->id]);
        $this->assertDatabaseHas('beneficiary_documents', ['beneficiary_id' => $beneficiary->id, 'name' => 'Tài liệu mới']);
        $this->assertSame(1, $beneficiary->documents()->count());
        $this->assertDatabaseHas('beneficiary_dependent_relations', [
            'beneficiary_id' => $beneficiary->id, 'dependent_id' => $dependent->id,
        ]);
    }

    /** Không gửi khóa thì giữ nguyên; gửi mảng rỗng thì xóa sạch. */
    public function test_update_keeps_section_when_key_absent_and_clears_on_empty_array(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Trần Văn L', 'gender' => 'male', 'status' => 'active',
        ]);
        $beneficiary->documents()->create(['organization_id' => $this->orgA->id, 'name' => 'Tài liệu A']);

        $this->putJson("/api/beneficiaries/{$beneficiary->id}", [
            'full_name' => 'Trần Văn L2',
        ], ['X-Organization-Id' => $this->orgA->id])->assertOk();

        $this->assertSame(1, $beneficiary->documents()->count());

        $this->putJson("/api/beneficiaries/{$beneficiary->id}", [
            'documents' => [],
        ], ['X-Organization-Id' => $this->orgA->id])->assertOk();

        $this->assertSame(0, $beneficiary->documents()->count());
    }

    /** Gửi lại đúng payload cũ phải cho ra cùng trạng thái — PUT idempotent. */
    public function test_update_is_idempotent(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Trần Văn M', 'gender' => 'male', 'status' => 'active',
        ]);

        $payload = ['documents' => [['name' => 'Giấy A'], ['name' => 'Giấy B']]];

        $this->putJson("/api/beneficiaries/{$beneficiary->id}", $payload, ['X-Organization-Id' => $this->orgA->id])->assertOk();
        $this->putJson("/api/beneficiaries/{$beneficiary->id}", $payload, ['X-Organization-Id' => $this->orgA->id])->assertOk();

        $this->assertSame(2, $beneficiary->documents()->count());
    }

    public function test_update_rejects_id_inside_section(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Của tôi', 'gender' => 'male', 'status' => 'active',
        ]);
        $doc = $beneficiary->documents()->create(['organization_id' => $this->orgA->id, 'name' => 'Tài liệu']);

        $res = $this->putJson("/api/beneficiaries/{$beneficiary->id}", [
            'documents' => [['id' => $doc->id, 'name' => 'Đổi tên']],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('documents.0.id');
    }

    /**
     * `documents`/`dependents` có permission riêng — payload lồng không được thành đường vòng
     * qua hệ phân quyền cho người chỉ có quyền trên chính hồ sơ.
     */
    public function test_store_forbids_nested_documents_without_document_permission(): void
    {
        $clerk = User::factory()->create();
        $clerk->givePermissionTo('beneficiaries.store');
        Sanctum::actingAs($clerk);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn H',
            'gender' => 'male',
            'documents' => [['name' => 'Giấy lách quyền']],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertForbidden();
        $this->assertDatabaseMissing('beneficiary_documents', ['name' => 'Giấy lách quyền']);
    }

    public function test_store_allows_nested_documents_with_document_permission(): void
    {
        $clerk = User::factory()->create();
        $clerk->givePermissionTo(['beneficiaries.store', 'beneficiary-documents.store']);
        Sanctum::actingAs($clerk);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn I',
            'gender' => 'male',
            'documents' => [['name' => 'Giấy hợp lệ']],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertCreated();
        $this->assertDatabaseHas('beneficiary_documents', ['name' => 'Giấy hợp lệ']);
    }

    public function test_update_forbids_deleting_dependent_relation_without_permission(): void
    {
        $clerk = User::factory()->create();
        $clerk->givePermissionTo('beneficiaries.update');
        Sanctum::actingAs($clerk);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Trần Văn K', 'gender' => 'male', 'status' => 'active',
        ]);
        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con C', 'gender' => 'male',
        ]);
        $relation = $beneficiary->dependentRelations()->create([
            'dependent_id' => $dependent->id, 'relationship_type' => 'child',
        ]);

        // Gửi `dependents` = thay thế toàn bộ → quan hệ cũ bị xóa, cần quyền destroyRelation.
        $res = $this->putJson("/api/beneficiaries/{$beneficiary->id}", [
            'dependents' => [],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertForbidden();
        $this->assertDatabaseHas('beneficiary_dependent_relations', ['id' => $relation->id]);
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

    public function test_change_status_updates_status_and_death_date(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Người mất', 'gender' => 'male', 'status' => 'active',
        ]);

        $deathDate = now()->format('Y-m-d');

        $res = $this->patchJson("/api/beneficiaries/{$beneficiary->id}/status", [
            'status' => 'deceased',
            'death_date' => $deathDate,
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();

        $this->assertDatabaseHas('beneficiaries', [
            'id' => $beneficiary->id,
            'status' => 'deceased',
            'death_date' => $deathDate,
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
