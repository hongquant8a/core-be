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
}
