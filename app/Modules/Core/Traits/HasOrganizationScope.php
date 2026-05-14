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

        // Áp dụng Global Scope để lọc theo organization_id.
        // Cho phép record có organization_id=NULL = shared global catalog (vd MeetingType
        // dùng chung cho mọi org). Auth user thấy: record của org mình + record shared.
        static::addGlobalScope('organization', function (Builder $builder) {
            if (function_exists('getPermissionsTeamId')) {
                $teamId = getPermissionsTeamId();
                if ($teamId) {
                    $table = $builder->getModel()->getTable();
                    $builder->where(function ($q) use ($teamId, $table) {
                        $q->where("{$table}.organization_id", (int) $teamId)
                            ->orWhereNull("{$table}.organization_id");
                    });
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
