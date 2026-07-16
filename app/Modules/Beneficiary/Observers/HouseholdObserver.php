<?php

namespace App\Modules\Beneficiary\Observers;

use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\Household;

/**
 * Đăng ký trên CẢ Beneficiary lẫn Dependent (Beneficiary::observe + Dependent::observe) —
 * data integrity phải áp dụng mọi nơi household_id bị đổi (API, Seeder, Console, Tinker),
 * không chỉ 1 mốc nghiệp vụ cụ thể. Không COUNT() runtime khi hiển thị danh sách hộ.
 */
class HouseholdObserver
{
    public function saved(Beneficiary|Dependent $model): void
    {
        $this->syncCount($model->household_id);

        if ($model->wasChanged('household_id')) {
            $this->syncCount($model->getOriginal('household_id'));
        }
    }

    public function deleted(Beneficiary|Dependent $model): void
    {
        $this->syncCount($model->household_id);
    }

    private function syncCount(?int $householdId): void
    {
        if (! $householdId) {
            return;
        }

        $household = Household::withoutGlobalScopes()->find($householdId);

        if (! $household) {
            return;
        }

        $count = Beneficiary::withoutGlobalScopes()->where('household_id', $householdId)->count()
            + Dependent::withoutGlobalScopes()->where('household_id', $householdId)->count();

        $household->updateQuietly(['member_count' => $count]);
    }
}
