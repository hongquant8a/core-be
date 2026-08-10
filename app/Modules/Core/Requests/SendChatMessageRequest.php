<?php

namespace App\Modules\Core\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'max' => ':attribute không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'content' => 'Nội dung tin nhắn',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'content' => [
                'description' => 'Nội dung tin nhắn (tối đa 2000 ký tự).',
                'example' => 'Chào mọi người, mình có ý kiến về mục 3.',
            ],
        ];
    }
}
