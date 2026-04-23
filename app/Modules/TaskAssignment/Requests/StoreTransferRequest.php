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
}
