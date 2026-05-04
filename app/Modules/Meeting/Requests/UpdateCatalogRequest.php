<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['sometimes', MeetingCatalogStatusEnum::rule()],
            'sort_order' => 'nullable|integer|min:0',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_maps_url' => 'nullable|url|max:255',
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
            'name' => 'Tên',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'sort_order' => 'Thứ tự sắp xếp',
            'address' => 'Địa chỉ',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'google_maps_url' => 'google maps url',
        ];
    }
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Tên danh mục.',
                'example' => 'Họp định kỳ',
            ],
            'description' => [
                'description' => 'Mô tả danh mục.',
                'example' => 'Cập nhật mô tả danh mục meeting.',
            ],
            'status' => [
                'description' => 'Trạng thái danh mục.',
                'example' => 'active',
            ],
            'sort_order' => [
                'description' => 'Thứ tự hiển thị.',
                'example' => 2,
            ],
            'address' => [
                'description' => 'Địa chỉ (áp dụng cho địa điểm).',
                'example' => 'Số 2 Lê Lợi',
            ],
        ];
    }
}
