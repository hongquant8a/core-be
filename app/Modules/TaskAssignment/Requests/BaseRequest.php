<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function bodyParameters(): array
    {
        return [];
    }
}
