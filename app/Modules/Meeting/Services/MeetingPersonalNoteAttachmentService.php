<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingPersonalNote;
use App\Modules\Meeting\Models\MeetingPersonalNoteAttachment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingPersonalNoteAttachmentService
{
    public function __construct(private MediaService $mediaService) {}

    public function index(array $filters, int $limit)
    {
        return MeetingPersonalNoteAttachment::with(['note', 'mediaFile'])
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->when($filters['meeting_personal_note_id'] ?? null, fn ($q, $noteId) => $q->where('meeting_personal_note_id', $noteId))
            ->orderBy('sort_order')
            ->paginate($limit);
    }

    public function store(array $validated, $file): MeetingPersonalNoteAttachment
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $file, &$storedFiles) {
                $note = MeetingPersonalNote::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->findOrFail($validated['meeting_personal_note_id']);

                $media = $this->mediaService->uploadOne($note, $file, 'meeting-note-attachments', ['disk' => 'public']);
                $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];

                $attachment = MeetingPersonalNoteAttachment::create([
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                    'meeting_personal_note_id' => $note->id,
                    'media_id' => $media->id,
                    'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($note->id),
                ]);

                return $attachment->load(['note', 'mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(MeetingPersonalNoteAttachment $meetingPersonalNoteAttachment): void
    {
        $meetingPersonalNoteAttachment->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingPersonalNoteAttachment::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function nextSortOrder(int $noteId): int
    {
        return ((int) MeetingPersonalNoteAttachment::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_personal_note_id', $noteId)
            ->max('sort_order')) + 1;
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
