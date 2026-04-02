<?php

namespace App\Modules\TaskAssignment\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\TaskAssignment\Exports\LookupExport;
use App\Modules\TaskAssignment\Imports\LookupImport;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TaskAssignmentLookupService
{
    public function publicList(string $modelClass, array $filters)
    {
        /** @var Model $model */
        $model = app($modelClass);

        $publicFilters = [
            ...$filters,
            'status' => StatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'name',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return $model->newQuery()
            ->filter($publicFilters)
            ->get();
    }

    public function publicOptions(string $modelClass, array $filters)
    {
        /** @var Model $model */
        $model = app($modelClass);

        $publicFilters = [
            ...$filters,
            'status' => StatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'name',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return $model->newQuery()
            ->select(['id', 'name', 'description'])
            ->filter($publicFilters)
            ->get();
    }

    public function stats(string $modelClass, array $filters): array
    {
        /** @var Model $model */
        $model = app($modelClass);
        $base = $model->newQuery()->filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', StatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', StatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(string $modelClass, array $filters, int $limit)
    {
        /** @var Model $model */
        $model = app($modelClass);

        return $model->newQuery()
            ->with(['creator', 'editor'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Model $model): Model
    {
        return $model->load(['creator', 'editor']);
    }

    public function store(string $modelClass, array $validated): Model
    {
        /** @var Model $model */
        $model = app($modelClass);

        return $model->newQuery()->create($validated)->load(['creator', 'editor']);
    }

    public function update(Model $model, array $validated): Model
    {
        $model->update($validated);

        return $model->load(['creator', 'editor']);
    }

    public function destroy(Model $model): void
    {
        $model->delete();
    }

    public function bulkDestroy(string $modelClass, array $ids): void
    {
        /** @var Model $model */
        $model = app($modelClass);
        $model->newQuery()->whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(string $modelClass, array $ids, string $status): void
    {
        /** @var Model $model */
        $model = app($modelClass);
        $model->newQuery()->whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(Model $model, string $status): Model
    {
        $model->update(['status' => $status]);

        return $model->load(['creator', 'editor']);
    }

    public function export(string $modelClass, array $filters, string $fileName): BinaryFileResponse
    {
        return Excel::download(new LookupExport($modelClass, $filters), $fileName);
    }

    public function import(string $modelClass, $file): void
    {
        Excel::import(new LookupImport($modelClass), $file);
    }
}
