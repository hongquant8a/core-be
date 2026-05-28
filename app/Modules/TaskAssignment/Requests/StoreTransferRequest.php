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
}
