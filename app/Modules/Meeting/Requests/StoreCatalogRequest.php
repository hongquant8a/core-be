<?php

namespace App\Modules\Meeting\Requests;

use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:65535',
            'status' => ['required', MeetingCatalogStatusEnum::rule()],
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
                'example' => 'Họp chuyên đề',
            ],
            'description' => [
                'description' => 'Mô tả danh mục.',
                'example' => 'Danh mục phục vụ module meeting.',
            ],
            'status' => [
                'description' => 'Trạng thái danh mục.',
                'example' => 'active',
            ],
            'sort_order' => [
                'description' => 'Thứ tự hiển thị.',
                'example' => 1,
            ],
            'address' => [
                'description' => 'Địa chỉ (áp dụng cho địa điểm họp).',
                'example' => 'Số 1 Trần Phú',
            ],
            'latitude' => [
                'description' => 'Vĩ độ (áp dụng cho địa điểm).',
                'example' => 21.0278,
            ],
            'longitude' => [
                'description' => 'Kinh độ (áp dụng cho địa điểm).',
                'example' => 105.8342,
            ],
            'google_maps_url' => [
                'description' => 'Link Google Maps (áp dụng cho địa điểm).',
                'example' => 'https://maps.google.com/?q=21.0278,105.8342',
            ],
        ];
    }
}
