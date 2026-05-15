<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed 1 role "Đại biểu" cho module Phòng họp không giấy.
 *
 * Triết lý role-removal sau refactor 2026-05-10:
 *  - Toàn bộ thao tác CRUD admin (catalog, tạo/xóa meeting, đổi trạng thái meeting,
 *    dashboard, notification config, ...) -> Super Admin / Admin / Quản trị.
 *  - Thao tác trong meeting (agenda/document/participant/attendance/vote/discussion/note)
 *    -> role "Đại biểu" có ĐỦ permissions để call API. FE check render dựa vào
 *    chairperson_meeting_attendee_id / operator_meeting_attendee_id / participants list
 *    để biết user có quyền "operator/secretary" trong meeting đó hay không.
 *  - BE service vẫn enforce scope: user không phải chair/operator/participant của meeting
 *    -> không thấy/không sửa được dữ liệu meeting đó (org_id + per-meeting access check).
 */
class MeetingPermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $this->seedRole();
        $this->assignPermissionsToRole();
    }

    private function seedRole(): void
    {
        Role::firstOrCreate(
            ['name' => 'Đại biểu', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
    }

    private function assignPermissionsToRole(): void
    {
        $daiBieu = Role::where('name', 'Đại biểu')->where('guard_name', self::GUARD)->first();
        if ($daiBieu) {
            $daiBieu->syncPermissions($this->getDaiBieuPermissionNames());
        }
    }

    /**
     * Đại biểu có full action ở các sub-resource trong meeting (BE scope per-meeting).
     * KHÔNG có meetings.* permission nào — đại biểu list/show meeting qua endpoint public
     * (`/api/meetings/public[/stats|/{id}]`), không cần Spatie permission. Tránh phơi
     * `meetings.index` ra cho đại biểu khiến FE render màn quản trị meeting list dù không
     * phải admin. Catalog CRUD + dashboard + notification config -> admin only.
     * showQrCode đã chuyển sang Gate Policy (chair/operator/qr_manager_user_id), không qua Spatie.
     */
    private function getDaiBieuPermissionNames(): array
    {
        $names = [];

        // (Self profile + log activity dùng /me endpoints — không qua Spatie permission, không cần khai báo ở đây.)

        // Sub-resources trong meeting — full action (BE service scope theo participation)
        foreach (['meeting-agendas', 'meeting-documents'] as $resource) {
            foreach (['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        // In-meeting actions (respond, checkin, markAbsent, approve, reject, complete,
        // open/close vote-topic, cast vote) đã chuyển hoàn toàn sang nested route +
        // Gate Policy → KHÔNG còn cần Spatie permission tương ứng.

        // Participants — admin CRUD only.
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
            $names[] = "meeting-participants.{$action}";
        }

        // Attendances — admin CRUD/báo cáo only.
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
            $names[] = "meeting-attendances.{$action}";
        }

        // Vote — admin CRUD/báo cáo only (cast vote qua nested).
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
            $names[] = "meeting-vote-topics.{$action}";
            $names[] = "meeting-vote-responses.{$action}";
        }

        // Đăng ký thảo luận / chất vấn — admin CRUD/báo cáo (complete qua nested).
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy'] as $action) {
            $names[] = "meeting-discussion-registrations.{$action}";
        }

        // Ghi chú cá nhân (service auto-scope theo user)
        foreach (['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
            $names[] = "meeting-personal-notes.{$action}";
        }
        foreach (['index', 'store', 'update', 'destroy'] as $action) {
            $names[] = "meeting-personal-note-attachments.{$action}";
        }

        return array_values(array_unique($names));
    }
}
