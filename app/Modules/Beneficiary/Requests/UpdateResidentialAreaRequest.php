<?php

namespace App\Modules\Beneficiary\Requests;

class UpdateResidentialAreaRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'note' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Tên tổ dân phố phải là một chuỗi ký tự.',
            'name.max' => 'Tên tổ dân phố không được vượt quá 255 ký tự.',
            'note.string' => 'Ghi chú phải là một chuỗi ký tự.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tên tổ dân phố.', 'example' => 'Tổ 5'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên tổ dân phố',
            'note' => 'Ghi chú',
        ];
    }
}
