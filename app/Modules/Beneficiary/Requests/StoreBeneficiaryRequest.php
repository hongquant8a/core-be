<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\BeneficiaryTypeEnum;
use App\Modules\Beneficiary\Enums\DependentRelationshipEnum;
use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
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
            'household' => 'nullable|array',
            'dependents' => 'nullable|array',
        ];
    }

    /**
     * Validate thủ công từng phần tử classifications/household/dependents (thay vì rule
     * dot-notation `xxx.*.field`) — Scribe không render đúng ví dụ curl cho mảng object lồng
     * qua rule wildcard, gây lỗi "stdClass could not be converted to string" khi
     * scribe:generate build ví dụ bash cho TOÀN BỘ project, không riêng endpoint này.
     *
     * `household`/`dependents` là lối tắt nhập liệu nhanh cho hồ sơ HOÀN TOÀN MỚI — tái dùng
     * nguyên `rules()`/`messages()`/`attributes()` của StoreHouseholdRequest/StoreDependentRequest
     * (chạy qua Validator::make lồng) để không lặp lại danh sách rule, tránh 2 nơi cùng định
     * nghĩa 1 field rồi lệch nhau theo thời gian.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateClassifications($validator);
            $this->validateHousehold($validator);
            $this->validateDependents($validator);
        });
    }

    /**
     * Chỉ bắt buộc `type` (loại đối tượng — thông tin cơ bản nhất). `decision_no`/
     * `decision_date`/`issued_by` là giấy tờ hành chính, cho phép bổ sung sau khi cán bộ
     * có đủ hồ sơ — không chặn nhập liệu ngay từ đầu. `is_primary` chỉ validate "không quá 1",
     * không bắt buộc phải chọn ngay (chưa chọn thì chưa có loại chính, bổ sung sau).
     */
    private function validateClassifications(Validator $validator): void
    {
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
            $validator->errors()->add('classifications', 'Chỉ được chọn tối đa 1 phân loại là loại chính (is_primary = true).');
        }
    }

    /** Chỉ dùng khi KHÔNG gửi `household_id` — tạo hộ mới trong cùng lần tạo hồ sơ. */
    private function validateHousehold(Validator $validator): void
    {
        $household = $this->input('household');

        if ($household === null) {
            return;
        }

        if (! is_array($household)) {
            $validator->errors()->add('household', 'Thông tin hộ gia đình không hợp lệ.');

            return;
        }

        if ($this->filled('household_id')) {
            $validator->errors()->add('household', 'Không được vừa chọn household_id vừa gửi household để tạo hộ mới — chỉ chọn 1 trong 2.');

            return;
        }

        $request = new StoreHouseholdRequest;
        $nested = ValidatorFacade::make($household, $request->rules(), $request->messages(), $request->attributes());

        $this->mergeNestedErrors($validator, $nested, 'household');
    }

    /**
     * Mỗi phần tử = thông tin thân nhân (theo StoreDependentRequest) + relationship_type/
     * eligible_from để tự tạo luôn quan hệ hưởng chế độ với người có công đang tạo.
     */
    private function validateDependents(Validator $validator): void
    {
        $dependents = $this->input('dependents', []);

        if (! is_array($dependents)) {
            return;
        }

        $dependentRequest = new StoreDependentRequest;
        $extraRules = [
            'relationship_type' => ['required', DependentRelationshipEnum::rule()],
            'eligible_from' => 'required|date',
        ];
        $extraMessages = [
            'relationship_type.required' => 'Quan hệ với người có công không được để trống.',
            'relationship_type.in' => 'Quan hệ không hợp lệ.',
            'eligible_from.required' => 'Ngày bắt đầu đủ điều kiện hưởng không được để trống.',
            'eligible_from.date' => 'Ngày bắt đầu đủ điều kiện hưởng không hợp lệ.',
        ];
        $extraAttributes = [
            'relationship_type' => 'Quan hệ',
            'eligible_from' => 'Ngày bắt đầu đủ điều kiện',
        ];

        foreach ($dependents as $index => $dependent) {
            if (! is_array($dependent)) {
                $validator->errors()->add("dependents.{$index}", 'Thân nhân không hợp lệ.');

                continue;
            }

            $nested = ValidatorFacade::make(
                $dependent,
                [...$dependentRequest->rules(), ...$extraRules],
                [...$dependentRequest->messages(), ...$extraMessages],
                [...$dependentRequest->attributes(), ...$extraAttributes],
            );

            $this->mergeNestedErrors($validator, $nested, "dependents.{$index}");
        }
    }

    private function mergeNestedErrors(Validator $parent, Validator $nested, string $prefix): void
    {
        if (! $nested->fails()) {
            return;
        }

        foreach ($nested->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $parent->errors()->add("{$prefix}.{$field}", $message);
            }
        }
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
            'household.array' => 'Thông tin hộ gia đình không hợp lệ.',
            'dependents.array' => 'Danh sách thân nhân phải là một mảng.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'household_id' => ['description' => 'ID hộ gia đình có sẵn — không dùng cùng lúc với `household`.', 'example' => 1],
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
            'classifications' => ['description' => 'Danh sách phân loại đối tượng. Mỗi phần tử chỉ bắt buộc `type` — `decision_no`/`decision_date`/`issued_by` có thể bổ sung sau khi có đủ giấy tờ.', 'example' => []],
            'household' => ['description' => 'Tạo hộ gia đình mới ngay khi tạo hồ sơ — chỉ dùng khi KHÔNG gửi `household_id`. Các trường giống StoreHouseholdRequest, bắt buộc `head_name`.', 'example' => null],
            'dependents' => ['description' => 'Danh sách thân nhân tạo kèm — mỗi phần tử gồm các trường thân nhân (bắt buộc `full_name`, `gender`) cộng thêm `relationship_type`, `eligible_from` (bắt buộc) để tự tạo quan hệ hưởng chế độ.', 'example' => []],
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
            'household' => 'Hộ gia đình (tạo mới)',
            'dependents' => 'Danh sách thân nhân (tạo kèm)',
        ];
    }
}
