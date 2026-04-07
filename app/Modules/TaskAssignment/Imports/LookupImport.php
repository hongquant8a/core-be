<?php

namespace App\Modules\TaskAssignment\Imports;

use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class LookupImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures;

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
