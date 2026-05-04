<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingDiscussionStatusEnum;
use App\Modules\Meeting\Enums\MeetingDiscussionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingDiscussionRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_id' => 'required|integer|exists:meetings,id',
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
            'meeting_participant_id' => 'required|integer|exists:meeting_participants,id',
            'type' => ['required', MeetingDiscussionTypeEnum::rule()],
            'content' => 'required|string',
            'file' => 'nullable|file|max:10240',
            'status' => ['nullable', MeetingDiscussionStatusEnum::rule()],
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
            'meeting_agenda_id' => 'ID chương trình họp',
            'meeting_participant_id' => 'ID người tham dự',
            'type' => 'type',
            'content' => 'Nội dung',
            'file' => 'Tệp tải lên',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_id' => ['description' => 'ID cuộc họp.', 'example' => 1],
            'meeting_agenda_id' => ['description' => 'ID chương trình họp.', 'example' => 2],
            'meeting_participant_id' => ['description' => 'ID người tham dự đăng ký.', 'example' => 3],
            'type' => ['description' => 'Loại đăng ký.', 'example' => 'discussion'],
            'content' => ['description' => 'Nội dung đăng ký.', 'example' => 'Xin đăng ký phát biểu về nội dung 2'],
            'file' => ['description' => 'Tệp đính kèm nội dung đăng ký (nếu có).'],
            'status' => ['description' => 'Trạng thái đăng ký.', 'example' => 'registered'],
            'sort_order' => ['description' => 'Thứ tự gọi.', 'example' => 1],
        ];
    }
}
