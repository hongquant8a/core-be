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
    private const WITH = ['household', 'classifications.media', 'documents.media', 'creator.media', 'editor.media'];

    public function __construct(
        private HouseholdService $householdService,
        private DependentService $dependentService,
        private MediaService $mediaService,
    ) {}

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
        return Beneficiary::with(['household', 'creator.media', 'editor.media'])
            ->withCount(['dependents', 'documents'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Beneficiary $beneficiary): Beneficiary
    {
        return $beneficiary->load(self::WITH)->loadCount(['dependents', 'documents']);
    }

    /**
     * `household`/`dependents` là lối tắt nhập liệu nhanh cho hồ sơ HOÀN TOÀN MỚI (gộp coarse
     * theo đúng lúc dữ liệu "đi liền một khối" — cán bộ tiếp nhận 1 lần: người có công + hộ +
     * thân nhân đi kèm). Hộ/thân nhân sau khi tạo vẫn là resource độc lập, quản lý tiếp qua
     * `beneficiary-households`/`beneficiary-dependents` như bình thường — không lặp lại lối tắt
     * này ở update(), vì lúc đó hộ/thân nhân đã có vòng đời riêng, sửa qua đúng resource của nó.
     */
    public function store(array $validated): Beneficiary
    {
        return DB::transaction(function () use ($validated) {
            $classifications = $validated['classifications'] ?? [];
            $householdData = $validated['household'] ?? null;
            $dependents = $validated['dependents'] ?? [];
            unset($validated['classifications'], $validated['household'], $validated['dependents']);

            $validated['status'] ??= BeneficiaryStatusEnum::Pending->value;

            if ($householdData && empty($validated['household_id'])) {
                $validated['household_id'] = $this->householdService->store($householdData)->id;
            }

            $beneficiary = Beneficiary::create($validated);

            foreach ($classifications as $row) {
                $beneficiary->classifications()->create($row);
            }

            foreach ($dependents as $dependentRow) {
                $dependent = $this->dependentService->store([
                    ...Arr::except($dependentRow, ['relationship_type']),
                    'household_id' => $dependentRow['household_id'] ?? $beneficiary->household_id,
                ]);

                $this->dependentService->addRelation($dependent, [
                    'beneficiary_id' => $beneficiary->id,
                    'relationship_type' => $dependentRow['relationship_type'],
                    'note' => $dependentRow['note'] ?? null,
                ]);
            }

            return $beneficiary->fresh(self::WITH);
        });
    }

    /**
     * Sync `classifications` theo quy ước COARSE: có `id` = cập nhật, không có `id` = tạo mới,
     * dòng vắng mặt trong payload GIỮ NGUYÊN (không tự xóa) — xóa phải qua `classifications_deleted`
     * tường minh. Xóa TRƯỚC upsert để nhất quán với quy ước chung, dù bảng này không có ràng buộc
     * unique nào bị ảnh hưởng bởi thứ tự.
     */
    public function update(Beneficiary $beneficiary, array $validated): Beneficiary
    {
        return DB::transaction(function () use ($beneficiary, $validated) {
            $classifications = $validated['classifications'] ?? [];
            $deletedIds = $validated['classifications_deleted'] ?? [];
            unset($validated['classifications'], $validated['classifications_deleted']);

            $beneficiary->update($validated);

            if ($deletedIds) {
                $beneficiary->classifications()->whereIn('id', $deletedIds)->delete();
            }

            foreach ($classifications as $row) {
                $attrs = Arr::except($row, ['id']);

                $classification = isset($row['id'])
                    ? tap($beneficiary->classifications()->whereKey($row['id'])->firstOrFail())->update($attrs)
                    : $beneficiary->classifications()->create($attrs);

                // Bất biến "chỉ 1 is_primary=true/beneficiary" (validate ở Request chỉ soát được
                // trong mảng gửi lên) — enforce lại ở đây với CẢ các dòng cũ không nằm trong payload.
                if ($attrs['is_primary'] ?? false) {
                    $beneficiary->classifications()->whereKeyNot($classification->id)->update(['is_primary' => false]);
                }
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
