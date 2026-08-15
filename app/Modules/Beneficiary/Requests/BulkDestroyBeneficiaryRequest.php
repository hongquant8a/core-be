<?php

namespace App\Modules\Beneficiary\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dùng chung cho `bulk-delete` của bản chính và cả ba bảng con — cùng một hình dạng payload
 * `{"ids": [...]}`, không có gì khác nhau để tách riêng.
 *
 * Không kiểm `exists`: query xoá chạy qua quan hệ hoặc qua global scope tenant nên id lạ tự
 * rơi ra ngoài. Thêm `exists` chỉ khiến một id sai làm hỏng cả lô.
 */
class BulkDestroyBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Vui lòng chọn ít nhất một bản ghi để xoá.',
            'ids.array' => 'Danh sách ID phải là mảng.',
            'ids.min' => 'Vui lòng chọn ít nhất một bản ghi để xoá.',
            'ids.max' => 'Chỉ được xoá tối đa :max bản ghi mỗi lần.',
            'ids.*.required' => 'ID không được để trống.',
            'ids.*.integer' => 'ID phải là số nguyên.',
            'ids.*.min' => 'ID không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return ['ids' => 'danh sách ID', 'ids.*' => 'ID'];
    }

    public function bodyParameters(): array
    {
        return [
            'ids' => ['description' => 'Danh sách ID cần xoá.', 'example' => [1, 2, 3]],
        ];
    }
}
