<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seed 2 role nghiệp vụ cho module Phòng họp không giấy.
 *
 *  - Thư ký họp: compose meeting + quản lý catalog + notification config + duyệt điểm danh
 *    + điều hành (gọi/kết thúc thảo luận, mở/đóng biểu quyết). Thực tế "thư ký" và "điều hành"
 *    là cùng một vai trò vận hành phiên họp ở mức quyền (role-in-meeting cụ thể vẫn xác định
 *    theo FK chairperson/operator trên meetings + middleware Sprint 1).
 *
 *  - Đại biểu họp: self-service trong meeting họ được mời (xác nhận tham gia, điểm danh, vote,
 *    đăng ký thảo luận/chất vấn, ghi chú cá nhân). Không tạo/sửa meeting/catalog.
 *
 * Ownership trong service vẫn enforce thêm 1 lớp: đại biểu chỉ thấy/sửa data của chính họ.
 *
 * Note: Quản trị tổ chức ở mức cao hơn (admin tổng) đã có sẵn ở Super Admin / Admin.
 */
class MeetingPermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $this->seedRoles();
        $this->assignPermissionsToRoles();
    }

    private function seedRoles(): void
    {
        Role::firstOrCreate(
            ['name' => 'Thư ký họp', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
        Role::firstOrCreate(
            ['name' => 'Đại biểu họp', 'guard_name' => self::GUARD],
            ['organization_id' => null]
        );
    }

    private function assignPermissionsToRoles(): void
    {
        $thuKy = Role::where('name', 'Thư ký họp')->where('guard_name', self::GUARD)->first();
        if ($thuKy) {
            $thuKy->syncPermissions($this->getThuKyPermissionNames());
        }

        $daiBieu = Role::where('name', 'Đại biểu họp')->where('guard_name', self::GUARD)->first();
        if ($daiBieu) {
            $daiBieu->syncPermissions($this->getDaiBieuPermissionNames());
        }
    }

    /**
     * Thư ký họp: full quyền soạn + vận hành meeting.
     */
    private function getThuKyPermissionNames(): array
    {
        $names = [];

        // Catalog meeting (full CRUD + import/export)
        $catalogResources = [
            'meeting-types', 'meeting-locations', 'meeting-document-types',
            'meeting-attendee-groups', 'meeting-attendees',
        ];
        foreach ($catalogResources as $resource) {
            foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'import'] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        // Meetings (full CRUD + status workflow + export + runtime actions + highlight)
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'bulkUpdateStatus', 'changeStatus', 'export', 'lockAttendance', 'unlockAttendance', 'endEarly', 'highlightAgenda', 'highlightDiscussion'] as $action) {
            $names[] = "meetings.{$action}";
        }

        // Sub-resources của meeting (đầy đủ thao tác để compose + run meeting)
        foreach (['meeting-agendas', 'meeting-documents'] as $resource) {
            foreach (['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        // Participants (full + respond để admin có thể sửa hộ)
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'respond'] as $action) {
            $names[] = "meeting-participants.{$action}";
        }

        // Attendances (full + checkin/markAbsent self + approve/reject operator)
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy', 'checkin', 'markAbsent', 'approve', 'reject'] as $action) {
            $names[] = "meeting-attendances.{$action}";
        }

        // Vote (chương trình + phiếu)
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
            $names[] = "meeting-vote-topics.{$action}";
            $names[] = "meeting-vote-responses.{$action}";
        }

        // Đăng ký thảo luận/chất vấn (không có bulkDestroy theo design + complete cho operator)
        foreach (['stats', 'index', 'show', 'store', 'update', 'destroy', 'complete'] as $action) {
            $names[] = "meeting-discussion-registrations.{$action}";
        }

        // Ghi chú cá nhân (service vẫn scope theo user — thư ký chỉ thấy ghi chú của chính họ)
        foreach (['index', 'show', 'store', 'update', 'destroy', 'bulkDestroy'] as $action) {
            $names[] = "meeting-personal-notes.{$action}";
        }
        foreach (['index', 'store', 'update', 'destroy'] as $action) {
            $names[] = "meeting-personal-note-attachments.{$action}";
        }

        // Notification config + logs (thư ký phụ trách cấu hình thông báo cuộc họp)
        foreach (['index', 'update'] as $action) {
            $names[] = "notifications.event-configs.{$action}";
        }
        foreach (['index', 'store', 'update', 'destroy'] as $action) {
            $names[] = "notifications.schedules.{$action}";
        }
        foreach (['index', 'show', 'destroy', 'bulkDestroy', 'export'] as $action) {
            $names[] = "notifications.logs.{$action}";
        }

        // Dashboard (truy cập trang tổng quan)
        $names[] = 'dashboard.index';

        return array_values(array_unique($names));
    }

    /**
     * Đại biểu họp: self-service trong meeting được mời.
     */
    private function getDaiBieuPermissionNames(): array
    {
        return [
            // Xem meeting (BE auto-scope chỉ trả meeting họ được mời qua publicIndex)
            'meetings.index',
            'meetings.show',
            'meetings.stats',

            // Xem chương trình + tài liệu của meeting
            'meeting-agendas.index',
            'meeting-agendas.show',
            'meeting-documents.index',
            'meeting-documents.show',

            // Tham gia / từ chối / điểm danh / báo vắng
            'meeting-participants.show',
            'meeting-participants.stats',
            'meeting-participants.respond',
            'meeting-attendances.checkin',
            'meeting-attendances.markAbsent',
            'meeting-attendances.show',
            'meeting-attendances.index',
            'meeting-attendances.stats',

            // Biểu quyết
            'meeting-vote-topics.index',
            'meeting-vote-topics.show',
            'meeting-vote-topics.stats',
            'meeting-vote-responses.index',
            'meeting-vote-responses.show',
            'meeting-vote-responses.store',
            'meeting-vote-responses.update',
            'meeting-vote-responses.stats',

            // Đăng ký thảo luận/chất vấn (service auto-derive participant + ownership)
            'meeting-discussion-registrations.index',
            'meeting-discussion-registrations.show',
            'meeting-discussion-registrations.store',
            'meeting-discussion-registrations.update',
            'meeting-discussion-registrations.destroy',
            'meeting-discussion-registrations.stats',

            // Ghi chú cá nhân (service auto-scope theo user)
            'meeting-personal-notes.index',
            'meeting-personal-notes.show',
            'meeting-personal-notes.store',
            'meeting-personal-notes.update',
            'meeting-personal-notes.destroy',
            'meeting-personal-notes.bulkDestroy',
            'meeting-personal-note-attachments.index',
            'meeting-personal-note-attachments.store',
            'meeting-personal-note-attachments.update',
            'meeting-personal-note-attachments.destroy',
        ];
    }
}
