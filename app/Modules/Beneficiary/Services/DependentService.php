<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Exports\DependentExport;
use App\Modules\Beneficiary\Imports\DependentImport;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Core\Support\ExportFilename;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DependentService
{
    private const WITH = ['household', 'residentialArea', 'dependentRelations.beneficiary', 'creator.media', 'editor.media'];

    public function stats(array $filters): array
    {
        $base = Dependent::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'linked' => (clone $base)->whereHas('beneficiaries')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Dependent::with(['household', 'residentialArea', 'creator.media', 'editor.media'])
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
        return Dependent::create($validated)->fresh(self::WITH);
    }

    public function update(Dependent $dependent, array $validated): Dependent
    {
        $dependent->update($validated);

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
     * Tạo quan hệ pivot với 1 Beneficiary (loại quan hệ + cờ thân nhân chính + ghi chú).
     *
     * Bất biến "tối đa 1 thân nhân chính/hồ sơ" phải enforce ở đây: đường này chỉ gửi lên MỘT dòng
     * nên Request không nhìn thấy các quan hệ cũ của cùng người có công. (Đường còn lại —
     * mảng `dependents[]` của hồ sơ — thay thế toàn bộ danh sách nên validate ở Request là đủ.)
     */
    public function addRelation(Dependent $dependent, array $validated): BeneficiaryDependentRelation
    {
        $relation = $dependent->dependentRelations()->create($validated);

        if ($relation->is_primary) {
            BeneficiaryDependentRelation::where('beneficiary_id', $relation->beneficiary_id)
                ->whereKeyNot($relation->id)
                ->update(['is_primary' => false]);
        }

        return $relation->load('beneficiary');
    }

    public function removeRelation(Dependent $dependent, int $relationId): void
    {
        $dependent->dependentRelations()->where('id', $relationId)->delete();
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
