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
}
