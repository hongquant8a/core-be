<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Exports\ResidentialAreaExport;
use App\Modules\Beneficiary\Imports\ResidentialAreaImport;
use App\Modules\Beneficiary\Models\Household;
use App\Modules\Beneficiary\Models\ResidentialArea;
use App\Modules\Core\Support\ExportFilename;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResidentialAreaService
{
    public function stats(array $filters): array
    {
        $ids = ResidentialArea::filter($filters)->pluck('id');

        return [
            'total' => $ids->count(),
            'total_households' => Household::whereIn('residential_area_id', $ids)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return ResidentialArea::withCount('households')
            ->with(['creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(ResidentialArea $residentialArea): ResidentialArea
    {
        return $residentialArea->loadCount('households')->load(['creator.media', 'editor.media']);
    }

    public function store(array $validated): ResidentialArea
    {
        $residentialArea = ResidentialArea::create($validated);

        return $residentialArea->loadCount('households')->load(['creator.media', 'editor.media']);
    }

    public function update(ResidentialArea $residentialArea, array $validated): ResidentialArea
    {
        $residentialArea->update($validated);

        return $residentialArea->loadCount('households')->load(['creator.media', 'editor.media']);
    }

    public function destroy(ResidentialArea $residentialArea): void
    {
        $residentialArea->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        ResidentialArea::whereIn('id', $ids)->delete();
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new ResidentialAreaExport($filters), ExportFilename::make('to-dan-pho'));
    }

    public function import($file): \Illuminate\Support\Collection
    {
        $import = new ResidentialAreaImport;
        Excel::import($import, $file);

        return $import->failures();
    }
}
