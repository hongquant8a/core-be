<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMeetingDiscussionRegistrationAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:meeting_discussion_registration_attachments,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'array' => ':attribute phải là mảng.',
            'integer' => ':attribute phải là số nguyên.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'Danh sách sắp xếp',
            'items.*.id' => 'ID của từng phần tử Danh sách sắp xếp',
            'items.*.sort_order' => 'Thứ tự sắp xếp của từng phần tử Danh sách sắp xếp',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'items' => [
                'description' => 'Danh sách cặp id và sort_order để sắp xếp file đính kèm thảo luận/chất vấn.',
                'example' => [
                    ['id' => 1, 'sort_order' => 1],
                    ['id' => 2, 'sort_order' => 2],
                ],
            ],
        ];
    }
}
