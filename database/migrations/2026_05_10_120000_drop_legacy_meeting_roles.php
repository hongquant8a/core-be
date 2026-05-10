<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop legacy meeting roles "Thư ký họp" + "Đại biểu họp" (refactor 2026-05-10).
 * User cũ thuộc các role này -> reassign sang role mới "Đại biểu" (do MeetingPermissionSeeder seed).
 *
 *  - Catalog admin (CRUD meeting-types/locations/document-types/attendees/groups, tạo/xóa meeting,
 *    đổi trạng thái, showQrCode, dashboard, notification config) -> Super Admin/Admin/Quản trị giữ.
 *  - "Đại biểu" có full action ở sub-resource trong meeting; FE check render dựa vào FK chair/operator
 *    + participants list.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('roles')) {
            return;
        }

        $newRoleId = DB::table('roles')
            ->where('name', 'Đại biểu')
            ->where('guard_name', 'web')
            ->value('id');

        $legacyRoles = DB::table('roles')
            ->whereIn('name', ['Thư ký họp', 'Đại biểu họp'])
            ->where('guard_name', 'web')
            ->pluck('id')
            ->all();

        if (empty($legacyRoles)) {
            return;
        }

        // Reassign user role: legacy -> new (skip nếu user đã có role mới).
        if ($newRoleId && DB::getSchemaBuilder()->hasTable('model_has_roles')) {
            $usersWithLegacy = DB::table('model_has_roles')
                ->whereIn('role_id', $legacyRoles)
                ->get();
            foreach ($usersWithLegacy as $row) {
                $hasNew = DB::table('model_has_roles')
                    ->where('role_id', $newRoleId)
                    ->where('model_id', $row->model_id)
                    ->where('model_type', $row->model_type)
                    ->where('organization_id', $row->organization_id)
                    ->exists();
                if (! $hasNew) {
                    DB::table('model_has_roles')->insert([
                        'role_id' => $newRoleId,
                        'model_id' => $row->model_id,
                        'model_type' => $row->model_type,
                        'organization_id' => $row->organization_id,
                    ]);
                }
            }
        }

        // Detach legacy role khỏi pivot rồi drop role row.
        if (DB::getSchemaBuilder()->hasTable('model_has_roles')) {
            DB::table('model_has_roles')->whereIn('role_id', $legacyRoles)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('role_has_permissions')) {
            DB::table('role_has_permissions')->whereIn('role_id', $legacyRoles)->delete();
        }
        DB::table('roles')->whereIn('id', $legacyRoles)->delete();
    }

    public function down(): void
    {
        // Không rollback — role legacy đã thay bằng "Đại biểu" mới.
    }
};
