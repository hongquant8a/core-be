<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HighlightAgendaMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agenda_id' => ['nullable', 'integer', 'exists:meeting_agendas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'integer' => ':attribute phải là số nguyên.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'agenda_id' => 'Chương trình',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'agenda_id' => [
                'description' => 'ID chương trình cần highlight lên màn chiếu. Null để bỏ highlight.',
                'example' => 5,
            ],
        ];
    }
}
