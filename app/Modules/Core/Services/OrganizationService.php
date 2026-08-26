<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Exports\OrganizationsExport;
use App\Modules\Core\Imports\OrganizationsImport;
use App\Modules\Core\Models\Organization;
use App\Modules\Core\Support\ExportFilename;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OrganizationService
{
    public function publicList(array $filters = []): Collection
    {
        $publicFilters = [
            ...$filters,
            'status' => StatusEnum::Active->value,
            'sort_by' => 'sort_order',
            'sort_order' => 'asc',
        ];

        return $this->getFlatTreeOrdered($publicFilters);
    }

    public function publicOptions(array $filters = []): Collection
    {
        $publicFilters = [
            ...$filters,
            'status' => StatusEnum::Active->value,
            'sort_by' => 'sort_order',
            'sort_order' => 'asc',
        ];

        return Organization::query()
            ->select(['id', 'name', 'description'])
            ->filter($publicFilters)
            ->treeOrder()
            ->get();
    }

    public function stats(array $filters): array
    {
        $base = Organization::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', StatusEnum::Active->value)->count(),
            'inactive' => (clone $base)->where('status', '!=', StatusEnum::Active->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Organization::with(['creator.media', 'editor.media', 'parent'])
            ->filter($filters)
            ->treeOrder()
            ->paginate($limit);
    }

    public function tree(?string $status)
    {
        $query = Organization::query()
            ->with('editor')
            ->when($status, fn ($q, $value) => $q->where('status', $value));
        $items = $query->orderBy('sort_order')->orderBy('id')->get();

        // Calculate user counts per organization.
        // Join users để loại bỏ user đã bị xoá (mass delete qua bulkDestroy để lại
        // orphan rows trong model_has_roles vì không fire model event của Spatie).
        $userCounts = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->select('model_has_roles.organization_id', \Illuminate\Support\Facades\DB::raw('count(distinct model_has_roles.model_id) as count'))
            ->where('model_has_roles.model_type', (new \App\Modules\Core\Models\User)->getMorphClass())
            ->groupBy('model_has_roles.organization_id')
            ->pluck('count', 'organization_id');

        // Append user_count to each item
        $items->each(function ($item) use ($userCounts) {
            $item->user_count = $userCounts->get($item->id) ?? 0;
        });

        return $this->buildTree($items);
    }

    public function show(Organization $organization): Organization
    {
        return $organization->load(['creator.media', 'editor.media', 'parent', 'children' => fn ($q) => $q->orderBy('sort_order')]);
    }

    public function store(array $data): Organization
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $org = Organization::create($data);

            // 1. NotificationEventConfig — mỗi event 1 row cho org mới
            foreach (\App\Services\Notification\Enums\NotificationEventEnum::cases() as $event) {
                \App\Modules\Core\Models\NotificationEventConfig::firstOrCreate(
                    [
                        'module_key' => $event->module()->value,
                        'event_key' => $event->value,
                        'organization_id' => $org->id,
                    ],
                    ['enabled' => false]
                );
            }

            // 2. NotificationSchedule — schedule mặc định cho từng event config
            $this->seedNotificationSchedules($org->id);

            return $org;
        });
    }

    private function seedNotificationSchedules(int $organizationId): void
    {
        $configs = \App\Modules\Core\Models\NotificationEventConfig::where('organization_id', $organizationId)->get()->keyBy('event_key');

        // ── TaskAssignment ──
        $moduleKey = \App\Services\Notification\Enums\NotificationModuleEnum::TaskAssignment->value;
        $isTaskAssignment = fn ($c) => $c && $c->module_key === $moduleKey;

        // Non-reminder instant
        foreach (['document_issued', 'task_assigned', 'task_completed', 'task_confirmed', 'report_submitted', 'task_rejected'] as $ek) {
            $c = $configs->get($ek);
            if (! $c) {
                continue;
            }
            $label = match ($ek) {
                'document_issued' => 'Thông báo ngay khi ban hành',
                'task_assigned' => 'Thông báo ngay khi giao việc',
                'task_completed' => 'Thông báo ngay khi hoàn thành',
                'task_confirmed' => 'Thông báo ngay khi xác nhận',
                'report_submitted' => 'Thông báo ngay khi có báo cáo mới',
                'task_rejected' => 'Thông báo ngay khi bị trả lại',
                default => 'Gửi ngay lập tức',
            };
            $s = \App\Modules\Core\Models\NotificationSchedule::firstOrNew([
                'notification_event_config_id' => $c->id, 'moment' => null, 'offset_minutes' => null,
            ]);
            $s->label = $label;
            $s->sort_order = 0;
            $s->save();
        }

        // Reminder
        $reminders = [
            'reminder_before' => [['before', 1440, 'Nhắc trước 1 ngày', 1], ['before', 120, 'Nhắc trước 2 giờ', 2]],
            'reminder_on' => [['on', null, 'Đến hạn', 1]],
            'reminder_after' => [['after', 1440, 'Trễ 1 ngày', 1]],
        ];
        foreach ($reminders as $ek => $schedules) {
            $c = $configs->get($ek);
            if (! $c) {
                continue;
            }
            foreach ($schedules as [$moment, $offset, $label, $sort]) {
                \App\Modules\Core\Models\NotificationSchedule::firstOrCreate(
                    ['notification_event_config_id' => $c->id, 'moment' => $moment, 'offset_minutes' => $offset],
                    ['channels' => [], 'label' => $label, 'sort_order' => $sort],
                );
            }
        }

        // ── Meeting ──
        $moduleKey = \App\Services\Notification\Enums\NotificationModuleEnum::Meeting->value;

        // Instant
        foreach (['meeting_published' => 'Gửi giấy mời ngay', 'meeting_updated' => 'Thông báo ngay khi cập nhật', 'meeting_cancelled' => 'Thông báo ngay khi hủy'] as $ek => $label) {
            $c = $configs->get($ek);
            if (! $c) {
                continue;
            }
            $s = \App\Modules\Core\Models\NotificationSchedule::firstOrNew([
                'notification_event_config_id' => $c->id, 'moment' => null, 'offset_minutes' => null,
            ]);
            $s->label = $label;
            $s->sort_order = 0;
            $s->save();
        }

        // Reminder
        $reminders = [
            'meeting_reminder_before' => [['before', 1440, 'Nhắc trước 1 ngày', 1], ['before', 30, 'Nhắc trước 30 phút', 2]],
            'meeting_reminder_on' => [['on', null, 'Đến giờ họp', 1]],
            'meeting_reminder_after' => [['after', 5, 'Sau 5 phút (nhắc kiểm tra biên bản)', 1]],
        ];
        foreach ($reminders as $ek => $schedules) {
            $c = $configs->get($ek);
            if (! $c) {
                continue;
            }
            foreach ($schedules as [$moment, $offset, $label, $sort]) {
                \App\Modules\Core\Models\NotificationSchedule::firstOrCreate(
                    ['notification_event_config_id' => $c->id, 'moment' => $moment, 'offset_minutes' => $offset],
                    ['channels' => [], 'label' => $label, 'sort_order' => $sort],
                );
            }
        }

    }

    public function update(Organization $organization, array $data): array
    {
        if (isset($data['parent_id']) && (int) $data['parent_id'] !== 0) {
            if ($this->isDescendantOf((int) $data['parent_id'], $organization->id)) {
                return [
                    'ok' => false,
                    'message' => 'Không thể chọn organization con làm organization cha.',
                    'code' => 422,
                    'error_code' => 'CONFLICT',
                ];
            }
        }

        if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] === 0) {
            $data['parent_id'] = null;
        }

        $organization->update($data);

        return [
            'ok' => true,
            'organization' => $organization->fresh(['parent', 'children']),
        ];
    }

    public function destroy(Organization $organization): void
    {
        $organization->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Organization::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Organization::whereIn('id', $ids)->update(['status' => $status]);
    }

    public function changeStatus(Organization $organization, string $status): Organization
    {
        $organization->update(['status' => $status]);

        return $organization->load(['parent', 'children']);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new OrganizationsExport($filters), ExportFilename::make('to-chuc'));
    }

    public function import($file): void
    {
        Excel::import(new OrganizationsImport, $file);
    }

    public function getFlatTreeOrdered(array $filters = []): Collection
    {
        $all = Organization::with(['creator.media', 'editor.media'])->filter($filters)->get();
        $tree = $this->buildTree($all);
        $result = collect();
        $flatten = function ($nodes) use (&$flatten, &$result) {
            foreach ($nodes as $node) {
                $result->push($node);
                $flatten($node->children);
            }
        };
        $flatten($tree);

        return $result;
    }

    public function getDepth(Organization $organization): int
    {
        $depth = 0;
        $parentId = $organization->parent_id;
        $ids = [$organization->id];

        while ($parentId) {
            if (in_array($parentId, $ids)) {
                break;
            }

            $ids[] = $parentId;
            $parent = Organization::find($parentId);
            $parentId = $parent ? $parent->parent_id : null;
            $depth++;
        }

        return $depth;
    }

    public function generateUniqueSlug(string $base, ?int $excludeId = null): string
    {
        $slug = $base;
        $query = Organization::where('slug', $slug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        $index = 0;
        while ($query->exists()) {
            $slug = $base.'-'.(++$index);
            $query = Organization::where('slug', $slug);
            if ($excludeId !== null) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }

    public function buildTree(Collection $items): Collection
    {
        $grouped = $items->groupBy('parent_id');
        $builder = function ($parentId) use ($grouped, &$builder) {
            return ($grouped->get($parentId) ?? collect())
                ->map(function ($node) use (&$builder) {
                    $node->setRelation('children', $builder($node->id));

                    return $node;
                })
                ->values();
        };

        return $builder(null);
    }

    private function isDescendantOf(int $candidateId, int $id): bool
    {
        if ($candidateId === $id) {
            return true;
        }

        $current = Organization::find($candidateId);

        while ($current && $current->parent_id) {
            if ($current->parent_id === $id) {
                return true;
            }

            $current = Organization::find($current->parent_id);
        }

        return false;
    }
}
