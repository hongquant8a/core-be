<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryTypeRelation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Đối tượng của người có công — dạng D (n–n có thuộc tính), xử lý y hệt dạng A.
 */
class BeneficiaryTypeRelationService
{
    private const FILLABLE = ['beneficiary_type_id', 'is_primary'];

    private const MEDIA_COLLECTION = BeneficiaryTypeRelation::MEDIA_COLLECTION;

    /**
     * Bốn thứ trong danh sách này đều bắt buộc, thiếu cái nào cũng hỏng một kiểu khác:
     *   - 'media'            → Resource gọi getMedia() từng dòng, thiếu là N+1
     *   - 'beneficiary'      → Resource xuất parent_lock_version (quy tắc 4); thiếu thì
     *                          whenLoaded trả MissingValue và key biến mất khỏi response
     *   - 'beneficiaryType'  → hiển thị tên loại đối tượng, thiếu là N+1
     *   - 'creator.media'    → FormatsUserSummary gọi getFirstMedia('avatars') trên user
     *     'editor.media'
     */
    private const WITH = ['media', 'beneficiary', 'beneficiaryType', 'creator.media', 'editor.media'];

    public function index(Beneficiary $beneficiary, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return $beneficiary->typeRelations()
            ->with(self::WITH)
            ->when($filters['beneficiary_type_id'] ?? null, fn ($q, $id) => $q->where('beneficiary_type_id', $id))
            ->when(
                array_key_exists('is_primary', $filters) && $filters['is_primary'] !== null,
                fn ($q) => $q->where('is_primary', filter_var($filters['is_primary'], FILTER_VALIDATE_BOOLEAN))
            )
            ->orderByDesc('is_primary')
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(BeneficiaryTypeRelation $typeRelation): BeneficiaryTypeRelation
    {
        return $typeRelation->load(self::WITH);
    }

    /**
     * UNIQUE(beneficiary_id, beneficiary_type_id) + SoftDeletes: gán lại một loại đối tượng
     * đã từng bị xoá phải là khôi phục dòng cũ (kèm tệp đính kèm), không phải 23000.
     */
    public function store(Beneficiary $beneficiary, array $data): BeneficiaryTypeRelation
    {
        $attributes = Arr::only($data, self::FILLABLE);

        $typeRelation = DB::transaction(function () use ($beneficiary, $attributes) {
            $existing = $beneficiary->typeRelations()
                ->withTrashed()
                ->where('beneficiary_type_id', $attributes['beneficiary_type_id'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($attributes);
                $item = $existing;
            } else {
                $item = $beneficiary->typeRelations()->create($attributes);
            }

            $this->demoteOtherPrimaries($beneficiary, $item);

            return $item;
        });

        // Upload SAU commit — thống nhất một quy tắc với saveFull, không phải nhớ hai
        // trường hợp. Lỗi upload để lại dòng DB không tệp, sửa được bằng UI; upload trong
        // transaction rồi rollback để lại tệp rác không ai dọn.
        $this->uploadAttachments($typeRelation, $data['attachments'] ?? []);

        return $typeRelation->load(self::WITH);
    }

    /**
     * THỨ TỰ BA BƯỚC KHÔNG ĐƯỢC ĐỔI:
     *   1. snapshot media TRƯỚC khi upload
     *   2. commit transaction
     *   3. mới xoá tệp
     *
     * Xoá tệp vật lý KHÔNG rollback theo transaction.
     */
    public function update(BeneficiaryTypeRelation $typeRelation, array $data): BeneficiaryTypeRelation
    {
        // Snapshot TRƯỚC khi upload: chụp sau thì tệp vừa upload cũng nằm trong danh sách
        // đối chiếu, mà nó không có trong keep_media_ids → bị xoá ngay lập tức.
        $existing = $typeRelation->getMedia(self::MEDIA_COLLECTION);

        DB::transaction(function () use ($typeRelation, $data) {
            $typeRelation->update(Arr::only($data, self::FILLABLE));
            $this->demoteOtherPrimaries($typeRelation->beneficiary, $typeRelation);
        });

        $this->uploadAttachments($typeRelation, $data['attachments'] ?? []);

        // Không có cờ → request không quản lý tệp → giữ nguyên toàn bộ tệp cũ.
        if ($data['sync_attachments'] ?? false) {
            $keep = array_map('intval', $data['keep_media_ids'] ?? []);

            // Duyệt trên $existing (media của CHÍNH record này) nên client gửi id lạ cũng
            // không xoá được tệp của bản ghi khác.
            $existing->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                ->each->delete();       // ngoài transaction
        }

        return $typeRelation->load(self::WITH);
    }

    /**
     * Xoá mềm. Tệp đính kèm giữ nguyên trên storage, phục hồi được khi restore bản ghi —
     * bắt buộc với dữ liệu có giá trị pháp lý.
     *
     * Dòng đang là chính bị xoá thì KHÔNG tự thăng dòng khác lên; cán bộ chọn lại. Tự động
     * chọn hộ dễ tạo dữ liệu sai mà không ai biết.
     */
    public function destroy(BeneficiaryTypeRelation $typeRelation): void
    {
        $typeRelation->delete();
    }

    /** Chạy qua quan hệ nên không đụng được dòng của hồ sơ khác. */
    public function bulkDestroy(Beneficiary $beneficiary, array $ids): int
    {
        $deleted = $beneficiary->typeRelations()->whereIn('id', $ids)->delete();

        // Query Builder không kích hoạt $touches — phải touch tay, nếu không optimistic
        // lock của màn hình đang mở sẽ mù đúng vào thao tác xoá.
        $beneficiary->touch();

        return $deleted;
    }

    /**
     * "Nhiều nhất một dòng chính" — enforce ở Service chứ không bằng unique index, vì
     * "không có dòng nào là chính" cũng là trạng thái hợp lệ.
     */
    private function demoteOtherPrimaries(Beneficiary $beneficiary, BeneficiaryTypeRelation $primary): void
    {
        if (! $primary->is_primary) {
            return;
        }

        $beneficiary->typeRelations()
            ->whereKeyNot($primary->getKey())
            ->where('is_primary', true)
            ->update(['is_primary' => false, 'updated_by' => auth()->id(), 'updated_at' => now()]);
    }

    /**
     * isValid() bắt buộc: tệp hỏng giữa đường truyền vẫn tới đây dưới dạng UploadedFile,
     * đưa thẳng vào spatie sẽ ném lỗi giữa luồng đã commit.
     */
    private function uploadAttachments(BeneficiaryTypeRelation $typeRelation, array $files): void
    {
        foreach ($files as $file) {
            if ($file->isValid()) {
                $typeRelation->addMedia($file)->toMediaCollection(self::MEDIA_COLLECTION);
            }
        }
    }
}
