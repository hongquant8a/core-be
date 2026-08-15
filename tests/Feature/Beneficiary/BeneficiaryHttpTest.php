<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryRelationship;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test đi qua TẦNG HTTP thật: route → permission → FormRequest → Service → Resource.
 *
 * Bốn file test kia gọi thẳng Service nên bỏ lọt cả tầng validate. Khoảng trống đó đã để lọt
 * một TypeError trong `activeCatalogRule()` mà chỉ `scribe:generate` mới phát hiện — lớp test
 * này tồn tại để lần sau bắt được ngay.
 */
class BeneficiaryHttpTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);

        $this->user = User::factory()->create();
        $this->grantAllBeneficiaryPermissions($this->user);

        Sanctum::actingAs($this->user);
    }

    /** Cấp trọn nhóm quyền của module — test này kiểm luồng nghiệp vụ, không kiểm phân quyền. */
    private function grantAllBeneficiaryPermissions(User $user): void
    {
        $resources = [
            'beneficiaries' => ['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'export', 'import'],
            'beneficiary-type-relations' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'],
            'beneficiary-dependents' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'],
            'beneficiary-documents' => ['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'],
            'beneficiary-types' => ['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import'],
            'beneficiary-residential-areas' => ['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import'],
            'beneficiary-relationships' => ['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import'],
        ];

        $role = Role::firstOrCreate(
            ['name' => 'beneficiary-tester', 'guard_name' => 'web'],
            ['organization_id' => $this->org->id]
        );

        foreach ($resources as $resource => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => "{$resource}.{$action}",
                    'guard_name' => 'web',
                ]);
                $role->givePermissionTo($permission);
            }
        }

        $user->assignRole($role);
    }

    private function headers(): array
    {
        return ['X-Organization-Id' => (string) $this->org->id];
    }

    private function makeArea(string $status = 'active'): BeneficiaryResidentialArea
    {
        return BeneficiaryResidentialArea::create([
            'name' => 'Tổ dân phố '.fake()->unique()->numberBetween(1, 9999),
            'organization_id' => $this->org->id,
            'status' => $status,
        ]);
    }

    private function makeType(string $name = 'Thương binh'): BeneficiaryType
    {
        return BeneficiaryType::create([
            'name' => $name, 'organization_id' => $this->org->id, 'status' => 'active',
        ]);
    }

    public function test_store_beneficiary_through_http(): void
    {
        $area = $this->makeArea();

        $response = $this->postJson('/api/beneficiaries', [
            'full_name' => 'Nguyễn Văn A',
            'birth_date' => '1950-03-15',
            'gender' => 'male',
            'id_number' => '048050001234',
            'residential_area_id' => $area->id,
            'latitude' => 16.0678,
            'longitude' => 108.2208,
        ], $this->headers());

        $response->assertOk()->assertJsonPath('success', true);
        $response->assertJsonPath('data.full_name', 'Nguyễn Văn A');

        // birth_year suy ra từ birth_date, không cần client gửi.
        $response->assertJsonPath('data.birth_year', 1950);

        // lock_version phải là ISO8601 để dùng làm token khoá lạc quan.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $response->json('data.lock_version')
        );
    }

    public function test_inactive_catalog_is_rejected_on_store(): void
    {
        $inactiveArea = $this->makeArea(CatalogStatusEnum::Inactive->value);

        $this->postJson('/api/beneficiaries', [
            'full_name' => 'Nguyễn Văn A',
            'residential_area_id' => $inactiveArea->id,
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('residential_area_id');
    }

    public function test_latitude_without_longitude_is_rejected(): void
    {
        $this->postJson('/api/beneficiaries', [
            'full_name' => 'Nguyễn Văn A',
            'latitude' => 16.0678,
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('latitude');
    }

    public function test_birth_year_conflicting_with_birth_date_is_rejected(): void
    {
        $this->postJson('/api/beneficiaries', [
            'full_name' => 'Nguyễn Văn A',
            'birth_date' => '1950-03-15',
            'birth_year' => 1960,
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('birth_year');
    }

    public function test_save_full_through_http_with_json_fields(): void
    {
        $type = $this->makeType();
        $relationship = BeneficiaryRelationship::create([
            'name' => 'Vợ', 'organization_id' => $this->org->id, 'status' => 'active',
        ]);

        $response = $this->post('/api/beneficiaries/save-full', [
            'full_name' => 'Nguyễn Văn A',
            // Danh sách con đi qua chuỗi JSON, KHÔNG phải mảng lồng FormData.
            'type_relations_json' => json_encode([
                ['id' => null, 'beneficiary_type_id' => $type->id, 'is_primary' => true],
            ]),
            'dependents_json' => json_encode([
                ['id' => null, 'full_name' => 'Trần Thị B', 'relationship_id' => $relationship->id],
            ]),
            'documents_json' => json_encode([
                ['id' => null, 'name' => 'Quyết định trợ cấp'],
            ]),
        ], $this->headers());

        $response->assertOk()->assertJsonPath('success', true);

        $beneficiary = Beneficiary::firstWhere('full_name', 'Nguyễn Văn A');
        $this->assertSame(1, $beneficiary->typeRelations()->count());
        $this->assertSame(1, $beneficiary->dependents()->count());
        $this->assertSame(1, $beneficiary->documents()->count());
    }

    public function test_save_full_rejects_malformed_json(): void
    {
        $this->post('/api/beneficiaries/save-full', [
            'full_name' => 'Nguyễn Văn A',
            'type_relations_json' => '{không phải json}',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('type_relations_json');
    }

    public function test_save_full_requires_lock_version_on_update(): void
    {
        $beneficiary = Beneficiary::create([
            'full_name' => 'Nguyễn Văn A', 'organization_id' => $this->org->id,
        ]);

        $this->post("/api/beneficiaries/{$beneficiary->id}/save-full", [
            'full_name' => 'Sửa đè',
        ], $this->headers())
            ->assertStatus(422)
            ->assertJsonValidationErrors('lock_version');
    }

    public function test_stale_lock_version_returns_409(): void
    {
        $beneficiary = Beneficiary::create([
            'full_name' => 'Nguyễn Văn A', 'organization_id' => $this->org->id,
        ]);

        $this->post("/api/beneficiaries/{$beneficiary->id}/save-full", [
            'full_name' => 'Sửa đè',
            'lock_version' => $beneficiary->updated_at->copy()->subMinute()->toIso8601String(),
        ], $this->headers())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'STALE_RECORD');
    }

    public function test_deleting_catalog_in_use_returns_409(): void
    {
        $area = $this->makeArea();
        Beneficiary::create([
            'full_name' => 'Nguyễn Văn A',
            'residential_area_id' => $area->id,
            'organization_id' => $this->org->id,
        ]);

        $this->deleteJson("/api/beneficiary-residential-areas/{$area->id}", [], $this->headers())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'CATALOG_IN_USE')
            ->assertJsonPath('errors.usage_count', 1);
    }

    public function test_catalog_change_status_and_filter(): void
    {
        $type = $this->makeType('Thương binh');

        $this->patchJson("/api/beneficiary-types/{$type->id}/status", [
            'status' => 'inactive',
        ], $this->headers())->assertOk();

        // Dropdown lọc active phải không còn thấy mục vừa ngừng dùng.
        $active = $this->getJson('/api/beneficiary-types?status=active&limit=-1', $this->headers());
        $active->assertOk();
        $this->assertEmpty($active->json('data'));

        // Màn quản trị không truyền status thì vẫn thấy.
        $all = $this->getJson('/api/beneficiary-types?limit=-1', $this->headers());
        $this->assertCount(1, $all->json('data'));
    }

    public function test_sub_resource_rejects_row_of_another_beneficiary(): void
    {
        $type = $this->makeType();

        $owner = Beneficiary::create(['full_name' => 'Chủ hồ sơ', 'organization_id' => $this->org->id]);
        $other = Beneficiary::create(['full_name' => 'Hồ sơ khác', 'organization_id' => $this->org->id]);

        $relation = $owner->typeRelations()->create(['beneficiary_type_id' => $type->id]);

        // scopeBindings(): {typeRelation} bắt buộc thuộc về {beneficiary} trên URL.
        $this->getJson(
            "/api/beneficiaries/{$other->id}/type-relations/{$relation->id}",
            $this->headers()
        )->assertNotFound();
    }

    public function test_enum_endpoint_returns_two_keys(): void
    {
        $this->getJson('/api/beneficiary-enums', $this->headers())
            ->assertOk()
            ->assertJsonCount(3, 'data.gender')
            ->assertJsonCount(2, 'data.catalog_status')
            ->assertJsonPath('data.catalog_status.0.label', 'Đang sử dụng');
    }

    public function test_stats_has_no_status_buckets(): void
    {
        $response = $this->getJson('/api/beneficiaries/stats', $this->headers())->assertOk();

        $data = $response->json('data');

        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('without_coordinates', $data);

        // Module không có cột status nên stats không được đếm theo trạng thái.
        $this->assertArrayNotHasKey('active', $data);
        $this->assertArrayNotHasKey('inactive', $data);
    }

    public function test_status_endpoints_do_not_exist_for_beneficiaries(): void
    {
        $beneficiary = Beneficiary::create([
            'full_name' => 'Nguyễn Văn A', 'organization_id' => $this->org->id,
        ]);

        $this->patchJson("/api/beneficiaries/{$beneficiary->id}/status", ['status' => 'inactive'], $this->headers())
            ->assertNotFound();

        $this->patchJson('/api/beneficiaries/bulk-status', ['ids' => [$beneficiary->id], 'status' => 'inactive'], $this->headers())
            ->assertNotFound();
    }
}
