<?php

namespace App\Modules\Auth\Services;

/**
 * Chuyển đổi permission Spatie (resource.action) sang định dạng CASL abilities.
 *
 * Mỗi permission Laravel tương ứng một đối tượng CASL riêng, không gộp chung.
 * Format: [{ "action": "index", "subject": "User" }, { "action": "show", "subject": "User" }, ...]
 */
class CaslAbilityConverter
{
    /**
     * Chuyển danh sách permission Spatie sang abilities theo chuẩn CASL.
     * Mỗi permission = 1 ability, giữ nguyên action gốc (index, show, store, ...).
     *
     * @param  array<string>  $permissions  Ví dụ: ["users.index", "users.show", "posts.store"]
     * @return array<array{action: string, subject: string}>
     */
    public static function toCaslAbilities(array $permissions): array
    {
        $abilities = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission) || ! str_contains($permission, '.')) {
                continue;
            }

            [$resource, $action] = explode('.', $permission, 2);
            $subject = self::resourceToSubject($resource);

            $abilities[] = [
                'action' => $action,
                'subject' => $subject,
            ];

            // Map thêm các alias CASL để tương thích 100% với các hàm can() cũ trên Frontend
            foreach (self::getAbilityAliases($permission) as $alias) {
                $abilities[] = $alias;
            }
        }

        return $abilities;
    }

    /**
     * Alias CASL cho các permission đã được gộp/đổi chỗ ở BE.
     *
     * core-fe hardcode `aclSubject = 'TaskAssignmentItems'` cho cả 2 màn Đang giao / Được giao,
     * và dùng luôn subject này cho CRUD công việc trong màn Văn bản giao việc — nên một ability
     * FE có thể đến từ nhiều permission BE khác nhau.
     */
    protected static function getAbilityAliases(string $permission): array
    {
        $aliases = [];

        switch ($permission) {
            // CRUD công việc nằm trong màn Văn bản giao việc
            case 'task-assignment-documents.index':
                $aliases[] = ['action' => 'index', 'subject' => 'TaskAssignmentItems'];
                $aliases[] = ['action' => 'show', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'task-assignment-documents.storeItem':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'task-assignment-documents.updateItem':
                $aliases[] = ['action' => 'update', 'subject' => 'TaskAssignmentItems'];
                $aliases[] = ['action' => 'bulkUpdateStatus', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'task-assignment-documents.destroyItem':
                $aliases[] = ['action' => 'destroy', 'subject' => 'TaskAssignmentItems'];
                $aliases[] = ['action' => 'bulkDestroy', 'subject' => 'TaskAssignmentItems'];
                break;

            // 2 màn công việc cá nhân dùng chung subject TaskAssignmentItems
            case 'my-assigned-tasks.index':
            case 'my-received-tasks.index':
                $aliases[] = ['action' => 'index', 'subject' => 'TaskAssignmentItems'];
                $aliases[] = ['action' => 'show', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-assigned-tasks.export':
            case 'my-received-tasks.export':
                $aliases[] = ['action' => 'export', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-assigned-tasks.markDone':
                $aliases[] = ['action' => 'markDone', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-assigned-tasks.pause':
                $aliases[] = ['action' => 'pause', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-assigned-tasks.cancel':
                $aliases[] = ['action' => 'cancel', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-assigned-tasks.changeStatus':
                $aliases[] = ['action' => 'changeStatus', 'subject' => 'TaskAssignmentItems'];
                // Nút "Mở lại" (reopen) ở màn Đang giao được FE gác bằng can('update')
                $aliases[] = ['action' => 'update', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-assigned-tasks.transfer':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemTransfers'];
                break;
            case 'my-assigned-tasks.note':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemNotes'];
                break;

            case 'my-received-tasks.updateProgress':
                $aliases[] = ['action' => 'updateProgress', 'subject' => 'TaskAssignmentItems'];
                // Nút mở drawer cập nhật ở màn Được giao được FE gác bằng can('update')
                $aliases[] = ['action' => 'update', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-received-tasks.report':
                $aliases[] = ['action' => 'index', 'subject' => 'TaskAssignmentItemReports'];
                $aliases[] = ['action' => 'show', 'subject' => 'TaskAssignmentItemReports'];
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemReports'];
                $aliases[] = ['action' => 'update', 'subject' => 'TaskAssignmentItemReports'];
                // Bảng alias này công bố ra FE đúng những gì BE cho phép. Route
                // DELETE của báo cáo gác bằng chính `my-received-tasks.report`,
                // nên thiếu `destroy` ở đây là bảng alias khai THIẾU so với route
                // của chính BE. Sửa cho khớp route, không phải sửa cho vừa FE.
                // Phạm vi (xoá được báo cáo của ai) do
                // TaskAssignmentItemReportPolicy gác, không phải do thiếu alias.
                $aliases[] = ['action' => 'destroy', 'subject' => 'TaskAssignmentItemReports'];
                break;
            case 'my-received-tasks.note':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemNotes'];
                break;
            case 'my-received-tasks.transfer':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemTransfers'];
                break;
        }

        return $aliases;
    }

    protected static function resourceToSubject(string $resource): string
    {
        return collect(explode('-', $resource))
            ->map(fn (string $part) => ucfirst(strtolower($part)))
            ->implode('');
    }
}
