<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingVoteOptionEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingVoteResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_vote_topic_id' => 'required|integer|exists:meeting_vote_topics,id',
            'meeting_participant_id' => 'required|integer|exists:meeting_participants,id',
            'option' => ['required', MeetingVoteOptionEnum::rule()],
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
            'meeting_vote_topic_id' => 'ID chủ đề biểu quyết',
            'meeting_participant_id' => 'ID người tham dự',
            'option' => 'option',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'meeting_vote_topic_id' => ['description' => 'ID chương trình biểu quyết.', 'example' => 1],
            'meeting_participant_id' => ['description' => 'ID người tham dự bỏ phiếu.', 'example' => 5],
            'option' => ['description' => 'Lựa chọn biểu quyết.', 'example' => 'agree'],
        ];
    }
}
