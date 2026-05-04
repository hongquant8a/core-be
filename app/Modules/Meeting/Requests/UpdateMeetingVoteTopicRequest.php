<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingBallotModeEnum;
use App\Modules\Meeting\Enums\MeetingVoteTopicStatusEnum;
use App\Modules\Meeting\Enums\MeetingVoteTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingVoteTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'meeting_agenda_id' => 'nullable|integer|exists:meeting_agendas,id',
            'title' => 'sometimes|string|max:255',
            'vote_type' => ['sometimes', MeetingVoteTypeEnum::rule()],
            'ballot_mode' => ['sometimes', MeetingBallotModeEnum::rule()],
            'show_result_on_projector' => 'nullable|boolean',
            'show_result_on_personal_device' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'status' => ['sometimes', MeetingVoteTopicStatusEnum::rule()],
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
            'meeting_agenda_id' => 'ID chương trình họp',
            'title' => 'Tiêu đề',
            'vote_type' => 'Loại biểu quyết',
            'ballot_mode' => 'Hình thức bỏ phiếu',
            'show_result_on_projector' => 'show result on projector',
            'show_result_on_personal_device' => 'show result on personal device',
            'sort_order' => 'Thứ tự sắp xếp',
            'status' => 'Trạng thái',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'title' => ['description' => 'Tên chương trình biểu quyết.', 'example' => 'Biểu quyết điều chỉnh kế hoạch'],
            'vote_type' => ['description' => 'Loại biểu quyết.', 'example' => 'approve_reject_abstain'],
            'ballot_mode' => ['description' => 'Chế độ biểu quyết.', 'example' => 'public_named'],
            'show_result_on_projector' => ['description' => 'Hiển thị kết quả trên màn chiếu.', 'example' => true],
            'show_result_on_personal_device' => ['description' => 'Hiển thị kết quả trên thiết bị cá nhân.', 'example' => true],
            'status' => ['description' => 'Trạng thái chủ đề biểu quyết.', 'example' => 'opened'],
        ];
    }
}
