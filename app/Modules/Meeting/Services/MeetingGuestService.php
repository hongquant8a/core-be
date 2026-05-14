<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\MeetingGuest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MeetingGuestService
{
    public function stats(array $filters): array
    {
        $base = MeetingGuest::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return MeetingGuest::with(['creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingGuest $guest): MeetingGuest
    {
        return $guest->load(['creator.media', 'editor.media']);
    }

    public function store(array $validated): MeetingGuest
    {
        $validated['organization_id'] = $this->resolveCurrentOrganizationId();

        return MeetingGuest::create($validated)->load(['creator.media', 'editor.media']);
    }

    public function update(MeetingGuest $guest, array $validated): MeetingGuest
    {
        $guest->update($validated);

        return $guest->load(['creator.media', 'editor.media']);
    }

    public function destroy(MeetingGuest $guest): void
    {
        $guest->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingGuest::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        MeetingGuest::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->update(['status' => $status]);
    }

    public function changeStatus(MeetingGuest $guest, string $status): MeetingGuest
    {
        $guest->update(['status' => $status]);

        return $guest->load(['creator.media', 'editor.media']);
    }

    public function export(array $filters, string $fileName): BinaryFileResponse
    {
        return Excel::download(
            new \App\Modules\Meeting\Exports\MeetingGuestExport($filters),
            $fileName,
        );
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
