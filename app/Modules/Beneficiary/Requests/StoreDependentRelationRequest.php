<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;

class StoreDependentRelationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'beneficiary_id' => 'required|integer|exists:beneficiaries,id',
            'relationship_type' => ['required', DependentRelationshipEnum::rule()],
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'beneficiary_id.required' => 'Người có công không được để trống.',
            'beneficiary_id.exists' => 'Người có công không tồn tại.',
            'relationship_type.required' => 'Quan hệ không được để trống.',
            'relationship_type.in' => 'Quan hệ không hợp lệ.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'beneficiary_id' => ['description' => 'ID người có công liên quan.', 'example' => 1],
            'relationship_type' => ['description' => 'Quan hệ với người có công.', 'example' => 'child'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'beneficiary_id' => 'Người có công',
            'relationship_type' => 'Quan hệ',
            'note' => 'Ghi chú',
        ];
    }
}
