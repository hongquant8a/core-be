<?php

namespace App\Modules\Beneficiary\Console\Commands;

use App\Modules\Beneficiary\Enums\DependentEligibilityEnum;
use App\Modules\Beneficiary\Enums\DependentRelationStatusEnum;
use App\Modules\Beneficiary\Models\BeneficiaryDependentRelation;
use Illuminate\Console\Command;

class CheckDependentEligibilityCommand extends Command
{
    protected $signature = 'beneficiary:check-dependent-eligibility';

    protected $description = 'Quét thân nhân đủ 18 tuổi không còn điều kiện hưởng tuất, tự động chuyển status = expired';

    public function handle(): int
    {
        $expiredCount = 0;

        BeneficiaryDependentRelation::query()
            ->where('status', DependentRelationStatusEnum::Active->value)
            ->whereHas('dependent', function ($q) {
                $q->withoutGlobalScope('organization')
                    ->whereDate('date_of_birth', '<=', now()->subYears(18));
            })
            ->with(['dependent' => fn ($q) => $q->withoutGlobalScope('organization')])
            ->chunkById(200, function ($relations) use (&$expiredCount) {
                foreach ($relations as $relation) {
                    $dependent = $relation->dependent;

                    if (! $dependent || in_array($dependent->eligibility_status, [
                        DependentEligibilityEnum::Studying->value,
                        DependentEligibilityEnum::DisabledNoWorkCapacity->value,
                    ], true)) {
                        continue;
                    }

                    // update() trực tiếp, KHÔNG qua Service — BeneficiaryDependentRelationObserver::updated()
                    // tự ghi beneficiary_status_histories + dừng subsidy_grants liên quan.
                    $relation->update([
                        'eligible_until' => $dependent->date_of_birth->copy()->addYears(18),
                        'status' => DependentRelationStatusEnum::Expired->value,
                    ]);

                    $expiredCount++;
                }
            });

        $this->info("Đã quét điều kiện hưởng tuất. Số quan hệ chuyển expired: {$expiredCount}.");

        return self::SUCCESS;
    }
}
