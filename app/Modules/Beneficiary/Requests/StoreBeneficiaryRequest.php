<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Validation\Validator;

class StoreBeneficiaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'household_id' => 'nullable|integer|exists:beneficiary_households,id',
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'birth_year' => 'nullable|string|max:20',
            'gender' => ['required', GenderEnum::rule()],
            'id_number' => 'nullable|string|max:255',
            'injury_rate' => 'nullable|numeric|min:0|max:100',
            'recognition_decision_no' => 'nullable|string|max:255',
            'recognition_date' => 'nullable|date',
            'status' => ['nullable', BeneficiaryStatusEnum::rule()],
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'note' => 'nullable|string',

            'classifications' => 'nullable|array',
        ];
    }

    /**
     * Validate thủ công từng phần tử classifications (thay vì rule dot-notation
     * classifications.*.field) — Scribe không render đúng ví dụ curl cho mảng object lồng
     * qua rule wildcard, gây lỗi "stdClass could not be converted to string" khi
     * scribe:generate build ví dụ bash cho TOÀN BỘ project, không riêng endpoint này.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $classifications = $this->input('classifications', []);

            if (! is_array($classifications)) {
                return;
            }

            $primaryCount = 0;

            foreach ($classifications as $index => $classification) {
                if (! is_array($classification)) {
                    $validator->errors()->add("classifications.{$index}", 'Phân loại không hợp lệ.');

                    continue;
                }

                if (empty($classification['type']) || ! in_array($classification['type'], BeneficiaryTypeEnum::values(), true)) {
                    $validator->errors()->add("classifications.{$index}.type", 'Loại đối tượng không được để trống hoặc không hợp lệ.');
                }
                if (empty($classification['decision_no']) || ! is_string($classification['decision_no']) || strlen($classification['decision_no']) > 255) {
                    $validator->errors()->add("classifications.{$index}.decision_no", 'Số quyết định không được để trống.');
                }
                if (empty($classification['decision_date']) || ! strtotime($classification['decision_date'])) {
                    $validator->errors()->add("classifications.{$index}.decision_date", 'Ngày quyết định không được để trống hoặc không hợp lệ.');
                }
                if (empty($classification['issued_by']) || ! is_string($classification['issued_by']) || strlen($classification['issued_by']) > 255) {
                    $validator->errors()->add("classifications.{$index}.issued_by", 'Cơ quan ban hành không được để trống.');
                }

                if ((bool) ($classification['is_primary'] ?? false)) {
                    $primaryCount++;
                }
            }

            if (count($classifications) > 0 && $primaryCount !== 1) {
                $validator->errors()->add('classifications', 'Phải chọn đúng 1 phân loại là loại chính (is_primary = true).');
            }
        });
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Họ tên không được để trống.',
            'full_name.string' => 'Họ tên phải là một chuỗi ký tự.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'gender.required' => 'Giới tính không được để trống.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'household_id.exists' => 'Hộ gia đình không tồn tại.',
            'injury_rate.numeric' => 'Tỷ lệ thương tật phải là số.',
            'injury_rate.max' => 'Tỷ lệ thương tật không được vượt quá 100.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
            'classifications.array' => 'Danh sách phân loại phải là một mảng.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'household_id' => ['description' => 'ID hộ gia đình.', 'example' => 1],
            'full_name' => ['description' => 'Họ tên.', 'example' => 'Trần Văn B'],
            'date_of_birth' => ['description' => 'Ngày sinh (nếu biết đầy đủ ngày/tháng/năm).', 'example' => '1950-05-20'],
            'birth_year' => ['description' => 'Năm sinh dạng text (dùng khi không rõ đầy đủ ngày/tháng sinh).', 'example' => '1950'],
            'gender' => ['description' => 'Giới tính.', 'example' => 'male'],
            'id_number' => ['description' => 'CCCD/CMND.', 'example' => '049123456789'],
            'injury_rate' => ['description' => 'Tỷ lệ thương tật %.', 'example' => 61],
            'recognition_decision_no' => ['description' => 'Số quyết định công nhận.', 'example' => 'QD-123/2020'],
            'recognition_date' => ['description' => 'Ngày quyết định.', 'example' => '2020-07-15'],
            'status' => ['description' => 'Trạng thái.', 'example' => 'pending'],
            'address' => ['description' => 'Địa chỉ.', 'example' => null],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'phone' => ['description' => 'Số điện thoại.', 'example' => null],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'classifications' => ['description' => 'Danh sách phân loại đối tượng.', 'example' => []],
        ];
    }

    public function attributes(): array
    {
        return [
            'household_id' => 'Hộ gia đình',
            'full_name' => 'Họ tên',
            'date_of_birth' => 'Ngày sinh',
            'birth_year' => 'Năm sinh',
            'gender' => 'Giới tính',
            'id_number' => 'CCCD/CMND',
            'injury_rate' => 'Tỷ lệ thương tật',
            'recognition_decision_no' => 'Số quyết định công nhận',
            'recognition_date' => 'Ngày quyết định',
            'status' => 'Trạng thái',
            'address' => 'Địa chỉ',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
            'classifications' => 'Danh sách phân loại',
        ];
    }
}
