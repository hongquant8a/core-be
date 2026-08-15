<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Beneficiary\Exceptions\CatalogInUseException;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Beneficiary\Services\BeneficiaryCatalogService;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Danh mục là chỗ duy nhất trong module có cột `status`. Ba hành vi cốt lõi: xoá bị chặn khi
 * đang dùng, `inactive` không phá dữ liệu cũ, và nhập lại tên đã xoá thì khôi phục.
 */
class BeneficiaryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private BeneficiaryCatalogService $service;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->actingAs(User::factory()->create());

        $this->service = app(BeneficiaryCatalogService::class);
    }

    public function test_deleting_catalog_in_use_is_blocked_with_usage_count(): void
    {
        $area = $this->service->store(BeneficiaryResidentialArea::class, ['name' => 'Tổ dân phố 5']);

        Beneficiary::create([
            'full_name' => 'Nguyễn Văn A',
            'residential_area_id' => $area->id,
            'organization_id' => $this->org->id,
        ]);

        try {
            $this->service->destroy($area);
            $this->fail('Xoá danh mục đang được dùng lẽ ra phải bị chặn.');
        } catch (CatalogInUseException $e) {
            // Thông báo phải nói luôn đường đi tiếp, không chỉ báo lỗi cụt.
            $this->assertStringContainsString('1 bản ghi sử dụng', $e->getMessage());
            $this->assertStringContainsString('Ngừng sử dụng', $e->getMessage());
        }

        $this->assertDatabaseHas('beneficiary_residential_areas', [
            'id' => $area->id,
            'deleted_at' => null,
        ]);
    }

    public function test_unused_catalog_can_be_deleted(): void
    {
        $area = $this->service->store(BeneficiaryResidentialArea::class, ['name' => 'Tổ dân phố 9']);

        $this->service->destroy($area);

        $this->assertSoftDeleted('beneficiary_residential_areas', ['id' => $area->id]);
    }

    public function test_inactive_catalog_keeps_existing_references_intact(): void
    {
        $area = $this->service->store(BeneficiaryResidentialArea::class, ['name' => 'Tổ dân phố 5']);

        $beneficiary = Beneficiary::create([
            'full_name' => 'Nguyễn Văn A',
            'residential_area_id' => $area->id,
            'organization_id' => $this->org->id,
        ]);

        $this->service->changeStatus($area, CatalogStatusEnum::Inactive->value);

        // inactive CHỈ chặn gán mới — hồ sơ cũ giữ nguyên tham chiếu và đọc được bình thường.
        $this->assertSame($area->id, $beneficiary->fresh()->residential_area_id);
        $this->assertSame('Tổ dân phố 5', $beneficiary->fresh()->residentialArea->name);
    }

    public function test_recreating_soft_deleted_name_restores_the_row(): void
    {
        $type = $this->service->store(BeneficiaryType::class, ['name' => 'Thương binh', 'note' => 'ghi chú cũ']);
        $originalId = $type->id;

        $this->service->destroy($type);
        $this->assertSoftDeleted('beneficiary_types', ['id' => $originalId]);

        // UNIQUE(organization_id, name) + SoftDeletes: dòng đã xoá vẫn chiếm chỗ trong index
        // nên create() thẳng sẽ ném 23000. Service phải restore thay vì tạo mới.
        $restored = $this->service->store(BeneficiaryType::class, ['name' => 'Thương binh', 'note' => 'ghi chú mới']);

        $this->assertSame($originalId, $restored->id);
        $this->assertSame('ghi chú mới', $restored->note);
        $this->assertNull($restored->fresh()->deleted_at);
    }

    public function test_find_active_id_by_name_ignores_case_and_inactive_rows(): void
    {
        $area = $this->service->store(BeneficiaryResidentialArea::class, ['name' => 'Tổ Dân Phố 5']);

        // Import tra ngược theo tên, không phân biệt hoa/thường và bỏ khoảng trắng thừa.
        $this->assertSame(
            $area->id,
            $this->service->findActiveIdByName(BeneficiaryResidentialArea::class, '  tổ dân phố 5 ')
        );

        $this->service->changeStatus($area, CatalogStatusEnum::Inactive->value);

        // Mục đã ngừng sử dụng thì import coi như không khớp — nhất quán với dropdown.
        $this->assertNull(
            $this->service->findActiveIdByName(BeneficiaryResidentialArea::class, 'Tổ Dân Phố 5')
        );
    }

    public function test_reorder_updates_sort_order(): void
    {
        $a = $this->service->store(BeneficiaryType::class, ['name' => 'A', 'sort_order' => 1]);
        $b = $this->service->store(BeneficiaryType::class, ['name' => 'B', 'sort_order' => 2]);

        $this->service->reorder(BeneficiaryType::class, [
            ['id' => $a->id, 'sort_order' => 2],
            ['id' => $b->id, 'sort_order' => 1],
        ]);

        $this->assertSame(2, $a->fresh()->sort_order);
        $this->assertSame(1, $b->fresh()->sort_order);
    }
}
