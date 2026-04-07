<?php

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait tự động scope theo organization_id từ header X-Organization-Id.
 * Dùng cho các model trong module nghiệp vụ cần đa tổ chức.
 */
trait HasOrganizationScope
{
    public static function bootHasOrganizationScope(): void
    {
        // Tự động gán organization_id khi tạo mới
        static::creating(function (Model $model) {
            if (empty($model->organization_id) && function_exists('getPermissionsTeamId')) {
                $teamId = getPermissionsTeamId();
                if ($teamId) {
                    $model->organization_id = (int) $teamId;
                }
            }
        });

        // Áp dụng Global Scope để lọc theo organization_id
        static::addGlobalScope('organization', function (Builder $builder) {
            if (function_exists('getPermissionsTeamId')) {
                $teamId = getPermissionsTeamId();
                if ($teamId) {
                    $builder->where($builder->getModel()->getTable() . '.organization_id', (int) $teamId);
                }
            }
        });
    }

    /**
     * Quan hệ với organization.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
