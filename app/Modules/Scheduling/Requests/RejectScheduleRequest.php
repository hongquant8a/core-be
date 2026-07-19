<?php

namespace App\Modules\Scheduling\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejection_note.required' => 'Lý do từ chối không được để trống.',
            'rejection_note.string' => 'Lý do từ chối phải là chuỗi ký tự.',
            'rejection_note.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'rejection_note' => 'Lý do từ chối',
        ];
    }
}
