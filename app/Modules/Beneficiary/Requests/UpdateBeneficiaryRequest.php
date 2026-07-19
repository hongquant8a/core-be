<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\BeneficiaryClassification;
use Illuminate\Validation\Validator;

class UpdateBeneficiaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'household_id' => 'nullable|integer|exists:beneficiary_households,id',
            'full_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'nullable|date',
            'birth_year' => 'nullable|string|max:20',
            'gender' => ['sometimes', GenderEnum::rule()],
            'id_number' => 'nullable|string|max:255',
            'injury_rate' => 'nullable|numeric|min:0|max:100',
            'recognition_decision_no' => 'nullable|string|max:255',
            'recognition_date' => 'nullable|date',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'note' => 'nullable|string',

            'classifications' => 'nullable|array',
            'classifications_deleted' => 'nullable|array',
            'classifications_deleted.*' => 'integer',
        ];
    }

    /**
     * Đồng bộ classifications theo quy ước COARSE (xem docs "Quy chuẩn Aggregate & API"):
     * có `id` = cập nhật dòng đó, không có `id` = tạo mới, dòng KHÔNG xuất hiện trong mảng
     * `classifications` = giữ nguyên (không tự xóa) — muốn xóa phải đưa id vào
     * `classifications_deleted` tường minh. `id` gửi lên phải thuộc đúng hồ sơ đang sửa.
     *
     * Validate thủ công (không dùng rule dot-notation `classifications.*.field`) — lý do giống
     * StoreBeneficiaryRequest: Scribe không render đúng ví dụ curl cho mảng object lồng qua rule
     * wildcard.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $beneficiary = $this->route('beneficiary');
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

                if (! empty($classification['id'])) {
                    $belongsToBeneficiary = BeneficiaryClassification::where('id', $classification['id'])
                        ->where('beneficiary_id', $beneficiary->id)
                        ->exists();

                    if (! $belongsToBeneficiary) {
                        $validator->errors()->add("classifications.{$index}.id", 'Phân loại không thuộc hồ sơ này.');
                    }
                }

                if (empty($classification['type']) || ! in_array($classification['type'], BeneficiaryTypeEnum::values(), true)) {
                    $validator->errors()->add("classifications.{$index}.type", 'Loại đối tượng không được để trống hoặc không hợp lệ.');
                }
                if (! empty($classification['decision_no']) && (! is_string($classification['decision_no']) || strlen($classification['decision_no']) > 255)) {
                    $validator->errors()->add("classifications.{$index}.decision_no", 'Số quyết định không hợp lệ.');
                }
                if (! empty($classification['decision_date']) && ! strtotime($classification['decision_date'])) {
                    $validator->errors()->add("classifications.{$index}.decision_date", 'Ngày quyết định không hợp lệ.');
                }
                if (! empty($classification['issued_by']) && (! is_string($classification['issued_by']) || strlen($classification['issued_by']) > 255)) {
                    $validator->errors()->add("classifications.{$index}.issued_by", 'Cơ quan ban hành không hợp lệ.');
                }

                if ((bool) ($classification['is_primary'] ?? false)) {
                    $primaryCount++;
                }
            }

            if ($primaryCount > 1) {
                $validator->errors()->add('classifications', 'Chỉ được chọn tối đa 1 phân loại là loại chính (is_primary = true) trong danh sách gửi lên.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Họ tên phải là một chuỗi ký tự.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'household_id.exists' => 'Hộ gia đình không tồn tại.',
            'injury_rate.numeric' => 'Tỷ lệ thương tật phải là số.',
            'injury_rate.max' => 'Tỷ lệ thương tật không được vượt quá 100.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
            'classifications.array' => 'Danh sách phân loại phải là một mảng.',
            'classifications_deleted.array' => 'Danh sách ID phân loại cần xóa phải là một mảng.',
            'classifications_deleted.*.integer' => 'ID phân loại không hợp lệ.',
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
            'address' => ['description' => 'Địa chỉ.', 'example' => null],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'phone' => ['description' => 'Số điện thoại.', 'example' => null],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'classifications' => ['description' => 'Đồng bộ danh sách phân loại: có `id` = cập nhật dòng đó, không có `id` = tạo mới. Dòng không xuất hiện trong mảng này KHÔNG bị xóa (giữ nguyên) — muốn xóa phải đưa id vào `classifications_deleted`.', 'example' => []],
            'classifications_deleted' => ['description' => 'Danh sách ID phân loại cần xóa.', 'example' => []],
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
            'address' => 'Địa chỉ',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
            'classifications' => 'Danh sách phân loại',
            'classifications_deleted' => 'Danh sách ID phân loại cần xóa',
        ];
    }
}
