<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ModuleTypeEnum;
use App\Modules\Scheduling\Enums\NatureEnum;
use App\Modules\Scheduling\Enums\ReminderSourceEnum;
use App\Modules\Scheduling\Enums\ScheduleStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_type' => ['required', ModuleTypeEnum::rule()],
            'event_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'end_time' => 'nullable|regex:/^\d{2}:\d{2}(:\d{2})?$/',
            'content' => 'required|string',
            'host_id' => 'required|integer|exists:users,id',
            'location' => 'nullable|string|max:255',
            'preparation_unit' => 'nullable|string|max:255',
            'participant_count' => 'nullable|string|max:50',
            'nature' => ['required', NatureEnum::rule()],
            'driver_id' => 'nullable|integer|exists:users,id',
            'color_code' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'participants_text' => 'nullable|string',
            'departments_text' => 'nullable|string',
            'status' => ['nullable', ScheduleStatusEnum::rule()],
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:51200', // 50MB max
            'recipients' => 'nullable|array',
            'recipients.*.user_id' => 'nullable|integer|exists:users,id',
            'recipients.*.group_id' => 'nullable|integer|exists:notification_groups,id',
            'reminders' => 'nullable|array',
            'reminders.*.minutes_before' => 'required|integer|min:0',
            'reminders.*.channels' => 'required|array',
            'reminders.*.channels.*' => 'string|in:fcm,zalo,sms,inapp,FCM,ZALO,SMS,APP',
            'reminders.*.source' => ['nullable', ReminderSourceEnum::rule()],
            'reminders.*.preset_id' => 'nullable|integer|exists:reminder_presets,id',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute là trường bắt buộc.',
            'string' => ':attribute phải là chuỗi.',
            'integer' => ':attribute phải là số nguyên.',
            'boolean' => ':attribute phải là giá trị đúng/sai.',
            'array' => ':attribute phải là mảng.',
            'file' => ':attribute phải là tệp hợp lệ.',
            'max' => ':attribute không được vượt quá :max.',
            'min' => ':attribute phải lớn hơn hoặc bằng :min.',
            'date_format' => ':attribute không đúng định dạng ngày tháng :format.',
            'regex' => ':attribute không đúng định dạng hoặc mã màu.',
            'exists' => ':attribute không tồn tại trong hệ thống.',
        ];
    }

    public function attributes(): array
    {
        return [
            'module_type' => 'Phân hệ lịch công tác',
            'event_date' => 'Ngày diễn ra',
            'start_time' => 'Giờ bắt đầu',
            'end_time' => 'Giờ kết thúc',
            'content' => 'Nội dung',
            'host_id' => 'Người chủ trì',
            'location' => 'Địa điểm',
            'preparation_unit' => 'Đơn vị chuẩn bị',
            'participant_count' => 'Số lượng người tham gia',
            'nature' => 'Tính chất',
            'driver_id' => 'Lái xe',
            'color_code' => 'Mã màu hiển thị',
            'participants_text' => 'Thành phần tham dự (văn bản)',
            'departments_text' => 'Ban ngành tham dự (văn bản)',
            'status' => 'Trạng thái',
            'attachments' => 'Tài liệu đính kèm',
            'recipients' => 'Danh sách nhận thông báo',
            'reminders' => 'Danh sách mốc nhắc nhở',
        ];
    }
}
