<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Concerns\HasDocumentVisibility;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingView;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class MeetingDocumentService
{
    use HasDocumentVisibility;

    public function __construct(private MediaService $mediaService) {}

    /**
     * "Visible" document index — meeting public hoặc user là participant.
     *   - Guest hoặc auth không-participant: chỉ doc is_public=true của public meeting
     *   - Auth participant của meeting cụ thể (filter meeting_id): thấy mọi doc
     */
    public function publicIndex(array $filters, int $limit)
    {
        $meetingId = $filters['meeting_id'] ?? null;
        $meeting = $meetingId ? Meeting::find($meetingId) : null;
        $isParticipant = $this->shouldSeeAllDocs($meeting);

        return MeetingDocument::with(['agenda', 'documentType', 'mediaFile'])
            ->when(! $isParticipant, fn ($q) => $q->where('is_public', true)
                ->whereHas('meeting', function ($mq) {
                    $mq->where('is_public', true)
                        ->where('status', 'published');
                }))
            ->when($isParticipant && $meetingId, fn ($q) => $q->where('meeting_id', $meetingId))
            ->when(! $isParticipant && $meetingId, fn ($q) => $q->where('meeting_id', $meetingId))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->paginate($limit);
    }

    public function publicShow(MeetingDocument $meetingDocument): MeetingDocument
    {
        $meeting = $meetingDocument->meeting;
        $authUser = $this->ensureAuthResolved($meeting);

        $isParticipant = $this->shouldSeeAllDocs($meeting);
        $hasViewPermission = $authUser?->can('meeting-documents.show') ?? false;

        // Guest hoặc auth-không-có-quyền: yêu cầu doc + meeting đều public + published.
        if (! $isParticipant && ! $hasViewPermission) {
            if (
                ! $meetingDocument->is_public
                || ! $meeting
                || ! $meeting->is_public
                || $meeting->status !== 'published'
            ) {
                throw new ModelNotFoundException('Không tìm thấy tài liệu.');
            }
        }

        return $meetingDocument->load(['agenda', 'documentType', 'mediaFile']);
    }

    /**
     * Public export helper: resolve auth (Bearer/cookie/sanctum) + trả về user có vai trò
     * chair/op/participant trong meeting hay không. Guest → false → caller filter chỉ doc
     * is_public=true.
     */
    public function resolveIsPrivileged(?\App\Modules\Meeting\Models\Meeting $meeting): bool
    {
        $this->ensureAuthResolved($meeting);

        return $this->shouldSeeAllDocs($meeting);
    }

    /**
     * Resolve auth user TRƯỚC khi check role. shouldSeeAllDocs() chỉ đọc guard web/sanctum,
     * không tự đọc cookie. FE SPA dùng cookie `accessToken`. Side-effect: setUser vào guard
     * sanctum + setPermissionsTeamId nếu resolve được.
     *  - Ưu tiên Authorization Bearer header (cách chuẩn)
     *  - Fallback: cookie `accessToken` (FE SPA của project dùng cookie thay vì header)
     */
    private function ensureAuthResolved(?\App\Modules\Meeting\Models\Meeting $meeting): ?\App\Modules\Core\Models\User
    {
        $authUser = auth()->user() ?? \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        if (! $authUser) {
            $authUser = $this->resolveUserFromCookieToken();
            if ($authUser) {
                \Illuminate\Support\Facades\Auth::guard('sanctum')->setUser($authUser);
            }
        }
        if ($authUser && function_exists('setPermissionsTeamId') && $meeting) {
            setPermissionsTeamId((int) $meeting->organization_id);
        }

        return $authUser;
    }

    /**
     * FE SPA dùng cookie `accessToken` (format Sanctum: `id|plain_token`) thay vì Bearer header.
     * Resolve thủ công thành User instance.
     */
    private function resolveUserFromCookieToken(): ?\App\Modules\Core\Models\User
    {
        $request = request();
        if (! $request) {
            return null;
        }
        $token = $request->cookie('accessToken');
        if (! $token) {
            return null;
        }
        // Format Sanctum: `id|plain_text` — split, hash plain_text rồi match.
        if (! str_contains($token, '|')) {
            return null;
        }
        [$id, $plain] = explode('|', $token, 2);
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::find($id);
        if (! $accessToken) {
            return null;
        }
        if (! hash_equals($accessToken->token, hash('sha256', $plain))) {
            return null;
        }

        return $accessToken->tokenable instanceof \App\Modules\Core\Models\User
            ? $accessToken->tokenable
            : null;
    }

    /**
     * Track + resolve URL truy cập tệp đính kèm (chung cho cả public và authenticated).
     *
     * `type`:
     *  - 'download': increment download_count, log kind=document_download
     *  - 'view'    : increment view_count,    log kind=document_view
     *
     * @return array{url: string, file_name: ?string, type: string}
     */
    public function trackAccess(MeetingDocument $meetingDocument, string $type = 'download'): array
    {
        $type = in_array($type, ['download', 'view'], true) ? $type : 'download';

        $meetingDocument->loadMissing('mediaFile');
        if (! $meetingDocument->mediaFile) {
            throw new ModelNotFoundException('Tài liệu không có tệp đính kèm.');
        }

        $request = request();
        $counterColumn = $type === 'view' ? 'view_count' : 'download_count';
        $logKind = $type === 'view' ? 'document_view' : 'document_download';

        // Public route không có middleware auth:sanctum nên auth()->id() (guard web) null.
        // publicShow trước đó đã setUser vào sanctum guard (qua ensureAuthResolved). Đọc
        // sanctum làm fallback để log đúng user_id thay vì coi user đã login là "Khách".
        $userId = auth()->id() ?? \Illuminate\Support\Facades\Auth::guard('sanctum')->id();

        DB::transaction(function () use ($meetingDocument, $request, $counterColumn, $logKind, $userId) {
            $meetingDocument->increment($counterColumn);
            MeetingView::create([
                'organization_id' => $meetingDocument->organization_id,
                'meeting_id' => $meetingDocument->meeting_id,
                'meeting_document_id' => $meetingDocument->id,
                'user_id' => $userId,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'viewed_at' => now(),
                'kind' => $logKind,
            ]);
        });

        $media = $meetingDocument->mediaFile;

        return [
            'url' => '/storage/'.$media->id.'/'.$media->file_name,
            'file_name' => $media->file_name,
            'type' => $type,
        ];
    }

    /**
     * Auth document index — caller có permission meeting-documents.index. Trả full
     * docs (kể cả is_public=false) vì đây là endpoint CRUD admin. Filter is_public
     * chỉ áp dụng cho publicIndex (citizen/non-participant).
     */
    public function index(array $filters, int $limit)
    {
        return MeetingDocument::with(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(MeetingDocument $meetingDocument): MeetingDocument
    {
        return $meetingDocument->load(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media']);
    }

    public function store(array $validated, $file = null): MeetingDocument
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($validated, $file, &$storedFiles) {
                if (! array_key_exists('sort_order', $validated)) {
                    $validated['sort_order'] = $this->nextSortOrder($validated['meeting_id']);
                }
                $validated['organization_id'] = $this->resolveCurrentOrganizationId();
                $document = MeetingDocument::create($validated);

                if ($file) {
                    $media = $this->mediaService->uploadOne($document, $file, 'meeting-document-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $document->update(['media_id' => $media->id]);
                }

                return $document->load(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function update(MeetingDocument $meetingDocument, array $validated, $file = null): MeetingDocument
    {
        $storedFiles = [];
        try {
            return DB::transaction(function () use ($meetingDocument, $validated, $file, &$storedFiles) {
                $removeFile = (bool) ($validated['remove_file'] ?? false);
                unset($validated['remove_file']);
                $meetingDocument->update($validated);

                if ($removeFile && $meetingDocument->media_id) {
                    $this->mediaService->removeByIds($meetingDocument, [$meetingDocument->media_id], 'meeting-document-attachments');
                    $meetingDocument->update(['media_id' => null]);
                }

                if ($file) {
                    if ($meetingDocument->media_id) {
                        $this->mediaService->removeByIds($meetingDocument, [$meetingDocument->media_id], 'meeting-document-attachments');
                    }
                    $media = $this->mediaService->uploadOne($meetingDocument, $file, 'meeting-document-attachments', ['disk' => 'public']);
                    $storedFiles[] = ['disk' => $media->disk, 'path' => $media->getPathRelativeToRoot()];
                    $meetingDocument->update(['media_id' => $media->id]);
                }

                return $meetingDocument->load(['agenda', 'documentType', 'mediaFile', 'creator.media', 'editor.media']);
            });
        } catch (\Throwable $exception) {
            $this->mediaService->cleanupStoredFiles($storedFiles);
            throw $exception;
        }
    }

    public function destroy(MeetingDocument $meetingDocument): void
    {
        $meetingDocument->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        MeetingDocument::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->whereIn('id', $ids)
            ->delete();
    }

    public function reorder(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                MeetingDocument::query()
                    ->where('organization_id', $this->resolveCurrentOrganizationId())
                    ->whereKey($item['id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });
    }

    private function nextSortOrder(int $meetingId): int
    {
        return ((int) MeetingDocument::query()
            ->where('organization_id', $this->resolveCurrentOrganizationId())
            ->where('meeting_id', $meetingId)
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
