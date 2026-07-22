<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Exports\HouseholdExport;
use App\Modules\Beneficiary\Imports\HouseholdImport;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Core\Support\ExportFilename;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HouseholdService
{
    public function stats(array $filters): array
    {
        $base = Household::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'total_members' => (clone $base)->sum('member_count'),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Household::with(['residentialArea', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Household $household): Household
    {
        return $household->load(['residentialArea', 'creator.media', 'editor.media']);
    }

    public function store(array $validated): Household
    {
        return DB::transaction(function () use ($validated) {
            $beneficiaryIds = $validated['beneficiary_ids'] ?? [];
            $dependentIds = $validated['dependent_ids'] ?? [];
            unset($validated['beneficiary_ids'], $validated['dependent_ids']);

            // Mã hộ để trống sẽ tự sinh trong Household::creating (áp dụng cả import/tinker).
            $household = Household::create($validated);

            if (! empty($beneficiaryIds)) {
                Beneficiary::whereIn('id', $beneficiaryIds)->update(['household_id' => $household->id]);
            }
            if (! empty($dependentIds)) {
                Dependent::whereIn('id', $dependentIds)->update(['household_id' => $household->id]);
            }

            return $household->fresh(['residentialArea', 'creator.media', 'editor.media']);
        });
    }

    public function update(Household $household, array $validated): Household
    {
        $household->update($validated);

        return $household->load(['residentialArea', 'creator.media', 'editor.media']);
    }

    public function destroy(Household $household): void
    {
        $household->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Household::whereIn('id', $ids)->delete();
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new HouseholdExport($filters), ExportFilename::make('ho-gia-dinh'));
    }

    public function import($file): \Illuminate\Support\Collection
    {
        $import = new HouseholdImport;
        Excel::import($import, $file);

        return $import->failures();
    }
}
