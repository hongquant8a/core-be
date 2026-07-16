<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;

class StoreSubsidyPolicyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'beneficiary_type' => ['nullable', BeneficiaryTypeEnum::rule()],
            'relationship_type' => ['nullable', DependentRelationshipEnum::rule()],
            'amount' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'legal_basis' => 'required|string|max:255',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_type.in' => 'Loại đối tượng không hợp lệ.',
            'relationship_type.in' => 'Quan hệ không hợp lệ.',
            'amount.required' => 'Mức trợ cấp không được để trống.',
            'amount.numeric' => 'Mức trợ cấp phải là số.',
            'amount.min' => 'Mức trợ cấp không được âm.',
            'legal_basis.required' => 'Căn cứ pháp lý không được để trống.',
            'effective_from.required' => 'Ngày hiệu lực không được để trống.',
            'effective_to.after' => 'Ngày hết hiệu lực phải sau ngày hiệu lực.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'beneficiary_type' => ['description' => 'Loại đối tượng áp dụng.', 'example' => 'war_invalid'],
            'relationship_type' => ['description' => 'Quan hệ áp dụng (cho thân nhân).', 'example' => null],
            'amount' => ['description' => 'Mức trợ cấp.', 'example' => 3500000],
            'unit' => ['description' => 'Đơn vị.', 'example' => 'VND/tháng'],
            'legal_basis' => ['description' => 'Căn cứ pháp lý.', 'example' => 'Nghị định 75/2021/NĐ-CP'],
            'effective_from' => ['description' => 'Ngày hiệu lực.', 'example' => '2021-07-01'],
            'effective_to' => ['description' => 'Ngày hết hiệu lực.', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'beneficiary_type' => 'Loại đối tượng',
            'relationship_type' => 'Quan hệ',
            'amount' => 'Mức trợ cấp',
            'unit' => 'Đơn vị',
            'legal_basis' => 'Căn cứ pháp lý',
            'effective_from' => 'Ngày hiệu lực',
            'effective_to' => 'Ngày hết hiệu lực',
        ];
    }
}
