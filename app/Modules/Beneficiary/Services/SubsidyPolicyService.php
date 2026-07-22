<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\SubsidyStatusEnum;
use App\Modules\Beneficiary\Exports\SubsidyPolicyExport;
use App\Modules\Beneficiary\Imports\SubsidyPolicyImport;
use App\Modules\Beneficiary\Models\SubsidyGrant;
use App\Modules\Beneficiary\Models\SubsidyPolicy;
use App\Modules\Core\Support\ExportFilename;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubsidyPolicyService
{
    public function stats(array $filters): array
    {
        $base = SubsidyPolicy::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'effective' => (clone $base)->whereNull('effective_to')->orWhere('effective_to', '>=', now())->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return SubsidyPolicy::filter($filters)->paginate($limit);
    }

    public function show(SubsidyPolicy $policy): SubsidyPolicy
    {
        return $policy;
    }

    public function store(array $validated): SubsidyPolicy
    {
        return SubsidyPolicy::create($validated);
    }

    public function update(SubsidyPolicy $policy, array $validated): SubsidyPolicy
    {
        $policy->update($validated);

        return $policy;
    }

    public function destroy(SubsidyPolicy $policy): void
    {
        $policy->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        SubsidyPolicy::whereIn('id', $ids)->delete();
    }

    /**
     * Luồng 5 bước 3: NN ban hành mức trợ cấp mới — đóng policy cũ, tạo policy mới nối tiếp,
     * và với MỌI subsidy_grants đang active thuộc policy cũ: đóng granted_to, tạo grant mới nối tiếp
     * theo policy mới. Ghi nhiều bước có phụ thuộc -> bắt buộc DB::transaction() (CLAUDE.md §4).
     * KHÔNG sửa trực tiếp amount trên grant cũ — luôn đóng + tạo mới để giữ lịch sử đầy đủ.
     */
    public function renew(SubsidyPolicy $oldPolicy, array $newPolicyData): SubsidyPolicy
    {
        return DB::transaction(function () use ($oldPolicy, $newPolicyData) {
            $effectiveFrom = $newPolicyData['effective_from'];

            $oldPolicy->update(['effective_to' => now()->parse($effectiveFrom)->subDay()]);

            $newPolicy = SubsidyPolicy::create([
                ...$newPolicyData,
                'beneficiary_type' => $newPolicyData['beneficiary_type'] ?? $oldPolicy->beneficiary_type,
                'relationship_type' => $newPolicyData['relationship_type'] ?? $oldPolicy->relationship_type,
            ]);

            $activeGrants = SubsidyGrant::where('beneficiary_subsidy_policy_id', $oldPolicy->id)
                ->where('status', SubsidyStatusEnum::Active->value)
                ->get();

            foreach ($activeGrants as $grant) {
                $grant->update(['granted_to' => now()->parse($effectiveFrom)->subDay(), 'status' => SubsidyStatusEnum::Terminated->value, 'termination_reason' => 'Chuyển sang chính sách mới']);

                SubsidyGrant::create([
                    'organization_id' => $grant->organization_id,
                    'subject_type' => $grant->subject_type,
                    'subject_id' => $grant->subject_id,
                    'beneficiary_subsidy_policy_id' => $newPolicy->id,
                    'amount' => $newPolicy->amount,
                    'granted_from' => $effectiveFrom,
                    'status' => SubsidyStatusEnum::Active->value,
                ]);
            }

            return $newPolicy;
        });
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new SubsidyPolicyExport($filters), ExportFilename::make('chinh-sach-tro-cap'));
    }

    public function import($file): \Illuminate\Support\Collection
    {
        $import = new SubsidyPolicyImport;
        Excel::import($import, $file);

        return $import->failures();
    }
}
