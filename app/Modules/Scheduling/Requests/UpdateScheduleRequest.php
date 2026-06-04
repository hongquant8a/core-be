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
            'reminders'            => ['nullable', 'array'],
            'files'                => ['nullable', 'array'],
            'remove_media_ids'     => ['nullable', 'array'],
            'remove_media_ids.*'   => ['integer'],
            'is_important'         => ['nullable', 'boolean'],
            'participants_text'    => ['nullable', 'string'],
            'participant_count'    => ['nullable', 'string', 'max:50'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'nature'               => ['nullable', 'string'],
            'attachments'          => ['nullable', 'array'],
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
                        'PENDING' => ScheduleStatus::PENDING->value,
                        'APPROVED', 'PUBLISHED' => ScheduleStatus::PUBLISHED->value,
                        'CANCELLED' => ScheduleStatus::CANCELLED->value,
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
}
