<?php

namespace App\Modules\Scheduling\Requests;

use App\Modules\Scheduling\Enums\ScheduleStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_type'          => ['nullable', 'string'],
            'content'              => ['nullable', 'string'],
            'location'             => ['nullable', 'string', 'max:500'],
            'session'              => ['nullable', 'string'],
            'date_time'            => ['nullable', 'string'],
            'status'               => ['nullable'],
            'host_id'              => ['nullable', 'integer', 'exists:users,id'],
            'host_text'            => ['nullable', 'string', 'max:255'],
            'driver_id'            => ['nullable', 'integer', 'exists:users,id'],
            'driver_text'          => ['nullable', 'string', 'max:255'],
            'preparation_unit'     => ['nullable', 'string', 'max:500'],
            'departments_text'     => ['nullable', 'string'],
            'participants'         => ['nullable', 'array'],
            'participants.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'participants.*.group_id' => ['nullable', 'integer', 'exists:notification_groups,id'],
            'participants.*.display_name' => ['nullable', 'string', 'max:255'],
            'reminders'                      => ['nullable', 'array'],
            'reminders.*.type'               => ['nullable', 'string', 'in:instant,scheduled'],
            'remove_media_ids'     => ['nullable', 'array'],
            'remove_media_ids.*'   => ['integer'],
            'is_important'         => ['nullable', 'boolean'],
            'participants_text'    => ['nullable', 'string'],
            'participant_count'    => ['nullable', 'string', 'max:50'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'nature'               => ['nullable', 'string'],
            'attachments'            => ['nullable', 'array'],
            'attachments.*.id'       => ['nullable', 'integer'],
            'attachments.*.name'     => ['nullable', 'string', 'max:255'],
            'attachment_names'       => ['nullable', 'array'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        if (is_array($validated)) {
            // Normalize title / content
            if (array_key_exists('content', $validated) || array_key_exists('title', $validated)) {
                $validated['content'] = ($validated['content'] ?? null) ?: ($validated['title'] ?? null) ?: '';
            }

            // Normalize date
            if (array_key_exists('event_date', $validated) || array_key_exists('date', $validated)) {
                $validated['event_date'] = ($validated['event_date'] ?? null) ?: ($validated['date'] ?? null);
            }

            // Normalize host & driver
            if (array_key_exists('host_id', $validated) || array_key_exists('host_user_id', $validated)) {
                $validated['host_id'] = ($validated['host_id'] ?? null) ?: ($validated['host_user_id'] ?? null);
            }
            if (array_key_exists('driver_id', $validated) || array_key_exists('driver_user_id', $validated)) {
                $validated['driver_id'] = ($validated['driver_id'] ?? null) ?: ($validated['driver_user_id'] ?? null);
            }

            // Normalize preparation unit
            if (array_key_exists('preparation_unit', $validated) || array_key_exists('preparation_location', $validated)) {
                $validated['preparation_unit'] = ($validated['preparation_unit'] ?? null) ?: ($validated['preparation_location'] ?? null);
            }

            // Normalize status
            if (array_key_exists('status', $validated)) {
                $status = $validated['status'];
                if (is_string($status)) {
                    $status = strtoupper($status);
                    $validated['status'] = match ($status) {
                        'DRAFT' => ScheduleStatus::DRAFT->value,
                        'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
                        default => (int)$status,
                    };
                } else {
                    $validated['status'] = (int)$status;
                }
            }

            // Normalize session if it's legacy session name (e.g. MORNING)
            if (array_key_exists('session', $validated)) {
                $session = $validated['session'];
                if (in_array($session, ['MORNING', 'AFTERNOON', 'EVENING'], true)) {
                    $validated['session'] = match ($session) {
                        'MORNING' => 'S',
                        'AFTERNOON' => 'C',
                        'EVENING' => 'T',
                    };
                }
            }
        }
        return $validated;
    }

    public function messages(): array
    {
        return [
            'module_type.string' => 'Loại chương trình phải là chuỗi ký tự.',
            'content.string' => 'Nội dung phải là chuỗi ký tự.',
            'location.string' => 'Địa điểm phải là chuỗi ký tự.',
            'location.max' => 'Địa điểm không được vượt quá 500 ký tự.',
            'session.string' => 'Buổi phải là chuỗi ký tự.',
            'date_time.string' => 'Thời gian phải là chuỗi ký tự.',
            'host_id.integer' => 'Người chủ trì phải là số nguyên.',
            'host_id.exists' => 'Người chủ trì không tồn tại trong hệ thống.',
            'host_text.string' => 'Người chủ trì (ghi chú) phải là chuỗi ký tự.',
            'host_text.max' => 'Người chủ trì (ghi chú) không được vượt quá 255 ký tự.',
            'driver_id.integer' => 'Lái xe phải là số nguyên.',
            'driver_id.exists' => 'Lái xe không tồn tại trong hệ thống.',
            'driver_text.string' => 'Lái xe (ghi chú) phải là chuỗi ký tự.',
            'driver_text.max' => 'Lái xe (ghi chú) không được vượt quá 255 ký tự.',
            'preparation_unit.string' => 'Đơn vị chuẩn bị phải là chuỗi ký tự.',
            'preparation_unit.max' => 'Đơn vị chuẩn bị không được vượt quá 500 ký tự.',
            'departments_text.string' => 'Đơn vị tham gia phải là chuỗi ký tự.',
            'participants.array' => 'Danh sách người tham gia phải là một mảng.',
            'participants.*.user_id.integer' => 'Người tham gia phải là số nguyên.',
            'participants.*.user_id.exists' => 'Người tham gia không tồn tại trong hệ thống.',
            'participants.*.group_id.integer' => 'Nhóm tham gia phải là số nguyên.',
            'participants.*.group_id.exists' => 'Nhóm tham gia không tồn tại trong hệ thống.',
            'participants.*.display_name.string' => 'Tên hiển thị phải là chuỗi ký tự.',
            'participants.*.display_name.max' => 'Tên hiển thị không được vượt quá 255 ký tự.',
            'reminders.array' => 'Danh sách nhắc nhở phải là một mảng.',
            'reminders.*.type.string' => 'Loại nhắc nhở phải là chuỗi ký tự.',
            'reminders.*.type.in' => 'Loại nhắc nhở không hợp lệ.',
            'remove_media_ids.array' => 'Danh sách ID tệp cần xóa phải là một mảng.',
            'remove_media_ids.*.integer' => 'ID tệp cần xóa phải là số nguyên.',
            'is_important.boolean' => 'Trường quan trọng phải là true hoặc false.',
            'participants_text.string' => 'Người tham gia (ghi chú) phải là chuỗi ký tự.',
            'participant_count.string' => 'Số lượng người tham gia phải là chuỗi ký tự.',
            'participant_count.max' => 'Số lượng người tham gia không được vượt quá 50 ký tự.',
            'sort_order.integer' => 'Thứ tự sắp xếp phải là số nguyên.',
            'sort_order.min' => 'Thứ tự sắp xếp không được nhỏ hơn 0.',
            'nature.string' => 'Tính chất phải là chuỗi ký tự.',
            'attachments.array' => 'Danh sách tệp đính kèm phải là một mảng.',
            'attachments.*.id.integer' => 'ID tệp đính kèm phải là số nguyên.',
            'attachments.*.name.string' => 'Tên tệp đính kèm phải là chuỗi ký tự.',
            'attachments.*.name.max' => 'Tên tệp đính kèm không được vượt quá 255 ký tự.',
            'attachment_names.array' => 'Danh sách tên tệp đính kèm phải là một mảng.',
        ];
    }

    public function bodyParameters(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [
            'module_type' => 'Loại chương trình',
            'content' => 'Nội dung',
            'location' => 'Địa điểm',
            'session' => 'Buổi',
            'date_time' => 'Thời gian',
            'status' => 'Trạng thái',
            'host_id' => 'Người chủ trì',
            'host_text' => 'Người chủ trì (ghi chú)',
            'driver_id' => 'Lái xe',
            'driver_text' => 'Lái xe (ghi chú)',
            'preparation_unit' => 'Đơn vị chuẩn bị',
            'departments_text' => 'Đơn vị tham gia',
            'participants' => 'Danh sách người tham gia',
            'participants.*.user_id' => 'Người tham gia',
            'participants.*.group_id' => 'Nhóm tham gia',
            'participants.*.display_name' => 'Tên hiển thị',
            'reminders' => 'Danh sách nhắc nhở',
            'reminders.*.type' => 'Loại nhắc nhở',
            'remove_media_ids' => 'Danh sách ID tệp cần xóa',
            'remove_media_ids.*' => 'ID tệp cần xóa',
            'is_important' => 'Quan trọng',
            'participants_text' => 'Người tham gia (ghi chú)',
            'participant_count' => 'Số lượng người tham gia',
            'sort_order' => 'Thứ tự sắp xếp',
            'nature' => 'Tính chất',
            'attachments' => 'Danh sách tệp đính kèm',
            'attachments.*.id' => 'ID tệp đính kèm',
            'attachments.*.name' => 'Tên tệp đính kèm',
            'attachment_names' => 'Danh sách tên tệp đính kèm',
        ];
    }
}
