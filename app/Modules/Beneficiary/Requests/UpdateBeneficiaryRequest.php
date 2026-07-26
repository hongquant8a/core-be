<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Concerns\AuthorizesBeneficiarySections;
use App\Modules\Beneficiary\Concerns\ValidatesBeneficiarySections;
use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBeneficiaryRequest extends BaseRequest
{
    use AuthorizesBeneficiarySections, ValidatesBeneficiarySections;

    public function authorize(): bool
    {
        return $this->authorizeSections();
    }

    public function rules(): array
    {
        return [
            'household_id' => 'nullable|integer|exists:beneficiary_households,id',
            'residential_area_id' => 'nullable|integer|exists:beneficiary_residential_areas,id',
            'full_name' => 'sometimes|string|max:255',
            'date_of_birth' => 'nullable|date',
            'birth_year' => 'nullable|string|max:20',
            'gender' => ['sometimes', GenderEnum::rule()],
            'id_number' => [
                'nullable', 'string', 'max:255',
                // Trùng CCCD trong cùng tổ chức là không hợp lệ, bỏ qua chính hồ sơ đang sửa.
                Rule::unique('beneficiaries', 'id_number')
                    ->where('organization_id', getPermissionsTeamId())
                    ->ignore($this->route('beneficiary')),
            ],
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:255',
            'note' => 'nullable|string',

            'classifications' => 'nullable|array',
            'dependents' => 'nullable|array',
            'documents' => 'nullable|array',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateSections($validator));
    }

    public function messages(): array
    {
        return [
            'full_name.string' => 'Họ tên phải là một chuỗi ký tự.',
            'full_name.max' => 'Họ tên không được vượt quá 255 ký tự.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'id_number.unique' => 'CCCD/CMND này đã tồn tại trong danh sách người có công.',
            'household_id.exists' => 'Hộ gia đình không tồn tại.',
            'residential_area_id.exists' => 'Tổ dân phố không tồn tại.',
            'latitude.between' => 'Vĩ độ phải trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải trong khoảng -180 đến 180.',
            'classifications.array' => 'Danh sách loại đối tượng phải là một mảng.',
            'dependents.array' => 'Danh sách thân nhân phải là một mảng.',
            'documents.array' => 'Danh sách tài liệu phải là một mảng.',
        ];
    }

    /**
     * 3 mảng con là TRẠNG THÁI ĐẦY ĐỦ: gửi mảng nào thì thay thế toàn bộ danh sách đó (dòng cũ
     * bị xóa hết rồi tạo lại theo mảng gửi lên), không gửi khóa thì giữ nguyên. Gửi mảng rỗng
     * `[]` = xóa sạch danh sách đó.
     */
    public function bodyParameters(): array
    {
        return [
            'household_id' => ['description' => 'ID hộ gia đình.', 'example' => 1],
            'residential_area_id' => ['description' => 'ID tổ dân phố / thôn của người có công. Độc lập với tổ dân phố của hộ.', 'example' => 1],
            'full_name' => ['description' => 'Họ tên.', 'example' => 'Trần Văn B'],
            'date_of_birth' => ['description' => 'Ngày sinh (nếu biết đầy đủ ngày/tháng/năm).', 'example' => '1950-05-20'],
            'birth_year' => ['description' => 'Năm sinh dạng text (dùng khi không rõ đầy đủ ngày/tháng sinh).', 'example' => '1950'],
            'gender' => ['description' => 'Giới tính.', 'example' => 'male'],
            'id_number' => ['description' => 'CCCD/CMND.', 'example' => '049123456789'],
            'address' => ['description' => 'Địa chỉ.', 'example' => null],
            'latitude' => ['description' => 'Vĩ độ (tra cứu bản đồ).', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ (tra cứu bản đồ).', 'example' => 108.2208],
            'phone' => ['description' => 'Số điện thoại.', 'example' => null],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'classifications' => ['description' => 'THAY THẾ toàn bộ danh sách loại đối tượng. Mỗi phần tử: `type` (bắt buộc), `decision_no`, `decision_date`, `issued_by`, `is_primary`. Không gửi khóa = giữ nguyên.', 'example' => []],
            'dependents' => ['description' => 'THAY THẾ toàn bộ liên kết thân nhân. Mỗi phần tử: `dependent_id` + `relationship_type` (bắt buộc), `is_primary`, `note`. Tối đa 1 phần tử có `is_primary` = true (thân nhân chính). Không gửi khóa = giữ nguyên.', 'example' => []],
            'documents' => ['description' => 'THAY THẾ toàn bộ danh sách tài liệu. Mỗi phần tử: `name` (bắt buộc), `note`. Tập tin đính kèm của dòng cũ bị xóa theo — upload lại qua `beneficiary-documents`. Không gửi khóa = giữ nguyên.', 'example' => []],
        ];
    }

    public function attributes(): array
    {
        return [
            'household_id' => 'Hộ gia đình',
            'residential_area_id' => 'Tổ dân phố',
            'full_name' => 'Họ tên',
            'date_of_birth' => 'Ngày sinh',
            'birth_year' => 'Năm sinh',
            'gender' => 'Giới tính',
            'id_number' => 'CCCD/CMND',
            'address' => 'Địa chỉ',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
            'phone' => 'Số điện thoại',
            'note' => 'Ghi chú',
            'classifications' => 'Danh sách loại đối tượng',
            'dependents' => 'Danh sách thân nhân',
            'documents' => 'Danh sách tài liệu',
        ];
    }
}
