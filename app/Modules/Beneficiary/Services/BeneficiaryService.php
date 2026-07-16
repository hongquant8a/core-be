<?php

namespace App\Modules\Beneficiary\Services;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Exports\BeneficiaryExport;
use App\Modules\Beneficiary\Imports\BeneficiaryImport;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\StatusHistory;
use App\Modules\Core\Support\ExportFilename;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BeneficiaryService
{
    private const WITH = ['household', 'classifications', 'creator.media', 'editor.media'];

    public function stats(array $filters): array
    {
        $base = Beneficiary::filter($filters);

        return [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', BeneficiaryStatusEnum::Pending->value)->count(),
            'active' => (clone $base)->where('status', BeneficiaryStatusEnum::Active->value)->count(),
            'deceased' => (clone $base)->where('status', BeneficiaryStatusEnum::Deceased->value)->count(),
        ];
    }

    public function index(array $filters, int $limit)
    {
        return Beneficiary::with(['household', 'creator.media', 'editor.media'])
            ->withCount(['dependents', 'activeSubsidyGrants'])
            ->filter($filters)
            ->paginate($limit);
    }

    public function show(Beneficiary $beneficiary): Beneficiary
    {
        return $beneficiary->load(self::WITH)->loadCount(['dependents', 'activeSubsidyGrants']);
    }

    public function store(array $validated): Beneficiary
    {
        return DB::transaction(function () use ($validated) {
            $classifications = $validated['classifications'] ?? [];
            unset($validated['classifications']);

            $validated['status'] ??= BeneficiaryStatusEnum::Pending->value;

            $beneficiary = Beneficiary::create($validated);

            foreach ($classifications as $classification) {
                $beneficiary->classifications()->create($classification);
            }

            return $beneficiary->fresh(self::WITH);
        });
    }

    public function update(Beneficiary $beneficiary, array $validated): Beneficiary
    {
        $beneficiary->update($validated);

        return $beneficiary->load(self::WITH);
    }

    public function destroy(Beneficiary $beneficiary): void
    {
        $beneficiary->delete();
    }

    public function bulkDestroy(array $ids): void
    {
        Beneficiary::whereIn('id', $ids)->delete();
    }

    public function bulkUpdateStatus(array $ids, string $status): void
    {
        Beneficiary::whereIn('id', $ids)->update(['status' => $status]);
    }

    /**
     * Beneficiary chỉ có 1 đường ghi status (qua Service này) — ghi thẳng beneficiary_status_histories
     * ở đây, KHÔNG cần Event/Listener riêng (khác DependentEligibilityExpired ở Observer, vì đó có
     * 2 đường ghi). Khi cần gửi Zalo cho hành động này, tách event() ra — logic ghi lịch sử giữ nguyên.
     */
    public function changeStatus(Beneficiary $beneficiary, string $status, ?string $reason = null, ?string $deathDate = null): Beneficiary
    {
        return DB::transaction(function () use ($beneficiary, $status, $reason, $deathDate) {
            $oldStatus = $beneficiary->status;

            $update = ['status' => $status];
            if ($status === BeneficiaryStatusEnum::Deceased->value && $deathDate) {
                $update['death_date'] = $deathDate;
            }

            $beneficiary->update($update);

            StatusHistory::create([
                'organization_id' => $beneficiary->organization_id,
                'subject_type' => Beneficiary::class,
                'subject_id' => $beneficiary->id,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $reason,
                'changed_by' => auth()->id(),
                'changed_at' => now(),
            ]);

            if ($status === BeneficiaryStatusEnum::Deceased->value || $status === BeneficiaryStatusEnum::MovedOut->value) {
                $beneficiary->activeSubsidyGrants()->update([
                    'status' => 'terminated',
                    'termination_reason' => $status === BeneficiaryStatusEnum::Deceased->value
                        ? 'Người có công đã mất'
                        : 'Người có công đã chuyển đi',
                    'granted_to' => now(),
                ]);
            }

            return $beneficiary->load(self::WITH);
        });
    }

    public function statusHistories(Beneficiary $beneficiary, array $filters, int $limit)
    {
        return $beneficiary->statusHistories()
            ->with('changer')
            ->filter($filters)
            ->paginate($limit);
    }

    public function export(array $filters): BinaryFileResponse
    {
        return Excel::download(new BeneficiaryExport($filters), ExportFilename::make('nguoi-co-cong'));
    }

    public function import($file): void
    {
        Excel::import(new BeneficiaryImport, $file);
    }
}
