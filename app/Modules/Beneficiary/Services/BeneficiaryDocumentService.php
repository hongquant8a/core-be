<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Tài liệu hồ sơ — dạng A (1–n có tệp).
 */
class BeneficiaryDocumentService
{
    private const FILLABLE = ['name', 'note'];

    private const MEDIA_COLLECTION = BeneficiaryDocument::MEDIA_COLLECTION;

    private const WITH = ['media', 'beneficiary', 'creator.media', 'editor.media'];

    public function index(Beneficiary $beneficiary, array $filters = [], int $limit = 10): LengthAwarePaginator
    {
        return $beneficiary->documents()
            ->with(self::WITH)
            ->when($filters['search'] ?? null, fn ($q, $kw) => $q->where('name', 'like', "%{$kw}%"))
            ->when($filters['from_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to_date'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderBy($filters['sort_by'] ?? 'id', $filters['sort_order'] ?? 'desc')
            ->paginate($limit);
    }

    public function show(BeneficiaryDocument $document): BeneficiaryDocument
    {
        return $document->load(self::WITH);
    }

    public function store(Beneficiary $beneficiary, array $data): BeneficiaryDocument
    {
        $document = DB::transaction(
            fn () => $beneficiary->documents()->create(Arr::only($data, self::FILLABLE))
        );

        // Upload SAU commit — cùng quy tắc với saveFull.
        $this->uploadFiles($document, $data['files'] ?? []);

        return $document->load(self::WITH);
    }

    /**
     * THỨ TỰ BA BƯỚC KHÔNG ĐƯỢC ĐỔI: snapshot → commit → xoá tệp.
     * Xoá tệp vật lý KHÔNG rollback theo transaction.
     */
    public function update(BeneficiaryDocument $document, array $data): BeneficiaryDocument
    {
        // Snapshot TRƯỚC khi upload: chụp sau thì tệp vừa upload cũng nằm trong danh sách
        // đối chiếu, mà nó không có trong keep_media_ids → bị xoá ngay lập tức.
        $existing = $document->getMedia(self::MEDIA_COLLECTION);

        DB::transaction(fn () => $document->update(Arr::only($data, self::FILLABLE)));

        $this->uploadFiles($document, $data['files'] ?? []);

        // Không có cờ → request không quản lý tệp → giữ nguyên toàn bộ tệp cũ.
        if ($data['sync_attachments'] ?? false) {
            $keep = array_map('intval', $data['keep_media_ids'] ?? []);

            $existing->reject(fn ($media) => in_array((int) $media->id, $keep, true))
                ->each->delete();       // ngoài transaction
        }

        return $document->load(self::WITH);
    }

    public function destroy(BeneficiaryDocument $document): void
    {
        $document->delete();
    }

    public function bulkDestroy(Beneficiary $beneficiary, array $ids): int
    {
        $deleted = $beneficiary->documents()->whereIn('id', $ids)->delete();

        // Query Builder không kích hoạt $touches — phải touch tay.
        $beneficiary->touch();

        return $deleted;
    }

    /**
     * isValid() bắt buộc: tệp hỏng giữa đường truyền vẫn tới đây dưới dạng UploadedFile,
     * đưa thẳng vào spatie sẽ ném lỗi giữa luồng đã commit.
     */
    private function uploadFiles(BeneficiaryDocument $document, array $files): void
    {
        foreach ($files as $file) {
            if ($file->isValid()) {
                $document->addMedia($file)->toMediaCollection(self::MEDIA_COLLECTION);
            }
        }
    }
}
