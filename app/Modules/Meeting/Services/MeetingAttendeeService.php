<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Enums\MeetingCatalogStatusEnum;
use App\Modules\Meeting\Exports\MeetingAttendeeExport;
use App\Modules\Meeting\Imports\MeetingAttendeeImport;
use App\Modules\Meeting\Models\MeetingAttendee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingAttendeeService
{
    public function stats(array $filters): array
    {
        $base = MeetingAttendee::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', MeetingCatalogStatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', MeetingCatalogStatusEnum::Inactive->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingAttendee::with(['groups', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingAttendee $meetingAttendee): MeetingAttendee
    {
        return $meetingAttendee->load(['groups', 'creator.media', 'editor.media']);
    }

    public function store(array $validated): MeetingAttendee
    {
        $payload = [...$validated, 'organization_id' => $this->resolveCurrentOrganizationId()];

        return MeetingAttendee::create($payload)->load(['groups', 'creator.media', 'editor.media']);
    }

    public function update(MeetingAttendee $meetingAttendee, array $validated): MeetingAttendee
    {
        $meetingAttendee->update($validated);

        return $meetingAttendee->load(['groups', 'creator.media', 'editor.media']);
    }

    public function destroy(MeetingAttendee $meetingAttendee): void
    {
        $meetingAttendee->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingAttendee::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        MeetingAttendee::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(MeetingAttendee $meetingAttendee, string $status): MeetingAttendee
    {
        $meetingAttendee->update(['status' => $status]);

        return $meetingAttendee->load(['groups', 'creator.media', 'editor.media']);
    }

    /**
     * Danh sách user trong tổ chức hiện tại để FE chọn khi tạo đại biểu.
     * Loại bỏ user đã được link với attendee để tránh trùng. Dùng chung permission
     * `meeting-attendees.store` (không cần `users.index`) — tránh CASL conflict ở FE.
     */
    public function userOptions(array $filters)
    {
        $orgId = $this->resolveCurrentOrganizationId();

        $excludedUserIds = MeetingAttendee::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        return User::query()
            ->with('profile')
            ->where('status', 'active')
            ->when($excludedUserIds, fn ($q) => $q->whereNotIn('id', $excludedUserIds))
            ->whereExists(function ($sub) use ($orgId) {
                $sub->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->whereColumn('model_has_roles.model_id', 'users.id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('model_has_roles.organization_id', $orgId);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit((int) ($filters['limit'] ?? 50))
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->profile?->phone,
            ])
            ->all();
    }

    public function export(array $filters, string $fileName = 'meeting-attendees.xlsx'): BinaryFileResponse
    {
        return Excel::download(new MeetingAttendeeExport($filters), $fileName);
    }

    public function import($file): void
    {
        $import = new MeetingAttendeeImport;
        Excel::import($import, $file);

        $failures = $import->failures();
        if ($failures->isNotEmpty()) {
            $errors = [];
            foreach ($failures as $failure) {
                $row = $failure->row();
                foreach ($failure->errors() as $msg) {
                    $errors["row_{$row}"][] = $msg;
                }
            }
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    private function resolveCurrentOrganizationId(): int
    {
        $organizationId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;

        if (! is_numeric($organizationId) || (int) $organizationId <= 0) {
            throw new ModelNotFoundException('Không xác định được tổ chức làm việc hiện tại.');
        }

        return (int) $organizationId;
    }
}
