<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingAgendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s|after_or_equal:start_time',
            'content' => 'required|string',
            'person_in_charge' => 'nullable|string|max:255',
            'allow_discussion_registration' => 'nullable|boolean',
            'discussion_duration_minutes' => 'nullable|integer|min:1|max:600',
            'allow_question_registration' => 'nullable|boolean',
            'question_duration_minutes' => 'nullable|integer|min:1|max:600',
            'allow_vote_registration' => 'nullable|boolean',
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
            'meeting_id' => 'ID cuộc họp',
            'start_time' => 'Thời gian bắt đầu',
            'end_time' => 'Thời gian kết thúc',
            'content' => 'Nội dung',
            'person_in_charge' => 'person in charge',
            'allow_discussion_registration' => 'Cho phép đăng ký thảo luận',
            'discussion_duration_minutes' => 'Thời lượng đăng ký thảo luận (phút)',
            'allow_question_registration' => 'Cho phép đăng ký chất vấn',
            'question_duration_minutes' => 'Thời lượng đăng ký chất vấn (phút)',
            'allow_vote_registration' => 'Cho phép biểu quyết',
            'parent_id' => 'ID bản ghi cha',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => [
                'description' => 'ID cuộc họp.',
                'example' => 1,
            ],
            'start_time' => [
                'description' => 'Giờ bắt đầu (H:i:s).',
                'example' => '08:00:00',
            ],
            'end_time' => [
                'description' => 'Giờ kết thúc (H:i:s).',
                'example' => '08:30:00',
            ],
            'content' => [
                'description' => 'Nội dung chương trình.',
                'example' => 'Báo cáo công tác tuần.',
            ],
            'person_in_charge' => [
                'description' => 'Người phụ trách.',
                'example' => 'Nguyễn Văn A',
            ],
            'allow_discussion_registration' => [
                'description' => 'Cho phép đăng ký thảo luận.',
                'example' => true,
            ],
            'discussion_duration_minutes' => [
                'description' => 'Thời lượng cho phép đăng ký thảo luận (phút). Áp dụng khi allow_discussion_registration=true.',
                'example' => 30,
            ],
            'allow_question_registration' => [
                'description' => 'Cho phép đăng ký chất vấn.',
                'example' => false,
            ],
            'question_duration_minutes' => [
                'description' => 'Thời lượng cho phép đăng ký chất vấn (phút). Áp dụng khi allow_question_registration=true.',
                'example' => 15,
            ],
            'allow_vote_registration' => [
                'description' => 'Cho phép biểu quyết trong chương trình. Khi true → FE bổ sung vote topic gắn meeting_agenda_id = agenda.id. Duration của từng topic ở `meeting_vote_topics.duration_minutes`.',
                'example' => false,
            ],
            'parent_id' => [
                'description' => 'ID chương trình cha nếu là mục con.',
                'example' => null,
            ],
            'sort_order' => [
                'description' => 'Thứ tự hiển thị.',
                'example' => 1,
            ],
        ];
    }
}
