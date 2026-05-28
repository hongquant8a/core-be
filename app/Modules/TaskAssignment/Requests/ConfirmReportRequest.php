<?php

namespace App\Modules\TaskAssignment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_note' => 'nullable|string|max:2000',
            'is_done' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'confirm_note.max' => 'Ghi chú xác nhận không được vượt quá 2000 ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'confirm_note' => 'Ghi chú xác nhận',
            'is_done' => 'Hoàn thành',
        ];
    }
}
