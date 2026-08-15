<?php

namespace Tests\Feature\Beneficiary;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryType;
use App\Modules\Beneficiary\Services\BeneficiaryTypeRelationService;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dạng D — UNIQUE(beneficiary_id, beneficiary_type_id) cộng SoftDeletes là cái bẫy chính,
 * cộng với luồng media snapshot → commit → ghi → xoá.
 */
class BeneficiaryTypeRelationTest extends TestCase
{
    use RefreshDatabase;

    private BeneficiaryTypeRelationService $service;

    private Organization $org;

    private Beneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->org = Organization::firstOrCreate(['slug' => 'test'], ['name' => 'Test', 'status' => 'active']);
        setPermissionsTeamId($this->org->id);
        $this->actingAs(User::factory()->create());

        $this->service = app(BeneficiaryTypeRelationService::class);
        $this->beneficiary = Beneficiary::create([
            'full_name' => 'Nguyễn Văn A',
            'organization_id' => $this->org->id,
        ]);
    }

    private function makeType(string $name = 'Thương binh'): BeneficiaryType
    {
        return BeneficiaryType::create([
            'name' => $name, 'organization_id' => $this->org->id, 'status' => 'active',
        ]);
    }

    public function test_reassigning_deleted_type_restores_row_with_attachments(): void
    {
        $type = $this->makeType();

        $relation = $this->service->store($this->beneficiary, [
            'beneficiary_type_id' => $type->id,
            'attachments' => [UploadedFile::fake()->image('quyet-dinh.jpg')],
        ]);

        $originalId = $relation->id;
        $this->assertCount(1, $relation->getMedia(\App\Modules\Beneficiary\Models\BeneficiaryTypeRelation::MEDIA_COLLECTION));

        $this->service->destroy($relation);
        $this->assertSoftDeleted('beneficiary_type_relations', ['id' => $originalId]);

        // Gán lại đúng loại đó: create() thẳng sẽ ném SQLSTATE 23000 vì dòng đã xoá mềm vẫn
        // chiếm chỗ trong unique index. Service phải restore, và tệp cũ quay lại nguyên vẹn.
        $restored = $this->service->store($this->beneficiary, ['beneficiary_type_id' => $type->id]);

        $this->assertSame($originalId, $restored->id);
        $this->assertNull($restored->fresh()->deleted_at);
        $this->assertCount(1, $restored->getMedia(\App\Modules\Beneficiary\Models\BeneficiaryTypeRelation::MEDIA_COLLECTION));
    }

    public function test_sync_attachments_removes_only_files_not_kept(): void
    {
        $type = $this->makeType();
        $collection = \App\Modules\Beneficiary\Models\BeneficiaryTypeRelation::MEDIA_COLLECTION;

        $relation = $this->service->store($this->beneficiary, [
            'beneficiary_type_id' => $type->id,
            'attachments' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ]);

        $mediaIds = $relation->getMedia($collection)->pluck('id')->all();
        $this->assertCount(2, $mediaIds);

        $this->service->update($relation, [
            'sync_attachments' => true,
            'keep_media_ids' => [$mediaIds[0]],
        ]);

        $remaining = $relation->fresh()->getMedia($collection)->pluck('id')->all();
        $this->assertSame([$mediaIds[0]], $remaining);
    }

    public function test_missing_sync_flag_keeps_all_existing_files(): void
    {
        $type = $this->makeType();
        $collection = \App\Modules\Beneficiary\Models\BeneficiaryTypeRelation::MEDIA_COLLECTION;

        $relation = $this->service->store($this->beneficiary, [
            'beneficiary_type_id' => $type->id,
            'attachments' => [UploadedFile::fake()->image('a.jpg')],
        ]);

        // Không có cờ sync_attachments → request không quản lý tệp → giữ nguyên toàn bộ.
        $this->service->update($relation, ['is_primary' => true]);

        $this->assertCount(1, $relation->fresh()->getMedia($collection));
    }

    public function test_setting_primary_demotes_the_previous_one(): void
    {
        $first = $this->service->store($this->beneficiary, [
            'beneficiary_type_id' => $this->makeType('Thương binh')->id,
            'is_primary' => true,
        ]);

        $second = $this->service->store($this->beneficiary, [
            'beneficiary_type_id' => $this->makeType('Bệnh binh')->id,
            'is_primary' => true,
        ]);

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame(1, $this->beneficiary->typeRelations()->where('is_primary', true)->count());
    }

    public function test_child_row_touches_parent_updated_at(): void
    {
        $before = $this->beneficiary->updated_at->copy()->subMinutes(5);

        Beneficiary::withoutGlobalScope('organization')
            ->whereKey($this->beneficiary->id)
            ->update(['updated_at' => $before]);

        // $touches = ['beneficiary'] là cơ chế DUY NHẤT bắt xung đột giữa màn sub-resource
        // và màn save-full.
        $this->service->store($this->beneficiary, ['beneficiary_type_id' => $this->makeType()->id]);

        $this->assertTrue($this->beneficiary->fresh()->updated_at->greaterThan($before));
    }
}
