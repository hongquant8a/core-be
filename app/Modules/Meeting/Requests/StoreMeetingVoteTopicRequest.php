<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingBallotModeEnum;
use App\Modules\Meeting\Enums\MeetingVoteTopicStatusEnum;
use App\Modules\Meeting\Enums\MeetingVoteTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingVoteTopicRequest extends FormRequest
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
            'title' => 'required|string|max:255',
            'vote_type' => ['required', MeetingVoteTypeEnum::rule()],
            'ballot_mode' => ['required', MeetingBallotModeEnum::rule()],
            'show_result_on_projector' => 'nullable|boolean',
            'show_result_on_personal_device' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'status' => ['nullable', MeetingVoteTopicStatusEnum::rule()],
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
            'meeting_id' => ['description' => 'ID cuộc họp.', 'example' => 1],
            'meeting_agenda_id' => ['description' => 'ID chương trình họp liên quan.', 'example' => 2],
            'title' => ['description' => 'Tên chương trình biểu quyết.', 'example' => 'Thông qua kế hoạch A'],
            'vote_type' => ['description' => 'Loại biểu quyết.', 'example' => 'agree_disagree_abstain'],
            'ballot_mode' => ['description' => 'Chế độ biểu quyết.', 'example' => 'anonymous'],
            'show_result_on_projector' => ['description' => 'Hiển thị kết quả trên màn chiếu.', 'example' => true],
            'show_result_on_personal_device' => ['description' => 'Hiển thị kết quả trên thiết bị cá nhân.', 'example' => false],
            'sort_order' => ['description' => 'Thứ tự hiển thị.', 'example' => 1],
            'status' => ['description' => 'Trạng thái chủ đề biểu quyết.', 'example' => 'draft'],
        ];
    }
}
