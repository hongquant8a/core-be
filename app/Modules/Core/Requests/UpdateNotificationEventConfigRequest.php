<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationEventConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
        ];
    }

    public function attributes(): array
    {
        return [
            'enabled' => 'Enabled',
        ];
    }
}
