<?php

namespace App\Modules\Meeting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenMeetingVoteTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:65535'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ];
    }

    public function messages(): array
    {
        return [
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'max' => ':attribute không được vượt quá :max.',
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => 'Nội dung biểu quyết',
            'duration_minutes' => 'Thời lượng (phút)',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'description' => [
                'description' => 'Nội dung diễn giải hiển thị trên popup biểu quyết. Nếu null, BE giữ nguyên giá trị cũ.',
                'example' => 'Biểu quyết thông qua nghị quyết phân bổ ngân sách 2026.',
            ],
            'duration_minutes' => [
                'description' => 'Thời lượng phiên biểu quyết tính bằng phút (FE tự đếm ngược, BE chỉ lưu giá trị tham chiếu).',
                'example' => 5,
            ],
        ];
    }
}
