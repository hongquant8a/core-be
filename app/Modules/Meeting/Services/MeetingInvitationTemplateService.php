<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingInvitationTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * CRUD template giấy mời — scope theo org.
 * is_default: tự enforce 1 default (set true cho row khác -> unset cũ).
 */
class MeetingInvitationTemplateService
{
    public function __construct(private MediaService $mediaService) {}

    public function index(array $filters, int $limit)
    {
        return MeetingInvitationTemplate::with(['mediaFile', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingInvitationTemplate $template): MeetingInvitationTemplate
    {
        return $template->load(['mediaFile', 'creator.media', 'editor.media']);
    }

    public function store(array $validated, ?UploadedFile $file): MeetingInvitationTemplate
    {
        return DB::transaction(function () use ($validated, $file) {
            $template = MeetingInvitationTemplate::create([
                'organization_id' => $this->resolveCurrentOrganizationId(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($file) {
                $media = $this->mediaService->uploadOne($template, $file, 'meeting-invitation-template-file');
                $template->update(['media_id' => $media->id]);
            }

            if ($template->is_default) {
                $this->unsetOtherDefaults($template->id, (int) $template->organization_id);
            }

            return $template->load(['mediaFile']);
        });
    }

    public function update(MeetingInvitationTemplate $template, array $validated, ?UploadedFile $file): MeetingInvitationTemplate
    {
        return DB::transaction(function () use ($template, $validated, $file) {
            $template->update([
                'name' => $validated['name'] ?? $template->name,
                'description' => $validated['description'] ?? $template->description,
                'is_default' => array_key_exists('is_default', $validated) ? (bool) $validated['is_default'] : $template->is_default,
                'status' => $validated['status'] ?? $template->status,
            ]);

            if ($file) {
                // Xóa file cũ (nếu có) trước khi upload mới — tránh leak media orphan.
                if ($template->media_id && $template->mediaFile) {
                    $this->mediaService->removeByIds($template, [$template->media_id], 'meeting-invitation-template-file');
                }
                $media = $this->mediaService->uploadOne($template, $file, 'meeting-invitation-template-file');
                $template->update(['media_id' => $media->id]);
            }

            if ($template->is_default) {
                $this->unsetOtherDefaults($template->id, (int) $template->organization_id);
            }

            return $template->load(['mediaFile']);
        });
    }

    public function destroy(MeetingInvitationTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            if ($template->media_id) {
                $this->mediaService->removeByIds($template, [$template->media_id], 'meeting-invitation-template-file');
            }
            $template->delete();
        });
    }

    public function resolveDefault(): MeetingInvitationTemplate
    {
        $organizationId = $this->resolveCurrentOrganizationId();
        $template = MeetingInvitationTemplate::where('organization_id', $organizationId)
            ->where('is_default', true)
            ->where('status', 'active')
            ->first();
        if (! $template) {
            throw new ModelNotFoundException('Chưa có template giấy mời mặc định.');
        }

        return $template;
    }

    private function unsetOtherDefaults(int $keepId, int $organizationId): void
    {
        MeetingInvitationTemplate::where('organization_id', $organizationId)
            ->where('id', '!=', $keepId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
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
