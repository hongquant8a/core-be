<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Thân nhân — dạng B.
 *
 * `id_number` KHÔNG unique: một người là thân nhân của hai hồ sơ thì đúng là hai dòng cùng
 * CCCD — hệ quả có chủ đích của việc chọn quan hệ 1–n thay vì n–n.
 */
class SaveBeneficiaryDependentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->route('dependent') !== null;

        return [
            'full_name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:'.now()->year],
            'gender' => ['nullable', GenderEnum::rule()],
            'id_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'residential_area_id' => ['nullable', 'integer', $this->activeCatalogRule('beneficiary_residential_areas')],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:5000'],
            'relationship_id' => ['nullable', 'integer', $this->activeCatalogRule('beneficiary_relationships')],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $birthDate = $this->input('birth_date');
            $birthYear = $this->input('birth_year');

            if ($birthDate && $birthYear && (int) substr($birthDate, 0, 4) !== (int) $birthYear) {
                $validator->errors()->add('birth_year', 'Năm sinh không khớp với ngày tháng năm sinh đã nhập.');
            }

            $lat = $this->input('latitude');
            $lng = $this->input('longitude');

            if (($lat === null) !== ($lng === null)) {
                $validator->errors()->add('latitude', 'Vĩ độ và kinh độ phải nhập cùng nhau.');
            }
        });
    }

    private function activeCatalogRule(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where('organization_id', getPermissionsTeamId())
            ->where('status', 'active')
            ->whereNull('deleted_at');
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ và tên thân nhân.',
            'full_name.max' => 'Họ và tên không được vượt quá :max ký tự.',
            'birth_date.date_format' => 'Ngày sinh phải theo định dạng YYYY-MM-DD.',
            'birth_date.before_or_equal' => 'Ngày sinh không được ở tương lai.',
            'birth_year.digits' => 'Năm sinh phải gồm 4 chữ số.',
            'birth_year.min' => 'Năm sinh không được nhỏ hơn :min.',
            'birth_year.max' => 'Năm sinh không được lớn hơn :max.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'id_number.max' => 'CCCD/CMND không được vượt quá :max ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá :max ký tự.',
            'residential_area_id.exists' => 'Tổ dân phố/Thôn không tồn tại hoặc đã ngừng sử dụng.',
            'address.max' => 'Địa chỉ không được vượt quá :max ký tự.',
            'latitude.between' => 'Vĩ độ phải nằm trong khoảng :min đến :max.',
            'longitude.between' => 'Kinh độ phải nằm trong khoảng :min đến :max.',
            'note.max' => 'Ghi chú không được vượt quá :max ký tự.',
            'relationship_id.exists' => 'Mối quan hệ không tồn tại hoặc đã ngừng sử dụng.',
            'is_primary.boolean' => 'Thân nhân chính phải là true hoặc false.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'họ và tên thân nhân',
            'birth_date' => 'ngày tháng năm sinh',
            'birth_year' => 'năm sinh',
            'gender' => 'giới tính',
            'id_number' => 'CCCD/CMND',
            'phone' => 'số điện thoại',
            'residential_area_id' => 'tổ dân phố/thôn',
            'address' => 'địa chỉ',
            'latitude' => 'vĩ độ',
            'longitude' => 'kinh độ',
            'note' => 'ghi chú',
            'relationship_id' => 'mối quan hệ',
            'is_primary' => 'thân nhân chính',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'full_name' => ['description' => 'Họ và tên thân nhân.', 'example' => 'Trần Thị B'],
            'birth_date' => ['description' => 'Ngày tháng năm sinh (Y-m-d).', 'example' => '1955-07-20'],
            'birth_year' => ['description' => 'Năm sinh — dùng khi không rõ ngày/tháng.', 'example' => 1955],
            'gender' => ['description' => 'Giới tính: male, female, other.', 'example' => 'female'],
            'id_number' => ['description' => 'Số CCCD/CMND.', 'example' => '048155001234'],
            'phone' => ['description' => 'Số điện thoại.', 'example' => '0905987654'],
            'residential_area_id' => ['description' => 'ID tổ dân phố/thôn (phải đang sử dụng).', 'example' => 3],
            'address' => ['description' => 'Địa chỉ.', 'example' => '12 Trần Phú'],
            'latitude' => ['description' => 'Vĩ độ.', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ.', 'example' => 108.2208],
            'note' => ['description' => 'Ghi chú.', 'example' => null],
            'relationship_id' => ['description' => 'ID mối quan hệ (phải đang sử dụng).', 'example' => 2],
            'is_primary' => ['description' => 'Đánh dấu là thân nhân chính. Nhiều nhất một dòng trên mỗi hồ sơ.', 'example' => true],
        ];
    }
}
