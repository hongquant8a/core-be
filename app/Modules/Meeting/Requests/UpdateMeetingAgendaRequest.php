<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after_or_equal:start_time',
            'content' => 'sometimes|string',
            'person_in_charge' => 'nullable|string|max:255',
            'allow_discussion_registration' => 'nullable|boolean',
            'allow_question_registration' => 'nullable|boolean',
            'parent_id' => 'nullable|integer|exists:meeting_agendas,id',
            'sort_order' => 'nullable|integer|min:0',
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
            'start_time' => 'Thời gian bắt đầu',
            'end_time' => 'Thời gian kết thúc',
            'content' => 'Nội dung',
            'person_in_charge' => 'person in charge',
            'allow_discussion_registration' => 'allow discussion registration',
            'allow_question_registration' => 'allow question registration',
            'parent_id' => 'ID bản ghi cha',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'content' => ['description' => 'Nội dung chương trình.', 'example' => 'Thảo luận chuyên đề A'],
            'person_in_charge' => ['description' => 'Người phụ trách.', 'example' => 'Lê Văn C'],
            'allow_discussion_registration' => ['description' => 'Cho phép đăng ký thảo luận.', 'example' => true],
            'allow_question_registration' => ['description' => 'Cho phép đăng ký chất vấn.', 'example' => true],
            'parent_id' => ['description' => 'ID mục cha.', 'example' => null],
            'sort_order' => ['description' => 'Thứ tự hiển thị.', 'example' => 3],
        ];
    }
}
