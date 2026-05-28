<?php

namespace App\Modules\Core\Requests;

use App\Modules\Core\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:organizations,slug',
            'description' => 'nullable|string',
            'status' => ['required', StatusEnum::rule()],
            'parent_id' => 'nullable|exists:organizations,id',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên organization không được để trống.',
            'slug.unique' => 'Slug organization đã tồn tại.',
            'status.in' => 'Trạng thái chỉ chấp nhận active, inactive.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Tên',
            'slug' => 'Slug',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
            'parent_id' => 'Đơn vị cha',
            'sort_order' => 'Thứ tự sắp xếp',
        ];
    }
}
