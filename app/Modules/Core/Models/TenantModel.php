<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\HasOrganizationScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model cho mọi resource thuộc tenant (có cột `organization_id`).
 *
 * Auto-apply:
 *  - Global scope filter `organization_id = team_id` khi `getPermissionsTeamId()` có value
 *    (auth user đã set qua X-Organization-Id header).
 *  - Auto gán `organization_id` khi creating nếu chưa set.
 *  - Quan hệ `organization()` tới `Organization` model.
 *
 * Behavior khi guest (no team context):
 *  - Global scope skip → query cross-org (đúng yêu cầu public route).
 *
 * Sử dụng:
 *  - Mọi model có cột `organization_id` extends class này (thay cho `Model`).
 *  - KHÔNG dùng `use HasOrganizationScope` riêng từng model nữa — base đã có.
 *  - Pivot table (extends `Pivot`) hoặc model log không gắn tenant → vẫn extends `Model`.
 *
 * Lưu ý: muốn truy cập cross-org trong code (seeder, background job, system task) →
 * dùng `withoutGlobalScope('organization')` explicit để bypass scope.
 */
abstract class TenantModel extends Model
{
    use HasOrganizationScope;
}
