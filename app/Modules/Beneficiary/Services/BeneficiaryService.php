<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Exports\BeneficiaryExport;
use App\Modules\Beneficiary\Imports\BeneficiaryImport;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use App\Modules\Core\Services\MediaService;
use App\Modules\Core\Support\ExportFilename;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BeneficiaryService
{
    private const WITH = ['household', 'residentialArea', 'classifications.media', 'dependentRelations.dependent', 'documents.media', 'creator.media', 'editor.media'];

    public function __construct(private MediaService $mediaService) {}

    public function stats(array $filters): array
    {
        $base = Beneficiary::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', BeneficiaryStatusEnum::Pending->value)->count(),
            'active' => (clone $base)->where('status', BeneficiaryStatusEnum::Active->value)->count(),
            'deceased' => (clone $base)->where('status', BeneficiaryStatusEnum::Deceased->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Beneficiary::with(['household', 'residentialArea', 'creator.media', 'editor.media'])
            ->withCount(['dependents', 'documents'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Beneficiary $beneficiary): Beneficiary
    {
        return $beneficiary->load(self::WITH)->loadCount(['dependents', 'documents']);
    }

    public function store(array $validated): Beneficiary
    {
        return DB::transaction(function () use ($validated) {
            $classifications = $validated['classifications'] ?? [];
            $dependents = $validated['dependents'] ?? [];
            $documents = $validated['documents'] ?? [];
            unset($validated['classifications'], $validated['dependents'], $validated['documents']);

            $validated['status'] ??= BeneficiaryStatusEnum::Pending->value;

            $beneficiary = Beneficiary::create($validated);

            $beneficiary->classifications()->createMany($classifications);
            $beneficiary->dependentRelations()->createMany($dependents);
            $beneficiary->documents()->createMany($documents);

            return $beneficiary->load(self::WITH);
        });
    }

    /**
     * Mỗi mảng con là TRẠNG THÁI ĐẦY ĐỦ của quan hệ đó: gửi mảng nào thì THAY THẾ toàn bộ mảng
     * đó (xóa hết rồi tạo lại), không gửi thì giữ nguyên. Nhờ vậy `PUT` idempotent và FE chỉ cần
     * gửi đúng bảng đang hiển thị, không phải theo dõi dòng nào đã bị xóa.
     *
     * Xóa qua `get()->each->delete()` chứ không `delete()` thẳng trên query builder: chỉ khi
     * model event chạy thì medialibrary mới dọn file vật lý của tài liệu / quyết định kèm theo.
     */
    public function update(Beneficiary $beneficiary, array $validated): Beneficiary
    {
        return DB::transaction(function () use ($beneficiary, $validated) {
            $sections = Arr::only($validated, ['classifications', 'dependents', 'documents']);
            $validated = Arr::except($validated, ['classifications', 'dependents', 'documents']);

            $beneficiary->update($validated);

            $relations = [
                'classifications' => $beneficiary->classifications(),
                'dependents' => $beneficiary->dependentRelations(),
                'documents' => $beneficiary->documents(),
            ];

            foreach ($sections as $key => $rows) {
                $relations[$key]->get()->each->delete();
                $relations[$key]->createMany($rows ?? []);
            }

            return $beneficiary->load(self::WITH);
        });
    }

    public function destroy(Beneficiary $beneficiary): void
    {
        $beneficiary->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Beneficiary::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Beneficiary::whereIn('id', $ids)->update(['status' => $status]);
    }

    /** Đổi trạng thái hồ sơ (không ghi audit — đã bỏ bảng lịch sử trạng thái). */
    public function changeStatus(Beneficiary $beneficiary, string $status, ?string $deathDate = null): Beneficiary
    {
        $update = ['status' => $status];
        if ($status === BeneficiaryStatusEnum::Deceased->value && $deathDate) {
            $update['death_date'] = $deathDate;
        }

        $beneficiary->update($update);

        return $beneficiary->load(self::WITH);
    }

    /** Đính kèm file quyết định công nhận cho 1 phân loại (collection decision_documents). */
    public function uploadClassificationFiles(BeneficiaryClassification $classification, array $files): BeneficiaryClassification
    {
        $this->mediaService->uploadMany($classification, $files, 'decision_documents', ['disk' => 'public']);

        return $classification->load('media');
    }

    /** Xóa 1 file quyết định của phân loại. */
    public function deleteClassificationFile(BeneficiaryClassification $classification, int $mediaId): void
    {
        $this->mediaService->removeByIds($classification, [$mediaId], 'decision_documents');
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new BeneficiaryExport($filters), ExportFilename::make('nguoi-co-cong'));
    }

    public function import($file): \Illuminate\Support\Collection
    {
        $import = new BeneficiaryImport;
        Excel::import($import, $file);

        return $import->failures();
    }
}
