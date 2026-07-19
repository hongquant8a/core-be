<?php

namespace App\Modules\Beneficiary\Requests;

class StoreResidentialAreaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên tổ dân phố không được để trống.',
            'name.string' => 'Tên tổ dân phố phải là một chuỗi ký tự.',
            'name.max' => 'Tên tổ dân phố không được vượt quá 255 ký tự.',
            'code.string' => 'Mã tổ dân phố phải là một chuỗi ký tự.',
            'code.max' => 'Mã tổ dân phố không được vượt quá 255 ký tự.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tên tổ dân phố.', 'example' => 'Tổ 5'],
            'code' => ['description' => 'Mã tổ dân phố.', 'example' => 'TDP-005'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên tổ dân phố',
            'code' => 'Mã tổ dân phố',
        ];
    }
}
