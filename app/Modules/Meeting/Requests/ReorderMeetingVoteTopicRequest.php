<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMeetingVoteTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:meeting_vote_topics,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'numeric' => ':attribute phải là số.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'array' => ':attribute phải là mảng.',
            'file' => ':attribute phải là tệp hợp lệ.',
            'mimes' => ':attribute phải đúng định dạng tệp cho phép.',
            'max' => ':attribute không được vượt quá :max ký tự/phần tử/dung lượng.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'date' => ':attribute phải là ngày hợp lệ.',
            'after_or_equal' => ':attribute phải sau hoặc bằng :date.',
            'in' => ':attribute không hợp lệ.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
            'unique' => ':attribute đã tồn tại.',
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
                'description' => 'Danh sách cặp id và sort_order để sắp xếp chương trình biểu quyết.',
                'example' => [
                    ['id' => 1, 'sort_order' => 1],
                    ['id' => 2, 'sort_order' => 2],
                ],
            ],
        ];
    }
}
