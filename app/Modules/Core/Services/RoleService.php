<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Exports\RolesExport;
use App\Modules\Core\Imports\RolesImport;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Support\ExportFilename;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RoleService
{
    public function stats(array $filters): array
    {
        $base = Role::with('organization')->filter($filters);

        return ['total' => (clone $base)->count()];
    }

    public function index(array $filters, int $limit)
    {
        return Role::with(['organization', 'permissions'])
            ->withCount('users')
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Role $role): Role
    {
        return $role->load(['organization', 'permissions'])->loadCount('users');
    }

    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $permissionIds = $data['permissions'] ?? $data['permission_ids'] ?? null;
            unset($data['permission_ids'], $data['permissions']);
            $data['guard_name'] = $data['guard_name'] ?? config('auth.defaults.guard', 'web');
            $data['organization_id'] = null;

            $role = Role::create($data);

            if (! empty($permissionIds)) {
                $role->syncPermissions($permissionIds);
            }

            return $role->load('permissions');
        });
    }

    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data) {
            $permissionIds = $data['permissions'] ?? $data['permission_ids'] ?? null;
            unset($data['permission_ids'], $data['permissions']);
            $data['organization_id'] = null;

            $role->update($data);

            if ($permissionIds !== null) {
                $role->syncPermissions($permissionIds);
            }

            return $role->load('permissions');
        });
    }

    public function destroy(Role $role): void
    {
        $role->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Role::whereIn('id', $ids)->delete();
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new RolesExport($filters), ExportFilename::make('vai-tro'));
    }

    public function import($file): void
    {
        Excel::import(new RolesImport, $file);
    }
}
