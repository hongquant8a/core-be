<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Core\Requests\FilterRequest;

/**
 * Bộ lọc riêng của bảng chính, thêm vào bộ chuẩn của `Core\Requests\FilterRequest`.
 *
 * KHÔNG có `status`: bảng chính không có cột trạng thái (CLAUDE.md B3 sau khi nới). Key
 * `status` thừa hưởng từ lớp cha vẫn được validate nhưng service bỏ qua.
 */
class FilterBeneficiaryRequest extends FilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'residential_area_id' => ['nullable', 'integer'],
            'beneficiary_type_id' => ['nullable', 'integer'],
            'relationship_id' => ['nullable', 'integer'],
            'gender' => ['nullable', GenderEnum::rule()],
            'birth_year_from' => ['nullable', 'integer', 'digits:4'],
            'birth_year_to' => ['nullable', 'integer', 'digits:4', 'gte:birth_year_from'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'residential_area_id.integer' => 'Tổ dân phố/Thôn không hợp lệ.',
            'beneficiary_type_id.integer' => 'Loại đối tượng không hợp lệ.',
            'relationship_id.integer' => 'Mối quan hệ không hợp lệ.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'birth_year_from.digits' => 'Năm sinh từ phải gồm 4 chữ số.',
            'birth_year_to.digits' => 'Năm sinh đến phải gồm 4 chữ số.',
            'birth_year_to.gte' => 'Năm sinh đến phải lớn hơn hoặc bằng năm sinh từ.',
        ]);
    }

    public function queryParameters(): array
    {
        return array_merge(parent::queryParameters(), [
            'residential_area_id' => ['description' => 'Lọc theo tổ dân phố/thôn.', 'example' => 3],
            'beneficiary_type_id' => ['description' => 'Lọc theo loại đối tượng (qua bảng nối).', 'example' => 2],
            'relationship_id' => ['description' => 'Lọc hồ sơ có thân nhân với mối quan hệ này.', 'example' => 1],
            'gender' => ['description' => 'Lọc theo giới tính: male, female, other.', 'example' => 'male'],
            'birth_year_from' => ['description' => 'Năm sinh từ.', 'example' => 1940],
            'birth_year_to' => ['description' => 'Năm sinh đến.', 'example' => 1960],
        ]);
    }
}
