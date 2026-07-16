<?php

namespace App\Modules\Beneficiary\Requests;

class BulkDestroyHouseholdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Danh sách ID không được để trống.',
            'ids.array' => 'Danh sách ID phải là một mảng.',
            'ids.min' => 'Phải chọn ít nhất 1 hộ gia đình.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID hộ gia đình cần xóa.', 'example' => [1, 2, 3]],
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'Danh sách ID', 'ids.*' => 'ID'];
    }
}
