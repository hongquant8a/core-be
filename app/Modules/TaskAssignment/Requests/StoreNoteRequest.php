<?php

namespace App\Modules\TaskAssignment\Requests;

class StoreNoteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:200',
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => 'Nội dung',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Nội dung không được để trống.',
            'content.string' => 'Nội dung phải là chuỗi ký tự.',
            'content.max' => 'Nội dung không được vượt quá 200 ký tự.',
        ];
    }
}
