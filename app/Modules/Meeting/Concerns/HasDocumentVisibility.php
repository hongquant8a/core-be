<?php

namespace App\Modules\Meeting\Concerns;

use App\Modules\Meeting\Models\Meeting;

/**
 * Logic xác định user có thấy được toàn bộ tài liệu của meeting không.
 *
 * - Guest (chưa đăng nhập) → false → chỉ thấy doc is_public=true
 * - Auth user là chủ trì / thư ký / participant → true → thấy mọi doc
 * - Auth user khác → false → chỉ thấy doc is_public=true (như guest)
 */
trait HasDocumentVisibility
{
    protected function shouldSeeAllDocs(?Meeting $meeting): bool
    {
        if (! $meeting) {
            return false;
        }

        $userId = auth()->id();
        if (! $userId) {
            return false;
        }

        $meeting->loadMissing(['chairperson', 'operator']);

        if ((int) ($meeting->chairperson?->user_id ?? 0) === (int) $userId) {
            return true;
        }
        if ((int) ($meeting->operator?->user_id ?? 0) === (int) $userId) {
            return true;
        }

        return $meeting->participants()
            ->whereHas('attendee', fn ($q) => $q->where('user_id', $userId))
            ->exists();
    }
}
