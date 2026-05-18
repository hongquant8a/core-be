<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingDiscussionRegistrationAttachment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Multi-attachment cho thảo luận / chất vấn. Pattern clone từ
 * MeetingPersonalNoteAttachmentService — owner-scope đã handle ở Policy.
 */
class MeetingDiscussionRegistrationAttachmentService
{
    public function __construct(private MediaService $mediaService) {}

    public function store(array $validated, $file): MeetingDiscussionRegistrationAttachment
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($validated, $file, &$storedFiles) {
                $registration = MeetingDiscussionRegistration::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->findOrFail($validated['meeting_discussion_registration_id']);

                $media = $this->mediaService->uploadOne(
                    $registration,
                    $file,
                    'meeting-discussion-attachments',
                    ['disk' => 'public']
                );
                $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];

                // file_name = user input nếu có, fallback original filename media upload.
                $fileName = $validated['file_name'] ?? $media->file_name;

                $attachment = MeetingDiscussionRegistrationAttachment::create([
                    'organization_id' => $this->resolveCurrentOrganizationId(),
                    'meeting_discussion_registration_id' => $registration->id,
                    'media_id' => $media->id,
                    'file_name' => $fileName,
                    'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($registration->id),
                ]);

                return $attachment->load(['registration', 'mediaFile']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(MeetingDiscussionRegistrationAttachment $attachment): void
    {
        $attachment->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingDiscussionRegistrationAttachment::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function nextSortOrder(int $registrationId): int
    {
        return ((int) MeetingDiscussionRegistrationAttachment::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_discussion_registration_id', $registrationId)
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
