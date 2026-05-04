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
 * Cột địa lý chỉ được mass-assign khi model có khai báo trong $fillable.
 */
class CatalogImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'name' => 'Tên',
        'description' => 'Mô tả',
        'address' => 'Địa chỉ',
        'latitude' => 'Vĩ độ',
        'longitude' => 'Kinh độ',
        'google_maps_url' => 'Google Maps URL',
        'sort_order' => 'Thứ tự',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_LABELS = [
        'name' => 'Tên',
        'status' => 'Trạng thái',
    ];

    public const TEMPLATE_LABELS_LOCATION = [
        'name' => 'Tên',
        'address' => 'Địa chỉ',
        'latitude' => 'Vĩ độ',
        'longitude' => 'Kinh độ',
        'google_maps_url' => 'Google Maps URL',
        'status' => 'Trạng thái',
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
            'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 0,
            'status' => $row['status'] ?? 'active',
            'organization_id' => function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null,
        ];

        if (in_array('address', $fillable, true)) {
            $payload['address'] = $row['address'] ?? null;
            $payload['latitude'] = isset($row['latitude']) ? (float) $row['latitude'] : null;
            $payload['longitude'] = isset($row['longitude']) ? (float) $row['longitude'] : null;
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
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Tên không được để trống.',
            'name.max' => 'Tên không được vượt quá 255 ký tự.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
            'latitude.between' => 'Vĩ độ phải nằm trong khoảng -90 đến 90.',
            'longitude.between' => 'Kinh độ phải nằm trong khoảng -180 đến 180.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return [
            'name' => 'Tên',
            'status' => 'Trạng thái',
            'latitude' => 'Vĩ độ',
            'longitude' => 'Kinh độ',
        ];
    }
}
