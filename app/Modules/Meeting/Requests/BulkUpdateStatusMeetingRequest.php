<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStatusMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'status' => ['required', MeetingStatusEnum::rule()],
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
            'ids' => 'Danh sách ID',
            'ids.*' => 'Phần tử của Danh sách ID',
            'status' => 'Trạng thái',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'ids' => [
                'description' => 'Danh sách ID cuộc họp.',
                'example' => [1, 2],
            ],
            'status' => [
                'description' => 'Trạng thái mới cho các cuộc họp.',
                'example' => 'published',
            ],
        ];
    }
}
