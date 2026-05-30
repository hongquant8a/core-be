<?php

namespace App\Modules\Scheduling\Imports;

use App\Modules\Core\Traits\TranslatesExcelHeadings;
use App\Modules\Scheduling\Models\Schedule;
use App\Modules\Scheduling\Enums\ModuleTypeEnum;
use App\Modules\Scheduling\Enums\SessionTypeEnum;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use App\Modules\Scheduling\Enums\NatureEnum;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ScheduleImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use Importable, SkipsFailures, TranslatesExcelHeadings;

    public const FIELD_LABELS = [
        'module_type' => 'Phân hệ',
        'event_date' => 'Ngày',
        'start_time' => 'Giờ bắt đầu',
        'end_time' => 'Giờ kết thúc',
        'session' => 'Buổi',
        'content' => 'Nội dung',
        'location' => 'Địa điểm',
        'host_text' => 'Chủ trì',
        'participants_text' => 'Thành phần',
        'car_info' => 'Thông tin xe',
    ];

    public const TEMPLATE_LABELS = [
        'module_type' => 'Phân hệ (EXECUTIVE hoặc OFFICE)',
        'event_date' => 'Ngày (Y-m-d)',
        'start_time' => 'Giờ bắt đầu (H:i)',
        'end_time' => 'Giờ kết thúc (H:i)',
        'session' => 'Buổi (S hoặc C hoặc T)',
        'content' => 'Nội dung lịch công tác',
        'location' => 'Địa điểm họp/công tác',
        'host_text' => 'Tên người/chức vụ chủ trì',
        'participants_text' => 'Thành phần tham dự',
        'car_info' => 'Thông tin xe phục vụ',
    ];

    public const TEMPLATE_EXAMPLES = [
        'module_type' => 'EXECUTIVE',
        'event_date' => '2026-06-01',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'session' => 'S',
        'content' => 'Họp giao ban định kỳ tuần 23',
        'location' => 'Phòng họp số 1',
        'host_text' => 'Giám đốc sở',
        'participants_text' => 'Toàn thể lãnh đạo phòng ban',
        'car_info' => 'Xe BKS 29A-12345',
    ];

    public function model(array $row)
    {
        $orgId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        
        $carbonDate = Carbon::parse($row['event_date']);
        $weekNumber = $carbonDate->weekOfYear;
        $year = $carbonDate->year;

        $session = $row['session'] ?? null;
        if (!$session && !empty($row['start_time'])) {
            $session = SessionTypeEnum::fromTime($row['start_time'])->value;
        }

        return new Schedule([
            'organization_id' => $orgId,
            'module_type' => $row['module_type'] ?? ModuleTypeEnum::Executive->value,
            'event_date' => $row['event_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'] ?? null,
            'session' => $session ?? 'S',
            'content' => $row['content'],
            'location' => $row['location'],
            'host_text' => $row['host_text'] ?? null,
            'participants_text' => $row['participants_text'] ?? null,
            'car_info' => $row['car_info'] ?? null,
            'nature' => NatureEnum::Host->value,
            'status' => ScheduleStatusEnum::Draft->value,
            'week_number' => $weekNumber,
            'year' => $year,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    public function prepareForValidation($data, $index)
    {
        $data = $this->translateHeadings($data);
        return $data;
    }

    public function rules(): array
    {
        return [
            'module_type' => 'required|in:EXECUTIVE,OFFICE',
            'event_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'session' => 'nullable|in:S,C,T',
            'content' => 'required|string|max:65535',
            'location' => 'required|string|max:255',
            'host_text' => 'nullable|string|max:255',
            'participants_text' => 'nullable|string|max:65535',
            'car_info' => 'nullable|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'module_type.required' => 'Phân hệ không được để trống.',
            'module_type.in' => 'Phân hệ phải là EXECUTIVE hoặc OFFICE.',
            'event_date.required' => 'Ngày không được để trống.',
            'event_date.date_format' => 'Ngày phải đúng định dạng Y-m-d.',
            'start_time.required' => 'Giờ bắt đầu không được để trống.',
            'start_time.date_format' => 'Giờ bắt đầu phải đúng định dạng H:i.',
            'end_time.date_format' => 'Giờ kết thúc phải đúng định dạng H:i.',
            'session.in' => 'Buổi phải là S, C hoặc T.',
            'content.required' => 'Nội dung không được để trống.',
            'location.required' => 'Địa điểm không được để trống.',
        ];
    }

    public function customValidationAttributes(): array
    {
        return self::FIELD_LABELS;
    }
}
