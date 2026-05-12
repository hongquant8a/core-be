<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exports\PermissionsExport;
use App\Modules\Core\Imports\PermissionsImport;
use App\Modules\Core\Models\Permission;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PermissionService
{
    public function stats(array $filters): array
    {
        $base = Permission::filter($filters);

        return [
            'total_groups' => (clone $base)->whereNull('parent_id')->count(),
            'total_permissions' => (clone $base)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        // Khi user pass sort_by tường minh → flat sort theo cột đó (skip treeOrder).
        // Mặc định không có sort_by → giữ tree order (cha trước, con sau theo sort_order).
        $hasExplicitSort = ! empty($filters['sort_by']);

        return Permission::query()
            ->when(! $hasExplicitSort, fn ($q) => $q->treeOrder())
            ->filter($filters)
            ->with(['parent', 'roles:id,name'])
            ->paginate($limit);
    }

    public function tree($parentIdProvided, $parentId)
    {
        $query = Permission::query()
            ->when($parentIdProvided, fn ($q) => $q->where('parent_id', $parentId));

        $items = $query->orderBy('sort_order')->orderBy('id')->get();

        return $this->buildTree($items);
    }

    public function show(Permission $permission): Permission
    {
        return $permission->load(['parent', 'children']);
    }

    public function store(array $data): Permission
    {
        $data['guard_name'] = $data['guard_name'] ?? config('auth.defaults.guard', 'web');

        return Permission::create($data);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission;
    }

    public function destroy(Permission $permission): void
    {
        $permission->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Permission::whereIn('id', $ids)->delete();
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new PermissionsExport($filters), 'export__quyen-han.xlsx');
    }

    public function import($file): void
    {
        Excel::import(new PermissionsImport, $file);
    }

    public function buildTree(Collection $items): Collection
    {
        $grouped = $items->groupBy('parent_id');
        $builder = function ($parentId) use ($grouped, &$builder) {
            return ($grouped->get($parentId) ?? collect())
                ->sortBy('sort_order')
                ->map(fn ($item) => $item->setRelation('children', $builder($item->id)))
                ->values();
        };

        return $builder(null);
    }
}
