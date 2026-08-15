<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Beneficiary\Services\BeneficiaryService;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cách ly đa tổ chức. `TenantModel` gắn global scope, nhưng chỉ có test mới chứng minh được
 * mọi đường vào đều đi qua nó.
 */
class BeneficiaryTenantTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private BeneficiaryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['slug' => 'org-a', 'name' => 'Tổ chức A', 'status' => 'active']);
        $this->orgB = Organization::create(['slug' => 'org-b', 'name' => 'Tổ chức B', 'status' => 'active']);
        $this->actingAs(User::factory()->create());

        $this->service = app(BeneficiaryService::class);
    }

    public function test_index_only_returns_current_organization_rows(): void
    {
        setPermissionsTeamId($this->orgA->id);
        Beneficiary::create(['full_name' => 'Của tổ chức A', 'organization_id' => $this->orgA->id]);

        setPermissionsTeamId($this->orgB->id);
        Beneficiary::create(['full_name' => 'Của tổ chức B', 'organization_id' => $this->orgB->id]);

        setPermissionsTeamId($this->orgA->id);
        $names = $this->service->index([], 100)->getCollection()->pluck('full_name')->all();

        $this->assertSame(['Của tổ chức A'], $names);
    }

    public function test_bulk_destroy_cannot_touch_other_organization_rows(): void
    {
        setPermissionsTeamId($this->orgB->id);
        $foreign = Beneficiary::create(['full_name' => 'Của tổ chức B', 'organization_id' => $this->orgB->id]);

        setPermissionsTeamId($this->orgA->id);
        $own = Beneficiary::create(['full_name' => 'Của tổ chức A', 'organization_id' => $this->orgA->id]);

        // Client gửi kèm id của tổ chức khác — global scope làm nó rơi ra ngoài, không cần
        // kiểm thủ công trong service.
        $deleted = $this->service->bulkDestroy([$own->id, $foreign->id]);

        $this->assertSame(1, $deleted);
        $this->assertSoftDeleted('beneficiaries', ['id' => $own->id]);
        $this->assertDatabaseHas('beneficiaries', ['id' => $foreign->id, 'deleted_at' => null]);
    }

    public function test_id_number_unique_is_scoped_per_organization(): void
    {
        setPermissionsTeamId($this->orgA->id);
        $this->service->store(['full_name' => 'Người A', 'id_number' => '048050001234']);

        // Cùng CCCD nhưng khác tổ chức: unique là (organization_id, id_number) nên hợp lệ.
        setPermissionsTeamId($this->orgB->id);
        $b = $this->service->store(['full_name' => 'Người B', 'id_number' => '048050001234']);

        $this->assertSame($this->orgB->id, $b->organization_id);
        $this->assertSame(2, Beneficiary::withoutGlobalScope('organization')
            ->where('id_number', '048050001234')->count());
    }

    public function test_store_restores_soft_deleted_row_with_same_id_number(): void
    {
        setPermissionsTeamId($this->orgA->id);

        $original = $this->service->store(['full_name' => 'Người A', 'id_number' => '048050001234']);
        $this->service->destroy($original);

        // Dòng đã xoá mềm vẫn chiếm chỗ trong unique index — create() thẳng sẽ ném 23000.
        $restored = $this->service->store(['full_name' => 'Người A nhập lại', 'id_number' => '048050001234']);

        $this->assertSame($original->id, $restored->id);
        $this->assertSame('Người A nhập lại', $restored->full_name);
        $this->assertNull($restored->fresh()->deleted_at);
    }

    public function test_soft_deleting_parent_keeps_child_rows(): void
    {
        setPermissionsTeamId($this->orgA->id);

        $beneficiary = Beneficiary::create(['full_name' => 'Người A', 'organization_id' => $this->orgA->id]);
        $type = BeneficiaryType::create([
            'name' => 'Thương binh', 'organization_id' => $this->orgA->id, 'status' => 'active',
        ]);
        $relation = $beneficiary->typeRelations()->create(['beneficiary_type_id' => $type->id]);

        $this->service->destroy($beneficiary);

        // beneficiaries có SoftDeletes nên onDelete('cascade') KHÔNG kích hoạt — dòng con
        // giữ nguyên và khôi phục lại được cùng bản chính.
        $this->assertDatabaseHas('beneficiary_type_relations', [
            'id' => $relation->id,
            'deleted_at' => null,
        ]);
    }
}
