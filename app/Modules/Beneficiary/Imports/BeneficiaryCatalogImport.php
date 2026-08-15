<?php

namespace App\Modules\Beneficiary\Imports;

use App\Modules\Beneficiary\Enums\CatalogStatusEnum;
use App\Modules\Core\Traits\NormalizesImportValues;
use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Nhập một trong ba bảng danh mục — cùng tập cột nên dùng chung một lớp, nhận model qua
 * constructor.
 */
class BeneficiaryCatalogImport implements SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use NormalizesImportValues, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên',
        'note' => 'Ghi chú',
        'sort_order' => 'Thứ tự',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_LABELS = self::FIELD_LABELS;

    public const TEMPLATE_EXAMPLES = [
        'name' => 'Tổ dân phố 5 (xóa hàng này)',
        'note' => '',
        'sort_order' => '1',
        'status' => 'active',
    ];

    public const REQUIRED_KEYS = ['name'];

    /** @param  class-string<Model>  $modelClass */
    public function __construct(private readonly string $modelClass) {}

    public function prepareForValidation(array $row): array
    {
        $row = $this->translateHeadings($row);

        $row['status'] = $this->normalizeEnum($row['status'] ?? null, CatalogStatusEnum::cases())
            ?? CatalogStatusEnum::Active->value;

        return $row;
    }

    public function model(array $row): ?Model
    {
        $name = trim((string) ($row['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $attributes = [
            'name' => $name,
            'note' => $row['note'] ?? null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'status' => $row['status'] ?? CatalogStatusEnum::Active->value,
        ];

        // UNIQUE(organization_id, name): nhập lại tên đã có là CẬP NHẬT mục đó, không phải
        // lỗi. Bao gồm cả mục đã xoá mềm — dòng đã xoá vẫn chiếm chỗ trong unique index.
        $existing = $this->modelClass::withTrashed()->where('name', $name)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->update($attributes);

            return null;    // đã ghi tay, không trả model cho Excel::import
        }

        return new $this->modelClass($attributes);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'status' => ['nullable', CatalogStatusEnum::rule()],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên.',
            'name.max' => 'Tên không được vượt quá :max ký tự.',
            'note.max' => 'Ghi chú không được vượt quá :max ký tự.',
            'sort_order.integer' => 'Thứ tự phải là số nguyên.',
            'sort_order.min' => 'Thứ tự không được nhỏ hơn :min.',
            'sort_order.max' => 'Thứ tự không được lớn hơn :max.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }

    public static function templateNotes(): array
    {
        return [
            'status' => self::enumHint(CatalogStatusEnum::cases()),
            'name' => 'Tên phải duy nhất trong tổ chức. Nhập lại tên đã có sẽ CẬP NHẬT mục đó.',
            'sort_order' => 'Số nhỏ hiển thị trước trong danh sách chọn. Để trống = 0.',
        ];
    }

    public static function templateOptions(): array
    {
        return [
            'status' => CatalogStatusEnum::values(),
        ];
    }
}
