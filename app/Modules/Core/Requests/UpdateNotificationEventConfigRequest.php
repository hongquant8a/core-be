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

    public function messages(): array
    {
        return [
            'enabled.required' => 'Enabled không được để trống.',
            'enabled.boolean' => 'Enabled phải là true hoặc false.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'enabled' => 'Enabled',
        ];
    }
}
