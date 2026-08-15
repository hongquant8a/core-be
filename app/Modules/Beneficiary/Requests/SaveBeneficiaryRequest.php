<?php

namespace App\Modules\Beneficiary\Requests;

use App\Modules\Beneficiary\Enums\GenderEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Dùng chung cho `store` và `update` bản chính.
 */
class SaveBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('beneficiary')?->id;

        return [
            'full_name' => $id ? ['sometimes', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'birth_year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:'.now()->year],
            'gender' => ['nullable', GenderEnum::rule()],
            'id_number' => [
                'nullable', 'string', 'max:20',
                // Scope tenant tường minh: `unique:beneficiaries,id_number` trần là lỗ hổng
                // cross-tenant. whereNull('deleted_at') để hồ sơ đã xoá không chặn nhập lại —
                // service có nhánh restore cho ca đó.
                Rule::unique('beneficiaries', 'id_number')
                    ->where('organization_id', getPermissionsTeamId())
                    ->whereNull('deleted_at')
                    ->ignore($id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'residential_area_id' => ['nullable', 'integer', $this->activeCatalogRule('beneficiary_residential_areas')],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:5000'],
            'lock_version' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Hai cột cùng mô tả một thứ nên không được mâu thuẫn. Service tự suy birth_year
            // từ birth_date, nhưng nếu client gửi cả hai mà lệch thì đó là lỗi nhập liệu cần
            // báo, không phải chuyện im lặng ghi đè.
            $birthDate = $this->input('birth_date');
            $birthYear = $this->input('birth_year');

            if ($birthDate && $birthYear && (int) substr($birthDate, 0, 4) !== (int) $birthYear) {
                $validator->errors()->add(
                    'birth_year',
                    'Năm sinh không khớp với ngày tháng năm sinh đã nhập.'
                );
            }

            // Toạ độ phải đi theo cặp: chỉ một trong hai thì chấm được lên bản đồ cũng không
            // có nghĩa, mà lại lọt qua bộ đếm "hồ sơ có toạ độ" ở stats.
            $lat = $this->input('latitude');
            $lng = $this->input('longitude');

            if (($lat === null) !== ($lng === null)) {
                $validator->errors()->add('latitude', 'Vĩ độ và kinh độ phải nhập cùng nhau.');
            }
        });
    }

    /**
     * Khoá ngoại trỏ danh mục phải scope tenant VÀ chỉ nhận mục đang dùng.
     *
     * `exists:bảng,id` trần là lỗ hổng cross-tenant — tổ chức A gán được danh mục của tổ
     * chức B. Điều kiện `status = active` là phần thực thi quy tắc "inactive chỉ chặn gán
     * mới, không đụng dữ liệu đã gán" — vì vậy nó chỉ có ở store/update, không có ở show.
     */
    protected function activeCatalogRule(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where('organization_id', getPermissionsTeamId())
            ->where('status', 'active')
            ->whereNull('deleted_at');
    }

    public function messages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'full_name.string' => 'Họ và tên phải là chuỗi ký tự.',
            'full_name.max' => 'Họ và tên không được vượt quá :max ký tự.',
            'birth_date.date_format' => 'Ngày sinh phải theo định dạng YYYY-MM-DD.',
            'birth_date.before_or_equal' => 'Ngày sinh không được ở tương lai.',
            'birth_year.integer' => 'Năm sinh phải là số.',
            'birth_year.digits' => 'Năm sinh phải gồm 4 chữ số.',
            'birth_year.min' => 'Năm sinh không được nhỏ hơn :min.',
            'birth_year.max' => 'Năm sinh không được lớn hơn :max.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'id_number.string' => 'CCCD/CMND phải là chuỗi ký tự.',
            'id_number.max' => 'CCCD/CMND không được vượt quá :max ký tự.',
            'id_number.unique' => 'CCCD/CMND này đã tồn tại trong hệ thống.',
            'phone.max' => 'Số điện thoại không được vượt quá :max ký tự.',
            'residential_area_id.integer' => 'Tổ dân phố/Thôn không hợp lệ.',
            'residential_area_id.exists' => 'Tổ dân phố/Thôn không tồn tại hoặc đã ngừng sử dụng.',
            'address.max' => 'Địa chỉ không được vượt quá :max ký tự.',
            'latitude.numeric' => 'Vĩ độ phải là số.',
            'latitude.between' => 'Vĩ độ phải nằm trong khoảng :min đến :max.',
            'longitude.numeric' => 'Kinh độ phải là số.',
            'longitude.between' => 'Kinh độ phải nằm trong khoảng :min đến :max.',
            'note.max' => 'Ghi chú không được vượt quá :max ký tự.',
            'lock_version.string' => 'Phiên bản bản ghi không hợp lệ.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'họ và tên',
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
            'lock_version' => 'phiên bản bản ghi',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'full_name' => ['description' => 'Họ và tên người có công.', 'example' => 'Nguyễn Văn A'],
            'birth_date' => ['description' => 'Ngày tháng năm sinh (Y-m-d).', 'example' => '1950-03-15'],
            'birth_year' => ['description' => 'Năm sinh — dùng khi không rõ ngày/tháng.', 'example' => 1950],
            'gender' => ['description' => 'Giới tính: male, female, other.', 'example' => 'male'],
            'id_number' => ['description' => 'Số CCCD/CMND.', 'example' => '048050001234'],
            'phone' => ['description' => 'Số điện thoại.', 'example' => '0905123456'],
            'residential_area_id' => ['description' => 'ID tổ dân phố/thôn (phải đang sử dụng).', 'example' => 3],
            'address' => ['description' => 'Địa chỉ.', 'example' => '12 Trần Phú'],
            'latitude' => ['description' => 'Vĩ độ.', 'example' => 16.0678],
            'longitude' => ['description' => 'Kinh độ.', 'example' => 108.2208],
            'note' => ['description' => 'Ghi chú.', 'example' => 'Đã xác minh hồ sơ.'],
            'lock_version' => ['description' => 'Token khoá lạc quan (ISO8601), lấy từ response trước.', 'example' => '2026-08-15T10:30:00+07:00'],
        ];
    }
}
