<?php

namespace App\Modules\TaskAssignment\Requests;

class StoreTransferRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'to_user_id' => 'required|integer|exists:users,id',
            'note' => 'nullable|string|max:200',
        ];
    }

    public function attributes(): array
    {
        return [
            'to_user_id' => 'Người nhận',
            'note' => 'Ghi chú',
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Người nhận không được để trống.',
            'to_user_id.integer' => 'Người nhận phải là số nguyên.',
            'to_user_id.exists' => 'Người nhận không tồn tại.',
            'note.string' => 'Ghi chú phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú không được vượt quá 200 ký tự.',
        ];
    }
}
