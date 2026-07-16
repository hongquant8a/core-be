<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\Dependent;
use App\Modules\Beneficiary\Models\SubsidyGrant;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use Illuminate\Validation\ValidationException;

class SubsidyGrantService
{
    private const SUBJECT_MODELS = [
        'beneficiary' => Beneficiary::class,
        'dependent' => Dependent::class,
    ];

    public function index(array $filters, int $limit)
    {
        return SubsidyGrant::with(['subject', 'policy'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function store(array $validated): SubsidyGrant
    {
        $modelClass = self::SUBJECT_MODELS[$validated['subject_type']];
        $subject = $modelClass::findOrFail($validated['subject_id']);

        $policy = SubsidyPolicy::findOrFail($validated['beneficiary_subsidy_policy_id']);

        if ($policy->effective_to && $policy->effective_to->isPast()) {
            throw ValidationException::withMessages([
                'beneficiary_subsidy_policy_id' => 'Chính sách trợ cấp này đã hết hiệu lực, vui lòng chọn chính sách khác.',
            ]);
        }

        $grant = SubsidyGrant::create([
            'organization_id' => $subject->organization_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
            'beneficiary_subsidy_policy_id' => $policy->id,
            'amount' => $validated['amount'] ?? $policy->amount,
            'granted_from' => $validated['granted_from'],
            'status' => SubsidyStatusEnum::Active->value,
        ]);

        return $grant->load(['subject', 'policy']);
    }

    public function changeStatus(SubsidyGrant $grant, string $status, ?string $terminationReason = null): SubsidyGrant
    {
        $update = ['status' => $status];

        if ($status === SubsidyStatusEnum::Terminated->value) {
            $update['termination_reason'] = $terminationReason;
            $update['granted_to'] = now();
        }

        $grant->update($update);

        return $grant->load(['subject', 'policy']);
    }
}
