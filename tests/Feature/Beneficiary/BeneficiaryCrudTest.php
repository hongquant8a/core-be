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

    /** `search` quét cả thân nhân: tên, CCCD, SĐT — cán bộ thường chỉ cầm 1 mảnh thông tin. */
    public function test_index_search_matches_dependent_name_id_number_and_phone(): void
    {
        Sanctum::actingAs($this->admin);

        $target = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Người có công X',
            'gender' => 'male', 'status' => 'active',
        ]);
        $other = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Người có công Y',
            'gender' => 'male', 'status' => 'active',
        ]);

        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Nguyễn Thị Thân Nhân',
            'gender' => 'female', 'id_number' => '049777888999', 'phone' => '0905777888',
        ]);
        $target->dependentRelations()->create([
            'dependent_id' => $dependent->id, 'relationship_type' => 'child',
        ]);

        foreach (['Thân Nhân', '049777888999', '0905777888'] as $keyword) {
            $res = $this->getJson('/api/beneficiaries?search='.urlencode($keyword), ['X-Organization-Id' => $this->orgA->id]);

            $res->assertOk();
            $ids = collect($res->json('data'))->pluck('id')->all();

            $this->assertContains($target->id, $ids, "Không tìm ra hồ sơ qua từ khóa: {$keyword}");
            // Chốt chặn cho bẫy AND/OR trong whereHas: không bọc closure thì subquery khớp
            // MỌI thân nhân và hồ sơ không liên quan cũng lọt vào kết quả.
            $this->assertNotContains($other->id, $ids, "Từ khóa {$keyword} lọt hồ sơ không liên quan");
        }
    }

    public function test_index_search_matches_beneficiary_phone(): void
    {
        Sanctum::actingAs($this->admin);

        $b = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Có số điện thoại',
            'gender' => 'male', 'status' => 'active', 'phone' => '0912345678',
        ]);

        $res = $this->getJson('/api/beneficiaries?search=0912345678', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $this->assertContains($b->id, collect($res->json('data'))->pluck('id')->all());
    }

    /** Hồ sơ kiêm nhiều loại vẫn phải khớp khi lọc theo 1 loại bất kỳ. */
    public function test_index_filters_by_classification_type(): void
    {
        Sanctum::actingAs($this->admin);

        $multi = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Kiêm 2 loại',
            'gender' => 'male', 'status' => 'active',
        ]);
        $multi->classifications()->create(['type' => 'war_invalid', 'is_primary' => true]);
        $multi->classifications()->create(['type' => 'agent_orange_victim']);

        $single = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Chỉ bệnh binh',
            'gender' => 'male', 'status' => 'active',
        ]);
        $single->classifications()->create(['type' => 'disease_invalid', 'is_primary' => true]);

        $res = $this->getJson('/api/beneficiaries?type=agent_orange_victim', ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($multi->id, $ids);
        $this->assertNotContains($single->id, $ids);

        // Bộ lọc dùng chung cho stats → con số phải khớp danh sách.
        $stats = $this->getJson('/api/beneficiaries/stats?type=agent_orange_victim', ['X-Organization-Id' => $this->orgA->id]);
        $stats->assertOk();
        $stats->assertJsonPath('data.total', 1);
    }

    /**
     * Người có công đã mất thì tọa độ bản đồ lấy theo thân nhân chính — hồ sơ vẫn cần một điểm
     * để cán bộ đến thăm viếng / chi trả. Tọa độ gốc không bị ghi đè.
     */
    public function test_map_coordinates_fall_back_to_primary_dependent_when_deceased(): void
    {
        Sanctum::actingAs($this->admin);

        $beneficiary = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Liệt sĩ Z', 'gender' => 'male',
            'status' => 'deceased', 'death_date' => '1972-04-30',
            'latitude' => 16.0000000, 'longitude' => 108.0000000,
        ]);
        $primary = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Mẹ liệt sĩ', 'gender' => 'female',
            'latitude' => 16.0678000, 'longitude' => 108.2208000,
        ]);
        $other = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Em ruột', 'gender' => 'male',
            'latitude' => 15.5000000, 'longitude' => 107.5000000,
        ]);
        $beneficiary->dependentRelations()->create([
            'dependent_id' => $primary->id, 'relationship_type' => 'mother', 'is_primary' => true,
        ]);
        $beneficiary->dependentRelations()->create([
            'dependent_id' => $other->id, 'relationship_type' => 'younger_sibling',
        ]);

        $res = $this->getJson("/api/beneficiaries/{$beneficiary->id}", ['X-Organization-Id' => $this->orgA->id]);

        $res->assertOk();
        $res->assertJsonPath('data.map_source', 'primary_dependent');
        $this->assertEquals(16.0678, $res->json('data.map_latitude'));
        $this->assertEquals(108.2208, $res->json('data.map_longitude'));
        $res->assertJsonPath('data.primary_dependent.dependent.full_name', 'Mẹ liệt sĩ');

        // Tọa độ gốc của hồ sơ giữ nguyên, không bị ghi đè.
        $this->assertEquals(16.0, $res->json('data.latitude'));
        $this->assertEquals(108.0, $res->json('data.longitude'));
    }

    public function test_map_coordinates_use_own_when_alive_or_no_primary_dependent(): void
    {
        Sanctum::actingAs($this->admin);

        // Còn sống, có thân nhân chính → vẫn dùng tọa độ của chính mình.
        $alive = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Còn sống', 'gender' => 'male',
            'status' => 'active', 'latitude' => 16.1000000, 'longitude' => 108.1000000,
        ]);
        $dependent = Dependent::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Con', 'gender' => 'male',
            'latitude' => 10.0000000, 'longitude' => 100.0000000,
        ]);
        $alive->dependentRelations()->create([
            'dependent_id' => $dependent->id, 'relationship_type' => 'child', 'is_primary' => true,
        ]);

        $res = $this->getJson("/api/beneficiaries/{$alive->id}", ['X-Organization-Id' => $this->orgA->id]);
        $res->assertJsonPath('data.map_source', 'self');
        $this->assertEquals(16.1, $res->json('data.map_latitude'));

        // Đã mất nhưng chưa chỉ định thân nhân chính → giữ tọa độ gốc.
        $deceased = Beneficiary::create([
            'organization_id' => $this->orgA->id, 'full_name' => 'Đã mất chưa gán', 'gender' => 'male',
            'status' => 'deceased', 'latitude' => 16.2000000, 'longitude' => 108.2000000,
        ]);

        $res2 = $this->getJson("/api/beneficiaries/{$deceased->id}", ['X-Organization-Id' => $this->orgA->id]);
        $res2->assertJsonPath('data.map_source', 'self');
        $this->assertEquals(16.2, $res2->json('data.map_latitude'));
    }

    public function test_store_rejects_multiple_primary_dependents(): void
    {
        Sanctum::actingAs($this->admin);

        $d1 = Dependent::create(['organization_id' => $this->orgA->id, 'full_name' => 'TN 1', 'gender' => 'male']);
        $d2 = Dependent::create(['organization_id' => $this->orgA->id, 'full_name' => 'TN 2', 'gender' => 'female']);

        $res = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Trần Văn N', 'gender' => 'male',
            'dependents' => [
                ['dependent_id' => $d1->id, 'relationship_type' => 'child', 'is_primary' => true],
                ['dependent_id' => $d2->id, 'relationship_type' => 'child', 'is_primary' => true],
            ],
        ], ['X-Organization-Id' => $this->orgA->id]);

        $res->assertUnprocessable();
        $res->assertJsonValidationErrors('dependents');
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
