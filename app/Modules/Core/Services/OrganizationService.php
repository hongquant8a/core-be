<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Enums\StatusEnum;
use App\Modules\Core\Exports\OrganizationsExport;
use App\Modules\Core\Imports\OrganizationsImport;
use App\Modules\Core\Models\Organization;
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
            ->when($status, fn ($q, $value) => $q->where('status', $value));
        $items = $query->orderBy('sort_order')->orderBy('id')->get();

        return $this->buildTree($items);
    }

    public function show(Organization $organization): Organization
    {
        return $organization->load(['creator.media', 'editor.media', 'parent', 'children' => fn ($q) => $q->orderBy('sort_order')]);
    }

    public function store(array $data): Organization
    {
        return Organization::create($data);
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
        return Excel::download(new OrganizationsExport($filters), 'organizations.xlsx');
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
