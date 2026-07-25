<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Models\BeneficiaryDocument;
use App\Modules\Core\Services\MediaService;
use Illuminate\Support\Facades\DB;

class BeneficiaryDocumentService
{
    private const WITH = ['media', 'creator.media', 'editor.media'];

    public function __construct(private MediaService $mediaService) {}

    public function index(array $filters, int $limit)
    {
        return BeneficiaryDocument::with(self::WITH)
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(BeneficiaryDocument $document): BeneficiaryDocument
    {
        return $document->load(self::WITH);
    }

    public function store(array $validated): BeneficiaryDocument
    {
        return DB::transaction(function () use ($validated) {
            $files = $validated['files'] ?? [];
            unset($validated['files']);

            $document = BeneficiaryDocument::create($validated);

            if ($files) {
                $this->mediaService->uploadMany($document, $files, 'files', ['disk' => 'public']);
            }

            return $document->load(self::WITH);
        });
    }

    public function update(BeneficiaryDocument $document, array $validated): BeneficiaryDocument
    {
        return DB::transaction(function () use ($document, $validated) {
            $files = $validated['files'] ?? [];
            $deletedFileIds = $validated['files_deleted'] ?? [];
            unset($validated['files'], $validated['files_deleted']);

            $document->update($validated);

            if ($deletedFileIds) {
                $this->mediaService->removeByIds($document, $deletedFileIds, 'files');
            }
            if ($files) {
                $this->mediaService->uploadMany($document, $files, 'files', ['disk' => 'public']);
            }

            return $document->load(self::WITH);
        });
    }

    public function destroy(BeneficiaryDocument $document): void
    {
        $document->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        BeneficiaryDocument::whereIn('id', $ids)->delete();
    }
}
