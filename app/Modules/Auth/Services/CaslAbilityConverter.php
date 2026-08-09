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

    protected static function getAbilityAliases(string $permission): array
    {
        $aliases = [];

        switch ($permission) {
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
                break;
            case 'my-assigned-tasks.transfer':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemTransfers'];
                break;
            case 'my-assigned-tasks.note':
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemNotes'];
                break;

            case 'my-received-tasks.updateProgress':
                $aliases[] = ['action' => 'updateProgress', 'subject' => 'TaskAssignmentItems'];
                break;
            case 'my-received-tasks.report':
                $aliases[] = ['action' => 'index', 'subject' => 'TaskAssignmentItemReports'];
                $aliases[] = ['action' => 'show', 'subject' => 'TaskAssignmentItemReports'];
                $aliases[] = ['action' => 'store', 'subject' => 'TaskAssignmentItemReports'];
                $aliases[] = ['action' => 'update', 'subject' => 'TaskAssignmentItemReports'];
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
