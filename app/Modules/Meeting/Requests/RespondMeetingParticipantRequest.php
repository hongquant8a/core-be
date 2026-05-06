<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingParticipantResponseStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondMeetingParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'response_status' => [
                'required',
                Rule::in([
                    MeetingParticipantResponseStatusEnum::Accepted->value,
                    MeetingParticipantResponseStatusEnum::Declined->value,
                ]),
            ],
            'absence_reason' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'in' => ':attribute không hợp lệ (chỉ accepted hoặc declined).',
            'max' => ':attribute không được vượt quá :max ký tự.',
        ];
    }

    public function attributes(): array
    {
        return [
            'response_status' => 'Trạng thái phản hồi',
            'absence_reason' => 'Lý do vắng',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'response_status' => ['description' => 'accepted (xác nhận tham gia) | declined (từ chối tham gia).', 'example' => 'accepted'],
            'absence_reason' => ['description' => 'Lý do (chỉ áp dụng khi declined).', 'example' => 'Có công tác đột xuất'],
        ];
    }
}
