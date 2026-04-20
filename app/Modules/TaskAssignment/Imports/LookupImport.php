<?php

namespace App\Modules\TaskAssignment\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LookupImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên',
        'description' => 'Mô tả',
        'status' => 'Trạng thái',
    ];

    /** Subset xuất ra template — chỉ field required theo StoreLookupRequest. */
    public const TEMPLATE_LABELS = [
        'name' => 'Tên',
        'status' => 'Trạng thái',
    ];

    public function __construct(private string $modelClass) {}

    public function model(array $row)
    {
        $model = app($this->modelClass);

        return $model->newInstance([
            'name' => $row['name'] ?? null,
            'description' => $row['description'] ?? null,
            'status' => $row['status'] ?? 'active',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);

        $data['name'] = isset($data['name']) ? (string) $data['name'] : null;

        return $data;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên không được để trống.',
            'name.string' => 'Tên phải là một chuỗi ký tự.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên',
            'description' => 'Mô tả',
            'status' => 'Trạng thái',
        ];
    }
}
