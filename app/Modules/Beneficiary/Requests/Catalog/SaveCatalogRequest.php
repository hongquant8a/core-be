<?php

namespace App\Modules\Beneficiary\Requests\Catalog;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Khuôn chung cho ba danh mục — cùng tập cột, chỉ khác bảng mà rule `unique` trỏ tới.
 *
 * Ba lớp con chỉ khai báo `table()` và tên route param. Tách abstract thay vì tham số hoá
 * runtime vì `unique` cần biết bảng ngay lúc dựng rule, mà FormRequest thì Laravel tự
 * resolve theo type-hint của controller — không có chỗ truyền tham số vào.
 */
abstract class SaveCatalogRequest extends FormRequest
{
    /** Tên bảng để rule `unique` trỏ đúng chỗ. */
    abstract protected function table(): string;

    /** Tên route param của bản ghi đang sửa, để `ignore()` bỏ qua chính nó. */
    abstract protected function routeParam(): string;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route($this->routeParam())?->id;

        return [
            // `name` là định danh duy nhất của mục danh mục (bỏ cột `code`), nên bắt buộc
            // UNIQUE theo tổ chức — import Excel tra ngược hoàn toàn dựa vào nó, hai dòng
            // trùng tên khiến import khớp sai không xác định được.
            'name' => [
                $id ? 'sometimes' : 'required', 'string', 'max:191',
                Rule::unique($this->table(), 'name')
                    ->where('organization_id', getPermissionsTeamId())
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'note' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['sometimes', CatalogStatusEnum::rule()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên.',
            'name.string' => 'Tên phải là chuỗi ký tự.',
            'name.max' => 'Tên không được vượt quá :max ký tự.',
            'name.unique' => 'Tên này đã tồn tại trong danh mục.',
            'note.string' => 'Ghi chú phải là chuỗi ký tự.',
            'note.max' => 'Ghi chú không được vượt quá :max ký tự.',
            'sort_order.integer' => 'Thứ tự phải là số nguyên.',
            'sort_order.min' => 'Thứ tự không được nhỏ hơn :min.',
            'sort_order.max' => 'Thứ tự không được lớn hơn :max.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'tên',
            'note' => 'ghi chú',
            'sort_order' => 'thứ tự',
            'status' => 'trạng thái',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Tên mục danh mục. Duy nhất trong tổ chức.', 'example' => 'Thương binh'],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'sort_order' => ['description' => 'Thứ tự hiển thị trong dropdown, nhỏ trước.', 'example' => 1],
            'status' => [
                'description' => 'Trạng thái: active (Đang sử dụng) hoặc inactive (Ngừng sử dụng). '
                    .'inactive chỉ chặn gán mới, hồ sơ đang tham chiếu giữ nguyên.',
                'example' => 'active',
            ],
        ];
    }
}
