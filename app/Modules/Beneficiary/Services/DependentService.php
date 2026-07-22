<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\DependentEligibilityEnum;
use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use App\Modules\Beneficiary\Exports\DependentExport;
use App\Modules\Beneficiary\Imports\DependentImport;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Core\Support\ExportFilename;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DependentService
{
    private const WITH = ['household', 'dependentRelations.beneficiary', 'creator.media', 'editor.media'];

    public function stats(array $filters): array
    {
        $base = Dependent::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'alive' => (clone $base)->where('is_alive', true)->count(),
            'deceased' => (clone $base)->where('is_alive', false)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Dependent::with(['household', 'creator.media', 'editor.media'])
            ->withCount('beneficiaries')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Dependent $dependent): Dependent
    {
        return $dependent->load(self::WITH);
    }

    public function store(array $validated): Dependent
    {
        $validated['is_alive'] ??= true;

        return Dependent::create($validated)->fresh(self::WITH);
    }

    public function update(Dependent $dependent, array $validated): Dependent
    {
        $dependent->update($validated);

        // is_alive chuyển false -> khóa toàn bộ quan hệ pivot của thân nhân này (trừ truy lĩnh —
        // chưa có cơ chế truy lĩnh riêng ở bản đầu nên khóa toàn bộ theo đúng thiết kế Luồng 3).
        if ($dependent->wasChanged('is_alive') && ! $dependent->is_alive) {
            $dependent->dependentRelations()
                ->where('status', DependentRelationStatusEnum::Active->value)
                ->update(['status' => DependentRelationStatusEnum::Expired->value]);
        }

        return $dependent->load(self::WITH);
    }

    public function destroy(Dependent $dependent): void
    {
        $dependent->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Dependent::whereIn('id', $ids)->delete();
    }

    /**
     * Tạo quan hệ pivot với 1 Beneficiary — validate tuổi >= 18 phải có eligibility_status
     * phù hợp mới cho active, ngược lại tự chuyển expired (đúng Luồng 3 bước 3).
     */
    public function addRelation(Dependent $dependent, array $validated): BeneficiaryDependentRelation
    {
        return DB::transaction(function () use ($dependent, $validated) {
            $status = $this->resolveInitialRelationStatus($dependent);

            $relation = $dependent->dependentRelations()->create([
                ...$validated,
                'status' => $status,
            ]);

            return $relation->load('beneficiary');
        });
    }

    public function removeRelation(Dependent $dependent, int $relationId): void
    {
        $dependent->dependentRelations()->where('id', $relationId)->delete();
    }

    private function resolveInitialRelationStatus(Dependent $dependent): string
    {
        if (! $dependent->is_alive) {
            return DependentRelationStatusEnum::Expired->value;
        }

        $age = $dependent->date_of_birth ? Carbon::parse($dependent->date_of_birth)->age : 0;

        if ($age >= 18 && ! in_array($dependent->eligibility_status, [
            DependentEligibilityEnum::Studying->value,
            DependentEligibilityEnum::DisabledNoWorkCapacity->value,
        ], true)) {
            return DependentRelationStatusEnum::Expired->value;
        }

        return DependentRelationStatusEnum::Active->value;
    }

    public function statusHistories(Dependent $dependent, array $filters, int $limit)
    {
        return $dependent->statusHistories()
            ->with('changer')
            ->filter($filters)
            ->paginate($limit);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new DependentExport($filters), ExportFilename::make('than-nhan'));
    }

    public function import($file): \Illuminate\Support\Collection
    {
        $import = new DependentImport;
        Excel::import($import, $file);

        return $import->failures();
    }
}
