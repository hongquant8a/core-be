<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\MeetingMinutesTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * CRUD template biên bản — không scope theo org (global cho module Meeting).
 * is_default: tự enforce 1 default (set true cho row khác -> unset cũ).
 */
class MeetingMinutesTemplateService
{
    public function __construct(private MediaService $mediaService) {}

    public function index(array $filters, int $limit)
    {
        return MeetingMinutesTemplate::with(['mediaFile', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingMinutesTemplate $template): MeetingMinutesTemplate
    {
        return $template->load(['mediaFile', 'creator.media', 'editor.media']);
    }

    public function store(array $validated, ?UploadedFile $file): MeetingMinutesTemplate
    {
        return DB::transaction(function () use ($validated, $file) {
            $template = MeetingMinutesTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_default' => (bool) ($validated['is_default'] ?? false),
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($file) {
                $media = $this->mediaService->uploadOne($template, $file, 'meeting-minutes-template-file');
                $template->update(['media_id' => $media->id]);
            }

            if ($template->is_default) {
                $this->unsetOtherDefaults($template->id);
            }

            return $template->load(['mediaFile']);
        });
    }

    public function update(MeetingMinutesTemplate $template, array $validated, ?UploadedFile $file): MeetingMinutesTemplate
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
                    $this->mediaService->removeByIds($template, [$template->media_id], 'meeting-minutes-template-file');
                }
                $media = $this->mediaService->uploadOne($template, $file, 'meeting-minutes-template-file');
                $template->update(['media_id' => $media->id]);
            }

            if ($template->is_default) {
                $this->unsetOtherDefaults($template->id);
            }

            return $template->load(['mediaFile']);
        });
    }

    public function destroy(MeetingMinutesTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            if ($template->media_id) {
                $this->mediaService->removeByIds($template, [$template->media_id], 'meeting-minutes-template-file');
            }
            $template->delete();
        });
    }

    public function resolveDefault(): MeetingMinutesTemplate
    {
        $template = MeetingMinutesTemplate::where('is_default', true)
            ->where('status', 'active')
            ->first();
        if (! $template) {
            throw new ModelNotFoundException('Chưa có template biên bản mặc định.');
        }

        return $template;
    }

    private function unsetOtherDefaults(int $keepId): void
    {
        MeetingMinutesTemplate::where('id', '!=', $keepId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
