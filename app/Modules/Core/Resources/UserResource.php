<?php

namespace App\Modules\Core\Resources;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Resources\Concerns\FormatsUserSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserResource extends JsonResource
{
    use FormatsUserSummary;
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'zalo_user_id' => $this->zalo_user_id,
            'gender' => $this->profile?->gender,
            'birth_date' => $this->profile?->birth_date?->format('d/m/Y'),
            'citizen_id' => $this->profile?->citizen_id,
            'permanent_address' => $this->profile?->permanent_address,
            'temporary_address' => $this->profile?->temporary_address,
            'user_name' => $this->user_name,
            'status' => $this->status,
            'task_assignment_department_id' => $this->whenLoaded('taskAssignmentUser', fn () => $this->taskAssignmentUser?->task_assignment_department_id),
            'avatar' => ($avatar = $this->getFirstMedia('avatars')) ? '/storage/'.$avatar->id.'/'.$avatar->file_name : null,
            'created_by' => $this->whenLoaded('creator', fn () => $this->formatUserSummary($this->creator), null),
            'updated_by' => $this->whenLoaded('editor', fn () => $this->formatUserSummary($this->editor), null),
            'assignments' => $this->roleAssignments(),
            // Derive từ personal_access_tokens.created_at max (Sanctum). Null nếu user chưa từng login.
            'last_login_at' => $this->last_login_at?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
            'updated_at' => $this->updated_at?->format('d/m/Y H:i:s'),
        ];
    }

    protected function roleAssignments(): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $modelHasRolesTable = $tableNames['model_has_roles'] ?? 'model_has_roles';
        $rolePivotKey = $columnNames['role_pivot_key'] ?? 'role_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'organization_id';

        $rows = DB::table($modelHasRolesTable)
            ->where($modelMorphKey, $this->id)
            ->where('model_type', \App\Modules\Core\Models\User::class)
            ->select([$teamForeignKey.' as organization_id', $rolePivotKey.' as role_id'])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $roleIds = $rows->pluck('role_id')->unique()->values();
        $organizationIds = $rows->pluck('organization_id')->unique()->values();

        $roles = Role::whereIn('id', $roleIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $organizations = Organization::whereIn('id', $organizationIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        return $rows
            ->groupBy('role_id')
            ->map(function ($items, $roleId) use ($roles, $organizations) {
                $role = $roles->get((int) $roleId);

                return [
                    'role_id' => (int) $roleId,
                    'role_name' => $role?->name,
                    'organization_ids' => $items
                        ->pluck('organization_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all(),
                    'organizations' => $items
                        ->pluck('organization_id')
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->map(fn ($id) => [
                            'id' => $id,
                            'name' => $organizations->get($id)?->name,
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
