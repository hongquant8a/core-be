<?php

namespace App\Modules\Beneficiary\Observers;

use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use App\Modules\Beneficiary\Models\StatusHistory;

/**
 * QUAN TRỌNG: status pivot có 2 đường ghi độc lập — cán bộ đổi tay qua Service, VÀ
 * CheckDependentEligibilityCommand update() trực tiếp Eloquent khi hết tuổi hưởng.
 * Observer đảm bảo ghi status_histories + dừng subsidy_grants dù đi qua đường nào,
 * thay vì fire trong Service (nhánh Job sẽ không bao giờ chạy qua Service).
 */
class BeneficiaryDependentRelationObserver
{
    public function updated(BeneficiaryDependentRelation $relation): void
    {
        if (! $relation->wasChanged('status') || $relation->status !== DependentRelationStatusEnum::Expired->value) {
            return;
        }

        $dependent = $relation->dependent;

        StatusHistory::create([
            'organization_id' => $dependent?->organization_id,
            'subject_type' => $dependent ? $dependent::class : null,
            'subject_id' => $relation->dependent_id,
            'old_status' => $relation->getOriginal('status'),
            'new_status' => $relation->status,
            'reason' => 'Hết điều kiện hưởng theo tuổi',
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);

        $dependent?->subsidyGrants()
            ->where('status', SubsidyStatusEnum::Active->value)
            ->update([
                'status' => SubsidyStatusEnum::Terminated->value,
                'termination_reason' => 'Hết điều kiện hưởng theo tuổi',
                'granted_to' => now(),
            ]);
    }
}
