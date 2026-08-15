<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Đổi trạng thái một mục danh mục. Chỉ danh mục có nhóm trạng thái — bảng chính
 * `beneficiaries` không có (CLAUDE.md B3 sau khi nới).
 */
class ChangeCatalogStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', CatalogStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return ['status' => 'trạng thái'];
    }

    public function bodyParameters(): array
    {
        return [
            'status' => [
                'description' => 'active (Đang sử dụng) hoặc inactive (Ngừng sử dụng). '
                    .'Chuyển sang inactive không ảnh hưởng hồ sơ đang tham chiếu, chỉ ẩn khỏi danh sách chọn.',
                'example' => 'inactive',
            ],
        ];
    }
}
