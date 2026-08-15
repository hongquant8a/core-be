<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\GenderEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\BeneficiaryResidentialArea;
use App\Modules\Beneficiary\Services\BeneficiaryCatalogService;
use App\Modules\Core\Traits\NormalizesImportValues;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Nhập danh sách người có công từ Excel.
 *
 * Ràng buộc tối thiểu: chỉ `full_name` bắt buộc. Dồn nhiều rule bắt buộc khiến cán bộ không
 * nhập nổi hàng loạt — dữ liệu thiếu bổ sung sau qua CRUD.
 *
 * Ba danh sách con (đối tượng, thân nhân, tài liệu) KHÔNG import: file phẳng không chở được
 * mảng lồng nhau. Các cột "Danh sách ..." của file export bị bỏ qua ở đây.
 */
class BeneficiaryImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use NormalizesImportValues, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'full_name' => 'Họ và tên',
        'birth_date' => 'Ngày sinh',
        'birth_year' => 'Năm sinh',
        'gender' => 'Giới tính',
        'id_number' => 'CCCD/CMND',
        'phone' => 'Số điện thoại',
        'residential_area' => 'Tổ dân phố/Thôn',
        'address' => 'Địa chỉ',
        'latitude' => 'Vĩ độ',
        'longitude' => 'Kinh độ',
        'note' => 'Ghi chú',
    ];

    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    public const TEMPLATE_EXAMPLES = [
        'full_name' => 'Nguyễn Văn A (xóa hàng này)',
        'birth_date' => '15/03/1950',
        'birth_year' => '1950',
        'gender' => 'male',
        'id_number' => '048050001234',
        'phone' => '0905123456',
        'residential_area' => 'Tổ dân phố 5',
        'address' => '12 Trần Phú',
        'latitude' => '16.0678',
        'longitude' => '108.2208',
        'note' => '',
    ];

    public const REQUIRED_KEYS = ['full_name'];

    public function __construct(private readonly ?BeneficiaryCatalogService $catalogs = new BeneficiaryCatalogService) {}

    /**
     * Chuẩn hoá TRƯỚC khi validate: dịch header tiếng Việt về key kỹ thuật, đổi nhãn enum
     * tiếng Việt về value gốc (để round-trip Export→Import chạy được), và tra ngược danh mục
     * theo TÊN.
     */
    public function prepareForValidation(array $row): array
    {
        $row = $this->translateHeadings($row);

        $row['gender'] = $this->normalizeEnum($row['gender'] ?? null, GenderEnum::cases());
        $row['birth_date'] = $this->normalizeDate($row['birth_date'] ?? null);

        // Cán bộ nhập TÊN tổ dân phố, không phải id. Không khớp (hoặc mục đã ngừng sử dụng)
        // thì để trống — KHÔNG chặn dòng, vì thiếu một trường phụ không đáng bỏ cả bản ghi.
        $row['residential_area_id'] = $this->catalogs->findActiveIdByName(
            BeneficiaryResidentialArea::class,
            $row['residential_area'] ?? null
        );

        // birth_year suy ra từ birth_date khi có, đúng như model tự làm — để rule so khớp
        // dưới đây không báo lỗi giả cho file export vốn có cả hai cột.
        if (! empty($row['birth_date'])) {
            $row['birth_year'] = (int) substr($row['birth_date'], 0, 4);
        }

        return $row;
    }

    public function model(array $row): ?Beneficiary
    {
        $fullName = trim((string) ($row['full_name'] ?? ''));

        if ($fullName === '') {
            return null;
        }

        $attributes = [
            'full_name' => $fullName,
            'birth_date' => $row['birth_date'] ?? null,
            'birth_year' => $row['birth_year'] ?? null,
            'gender' => $row['gender'] ?? null,
            'id_number' => trim((string) ($row['id_number'] ?? '')) ?: null,
            'phone' => trim((string) ($row['phone'] ?? '')) ?: null,
            'residential_area_id' => $row['residential_area_id'] ?? null,
            'address' => $row['address'] ?? null,
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null,
            'note' => $row['note'] ?? null,
        ];

        // UNIQUE(organization_id, id_number) + SoftDeletes: dòng đã xoá mềm vẫn chiếm chỗ
        // trong unique index. Nhập lại CCCD của một hồ sơ đã xoá phải khôi phục hồ sơ đó,
        // không phải ném SQLSTATE 23000 làm hỏng cả file import.
        if ($attributes['id_number']) {
            $existing = Beneficiary::withTrashed()
                ->where('id_number', $attributes['id_number'])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $existing->update($attributes);

                return null;    // đã ghi tay, không trả model cho Excel::import
            }
        }

        return new Beneficiary($attributes);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'birth_year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:'.now()->year],
            'gender' => ['nullable', GenderEnum::rule()],
            'id_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'full_name.required' => 'Vui lòng nhập họ và tên.',
            'full_name.max' => 'Họ và tên không được vượt quá :max ký tự.',
            'birth_date.date' => 'Ngày sinh không hợp lệ.',
            'birth_year.digits' => 'Năm sinh phải gồm 4 chữ số.',
            'birth_year.min' => 'Năm sinh không được nhỏ hơn :min.',
            'birth_year.max' => 'Năm sinh không được lớn hơn :max.',
            'gender.in' => 'Giới tính không hợp lệ.',
            'id_number.max' => 'CCCD/CMND không được vượt quá :max ký tự.',
            'phone.max' => 'Số điện thoại không được vượt quá :max ký tự.',
            'address.max' => 'Địa chỉ không được vượt quá :max ký tự.',
            'latitude.numeric' => 'Vĩ độ phải là số.',
            'latitude.between' => 'Vĩ độ phải nằm trong khoảng :min đến :max.',
            'longitude.numeric' => 'Kinh độ phải là số.',
            'longitude.between' => 'Kinh độ phải nằm trong khoảng :min đến :max.',
            'note.max' => 'Ghi chú không được vượt quá :max ký tự.',
        ];
    }

    /**
     * BẮT BUỘC cho mọi cột enum: cán bộ mở file mẫu phải THẤY giá trị hợp lệ, không phải đoán.
     * Sinh từ enum chứ không hardcode để không lệch khi enum đổi.
     */
    public static function templateNotes(): array
    {
        return [
            'gender' => self::enumHint(GenderEnum::cases()),
            'residential_area' => 'Nhập TÊN tổ dân phố/thôn đúng như trong danh mục. '
                .'Không khớp hoặc đã ngừng sử dụng thì để trống, dòng vẫn được nhập.',
            'birth_date' => 'Định dạng: dd/mm/yyyy hoặc yyyy-mm-dd.',
            'birth_year' => 'Chỉ nhập khi không rõ ngày/tháng. Có Ngày sinh thì cột này tự suy ra.',
        ];
    }

    public static function templateOptions(): array
    {
        return [
            'gender' => GenderEnum::values(),
        ];
    }
}
