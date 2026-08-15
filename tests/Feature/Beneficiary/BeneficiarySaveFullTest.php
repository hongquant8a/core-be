<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryRelationship;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Beneficiary\Services\BeneficiaryService;
use App\Modules\Core\Exceptions\StaleRecordException;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Endpoint gộp — ba ca dễ hỏng nhất: xoá bằng mảng rỗng, giữ nguyên khi vắng mặt, và
 * optimistic lock.
 */
class BeneficiarySaveFullTest extends TestCase
{
    use RefreshDatabase;

    private BeneficiaryService $service;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->actingAs(User::factory()->create());

        $this->service = app(BeneficiaryService::class);
    }

    private function makeType(string $name = 'Thương binh'): BeneficiaryType
    {
        return BeneficiaryType::create([
            'name' => $name,
            'organization_id' => $this->org->id,
            'status' => 'active',
        ]);
    }

    public function test_save_full_creates_beneficiary_with_all_child_lists(): void
    {
        $type = $this->makeType();
        $relationship = BeneficiaryRelationship::create([
            'name' => 'Vợ', 'organization_id' => $this->org->id, 'status' => 'active',
        ]);

        $beneficiary = $this->service->saveFull(null, [
            'full_name' => 'Nguyễn Văn A',
            'birth_date' => '1950-03-15',
            'type_relations' => [
                ['beneficiary_type_id' => $type->id, 'is_primary' => true],
            ],
            'dependents' => [
                ['full_name' => 'Trần Thị B', 'relationship_id' => $relationship->id, 'is_primary' => true],
            ],
            'documents' => [
                ['name' => 'Quyết định trợ cấp'],
            ],
        ]);

        $this->assertSame('Nguyễn Văn A', $beneficiary->full_name);
        $this->assertSame(1, $beneficiary->typeRelations()->count());
        $this->assertSame(1, $beneficiary->dependents()->count());
        $this->assertSame(1, $beneficiary->documents()->count());

        // birth_year phải tự suy ra từ birth_date, không cần client gửi.
        $this->assertSame(1950, $beneficiary->birth_year);
    }

    public function test_empty_array_deletes_all_rows_of_that_relation(): void
    {
        $type = $this->makeType();

        $beneficiary = $this->service->saveFull(null, [
            'full_name' => 'Nguyễn Văn A',
            'type_relations' => [['beneficiary_type_id' => $type->id]],
            'documents' => [['name' => 'Tài liệu 1']],
        ]);

        $this->assertSame(1, $beneficiary->typeRelations()->count());

        // "[]" = xoá hết dòng con của quan hệ đó.
        $updated = $this->service->saveFull($beneficiary, [
            'full_name' => 'Nguyễn Văn A',
            'lock_version' => $beneficiary->updated_at->toIso8601String(),
            'type_relations' => [],
        ]);

        $this->assertSame(0, $updated->typeRelations()->count());

        // documents KHÔNG gửi lên → giữ nguyên, không bị xoá lây.
        $this->assertSame(1, $updated->documents()->count());
    }

    public function test_stale_lock_version_is_rejected(): void
    {
        $beneficiary = $this->service->saveFull(null, ['full_name' => 'Nguyễn Văn A']);

        $staleToken = $beneficiary->updated_at->copy()->subMinute()->toIso8601String();

        $this->expectException(StaleRecordException::class);

        $this->service->saveFull($beneficiary, [
            'full_name' => 'Sửa đè',
            'lock_version' => $staleToken,
        ]);
    }

    public function test_deleting_only_child_rows_still_bumps_parent_updated_at(): void
    {
        $type = $this->makeType();

        $beneficiary = $this->service->saveFull(null, [
            'full_name' => 'Nguyễn Văn A',
            'type_relations' => [['beneficiary_type_id' => $type->id]],
        ]);

        $before = $beneficiary->updated_at;

        // Lùi updated_at để phân biệt được — whereNotIn xoá qua Query Builder nên không
        // kích hoạt $touches, service phải touch() tay.
        Beneficiary::withoutGlobalScope('organization')
            ->whereKey($beneficiary->id)
            ->update(['updated_at' => $before->copy()->subMinutes(5)]);

        $reloaded = Beneficiary::find($beneficiary->id);

        $updated = $this->service->saveFull($reloaded, [
            'full_name' => 'Nguyễn Văn A',
            'lock_version' => $reloaded->updated_at->toIso8601String(),
            'type_relations' => [],
        ]);

        $this->assertTrue($updated->updated_at->greaterThan($reloaded->updated_at));
    }

    public function test_at_most_one_primary_row_survives(): void
    {
        $type1 = $this->makeType('Thương binh');
        $type2 = $this->makeType('Bệnh binh');

        // Client gửi hai dòng cùng is_primary = true → dòng ĐẦU TIÊN thắng.
        $beneficiary = $this->service->saveFull(null, [
            'full_name' => 'Nguyễn Văn A',
            'type_relations' => [
                ['beneficiary_type_id' => $type1->id, 'is_primary' => true],
                ['beneficiary_type_id' => $type2->id, 'is_primary' => true],
            ],
        ]);

        $primaries = $beneficiary->typeRelations()->where('is_primary', true)->get();

        $this->assertCount(1, $primaries);
        $this->assertSame($type1->id, $primaries->first()->beneficiary_type_id);
    }
}
