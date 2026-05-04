<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CatalogService
{
    public function publicCatalog(string $modelClass, array $filters)
    {
        /** @var Model $model */
        $model = app($modelClass);

        $publicFilters = [
            ...$filters,
            'status' => MeetingCatalogStatusEnum::Active->value,
            'sort_by' => $filters['sort_by'] ?? 'sort_order',
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
        $payload = [
            ...$validated,
            'organization_id' => $this->resolveCurrentOrganizationId(),
        ];

        return $model->newQuery()->create($payload)->load(['creator', 'editor']);
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

        return $model->load(['creator', 'editor']);
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
