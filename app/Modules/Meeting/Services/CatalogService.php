<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use App\Modules\Meeting\Exports\CatalogExport;
use App\Modules\Meeting\Imports\CatalogImport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatalogService
{
    public function publicCatalog(string $modelClass, array $filters)
    {
        /** @var Model $model */
        $model = app($modelClass);

        $publicFilters = [
            ...$filters,
            'status' => MeetingCatalogStatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'name',
            'sort_order' => $filters['sort_order'] ?? 'asc',
        ];

        return $model->newQuery()->filter($publicFilters)->get();
    }

    public function publicOptions(string $modelClass, array $filters)
    {
        /** @var Model $model */
        $model = app($modelClass);

        $publicFilters = [
            ...$filters,
            'status' => MeetingCatalogStatusEnum::Active->value,
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
            'active' => (clone $base)->where('status', MeetingCatalogStatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', MeetingCatalogStatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(string $modelClass, array $filters, int $limit)
    {
        /** @var Model $model */
        $model = app($modelClass);

        $query = $model->newQuery()
            ->with(['creator.media', 'editor.media']);

        // MeetingAttendeeGroup: bổ sung attendees_count cho UI list (nhóm có bao nhiêu đại biểu).
        if ($modelClass === \App\Modules\Meeting\Models\MeetingAttendeeGroup::class) {
            $query->withCount('attendees');
        }

        return $query->filter($filters)->paginate($limit);
    }

    public function show(Model $model): Model
    {
        // MeetingAttendeeGroup show — kèm attendees_count.
        if ($model instanceof \App\Modules\Meeting\Models\MeetingAttendeeGroup) {
            $model->loadCount('attendees');
        }

        return $model->load(['creator.media', 'editor.media']);
    }

    public function store(string $modelClass, array $validated): Model
    {
        /** @var Model $model */
        $model = app($modelClass);
        $payload = [
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return $model->newQuery()->create($payload)->load(['creator.media', 'editor.media']);
    }

    public function update(Model $model, array $validated): Model
    {
        $model->update($validated);

        return $model->load(['creator.media', 'editor.media']);
    }

    public function destroy(Model $model): void
    {
        $model->delete();
    }

    public function bulkDestroy(string $modelClass, array $ids): void
    {
        /** @var Model $model */
        $model = app($modelClass);
        $model->newQuery()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(string $modelClass, array $ids, string $status): void
    {
        /** @var Model $model */
        $model = app($modelClass);
        $model->newQuery()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(Model $model, string $status): Model
    {
        $model->update(['status' => $status]);

        return $model->load(['creator.media', 'editor.media']);
    }

    public function export(string $modelClass, array $filters, string $fileName): BinaryFileResponse
    {
        return Excel::download(new CatalogExport($modelClass, $filters), $fileName);
    }

    public function import(string $modelClass, $file): void
    {
        $import = new CatalogImport($modelClass);
        Excel::import($import, $file);

        // Surface validation failures — không để silently skip (API trả 200 nhưng 0 row insert).
        $failures = $import->failures();
        if ($failures->isNotEmpty()) {
            $errors = [];
            foreach ($failures as $failure) {
                $row = $failure->row();
                foreach ($failure->errors() as $msg) {
                    $errors["row_{$row}"][] = $msg;
                }
            }
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
