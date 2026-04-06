<?php

namespace App\Modules\Core\Resources\Concerns;

use App\Modules\Core\Models\User;

/**
 * Cung cấp helper để format thông tin tóm tắt người dùng (id, name, avatar)
 * dùng chung cho các resource cần hiển thị created_by / updated_by.
 */
trait FormatsUserSummary
{
    /**
     * Trả về mảng tóm tắt người dùng gồm id, name, avatar.
     */
    protected function formatUserSummary(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $avatar = $user->getFirstMedia('avatars');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $avatar ? '/storage/'.$avatar->id.'/'.$avatar->file_name : null,
        ];
    }
}
