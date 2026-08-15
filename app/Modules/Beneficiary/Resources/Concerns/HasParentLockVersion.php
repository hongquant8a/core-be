<?php

namespace App\Modules\Beneficiary\Resources\Concerns;

/**
 * Mọi Resource dòng con đều xuất `parent_lock_version` (quy tắc 4 của B5).
 *
 * Service phải eager load quan hệ `beneficiary`, nếu không `whenLoaded` trả `MissingValue`
 * và key này biến mất khỏi response — frontend sẽ gán `undefined` vào state rồi dính 409 ở
 * lần ghi kế tiếp.
 */
trait HasParentLockVersion
{
    protected function parentLockVersion(): mixed
    {
        return $this->whenLoaded('beneficiary', fn () => $this->beneficiary->updated_at?->toIso8601String());
    }
}
