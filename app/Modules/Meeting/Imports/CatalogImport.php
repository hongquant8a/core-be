<?php

namespace App\Modules\Meeting\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import chung cho 4 catalog: MeetingType / MeetingLocation / MeetingDocumentType / MeetingAttendeeGroup.
 * Cột địa chỉ + Google Maps URL chỉ được mass-assign khi model có khai báo trong $fillable.
 */
class CatalogImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên',
        'description' => 'Mô tả',
        'address' => 'Địa chỉ',
        'google_maps_url' => 'Google Maps URL',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_LABELS = [
        'name' => 'Tên',
        'description' => 'Mô tả',
    ];

    public const TEMPLATE_LABELS_LOCATION = [
        'name' => 'Tên',
        'description' => 'Mô tả',
        'address' => 'Địa chỉ',
        'google_maps_url' => 'Google Maps URL',
    ];

    public function __construct(private string $modelClass) {}

    public function model(array $row)
    {
        /** @var Model $model */
        $model = app($this->modelClass);
        $fillable = $model->getFillable();

        $payload = [
            'name' => $row['name'] ?? null,
            'description' => $row['description'] ?? null,
            'status' => $row['status'] ?? 'active',
            'organization_id' => function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null,
        ];

        if (in_array('address', $fillable, true)) {
            $payload['address'] = $row['address'] ?? null;
            $payload['google_maps_url'] = $row['google_maps_url'] ?? null;
        }

        return $model->newInstance(array_intersect_key($payload, array_flip($fillable)));
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
            'status' => 'nullable|in:active,inactive',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên không được để trống.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên',
            'status' => 'Trạng thái',
        ];
    }
}
