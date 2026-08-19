<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Organization;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Enums\TaskProgressStatusEnum;
use App\Modules\TaskAssignment\Enums\TaskUserAssignmentStatusEnum;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemNote;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use App\Modules\TaskAssignment\Models\TaskAssignmentUser;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed dữ liệu mẫu đầy đủ cho phân hệ Quản lý công việc (TaskAssignment).
 *
 * - 6 đơn vị: Văn phòng Đảng uỷ, Ban Xây dựng Đảng, Uỷ ban Kiểm tra,
 *   Đảng uỷ UBND, Đảng uỷ Công an, Chi uỷ Quân sự.
 * - Mỗi đơn vị có 1 tài khoản đại diện (xem PermissionSeeder::PARTY_UNIT_ACCOUNTS),
 *   các user còn lại được chia đều làm thành viên đơn vị.
 * - 3 loại văn bản, 5 loại đầu việc.
 * - 15 văn bản giao việc × 5 đầu việc = 75 đầu việc, trải từ 03/2026 đến 08/2026.
 * - Mỗi đầu việc đã triển khai đều có báo cáo của đơn vị (báo cáo tiến độ + báo cáo kết quả),
 *   kèm trao đổi (note) giữa lãnh đạo và đơn vị thực hiện.
 * - created_at/updated_at/created_by/updated_by bám sát timeline thực tế.
 */
class TaskAssignmentDataSeeder extends Seeder
{
    private const ORG_ID = 1;

    /** Mã đơn vị (key nội bộ trong seeder) → tên đơn vị hiển thị. */
    private const UNITS = [
        'VPDU'   => ['name' => 'Văn phòng Đảng uỷ',  'sort_order' => 1, 'description' => 'Tham mưu tổng hợp, phục vụ hoạt động của Đảng uỷ và Ban Thường vụ Đảng uỷ.'],
        'BXDD'   => ['name' => 'Ban Xây dựng Đảng',  'sort_order' => 2, 'description' => 'Tham mưu công tác tổ chức, cán bộ, đảng viên và công tác tuyên giáo, dân vận.'],
        'UBKT'   => ['name' => 'Uỷ ban Kiểm tra',    'sort_order' => 3, 'description' => 'Tham mưu và thực hiện công tác kiểm tra, giám sát, kỷ luật của Đảng.'],
        'DUUBND' => ['name' => 'Đảng uỷ UBND',       'sort_order' => 4, 'description' => 'Lãnh đạo thực hiện nhiệm vụ chính trị của khối chính quyền.'],
        'DUCA'   => ['name' => 'Đảng uỷ Công an',    'sort_order' => 5, 'description' => 'Lãnh đạo công tác bảo đảm an ninh chính trị, trật tự an toàn xã hội.'],
        'CUQS'   => ['name' => 'Chi uỷ Quân sự',     'sort_order' => 6, 'description' => 'Lãnh đạo công tác quân sự, quốc phòng địa phương.'],
    ];

    /** user_name tài khoản đại diện của từng đơn vị. */
    private const UNIT_ACCOUNTS = [
        'VPDU'   => 'vpdu',
        'BXDD'   => 'bxdd',
        'UBKT'   => 'ubkt',
        'DUUBND' => 'duubnd',
        'DUCA'   => 'duca',
        'CUQS'   => 'cuqs',
    ];

    /** Mã đơn vị → id bảng task_assignment_departments. */
    private array $deptIds = [];

    /** Mã đơn vị → User đại diện (người nhận việc và báo cáo). */
    private array $representatives = [];

    /** Mã đơn vị → Collection User là thành viên đơn vị. */
    private array $membersByUnit = [];

    private array $typeIds = [];

    private array $itemTypeIds = [];

    private array $authorizedUserIds = [];

    private array $categoryCreatorUserIds = [];

    public function run(): void
    {
        // Đăng nhập tạm để model booted() không ghi đè created_by/updated_by = null
        auth()->setUser(User::first());

        // Đặt organization_id = 1 cho Spatie Permission và Global Scope của model
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(self::ORG_ID);
        }

        $this->seedDepartments();
        $this->seedTypes();
        $this->seedItemTypes();
        $this->assignUsersToDepartments();
        $this->seedDocumentsAndItems();

        auth()->forgetUser();
    }

    // ─── Đơn vị ─────────────────────────────────────────────────

    protected function seedDepartments(): void
    {
        $now = Carbon::parse('2026-01-15 08:30:00');
        $creatorId = $this->getCategoryCreatorId();

        foreach (self::UNITS as $code => $unit) {
            $ts = $now->copy()->addMinutes(($unit['sort_order'] - 1) * 5);
            $record = TaskAssignmentDepartment::unguarded(fn () => TaskAssignmentDepartment::withoutGlobalScopes()->firstOrCreate(
                ['name' => $unit['name']],
                [
                    'description' => $unit['description'],
                    'status' => 'active',
                    'sort_order' => $unit['sort_order'],
                    // Văn phòng Đảng uỷ là đầu mối tổng hợp đơn thư.
                    'is_petition_overview' => $code === 'VPDU',
                    'organization_id' => self::ORG_ID,
                ]
            ));

            DB::table('task_assignment_departments')->where('id', $record->id)->update([
                'organization_id' => self::ORG_ID,
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $ts, 'updated_at' => $ts,
            ]);

            $this->deptIds[$code] = $record->id;
        }
    }

    // ─── Loại văn bản ────────────────────────────────────────────

    protected function seedTypes(): void
    {
        $ts = Carbon::parse('2026-01-15 09:00:00');
        $creatorId = $this->getCategoryCreatorId();

        foreach (['Thường trực Đảng uỷ giao', 'Công việc chuyên môn', 'Công việc phát sinh'] as $i => $name) {
            $t = $ts->copy()->addMinutes($i * 3);
            $record = TaskAssignmentType::unguarded(fn () => TaskAssignmentType::withoutGlobalScopes()->firstOrCreate(
                ['name' => $name],
                ['status' => 'active', 'organization_id' => self::ORG_ID]
            ));
            DB::table('task_assignment_types')->where('id', $record->id)->update([
                'organization_id' => self::ORG_ID,
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $t, 'updated_at' => $t,
            ]);
        }
    }

    // ─── Loại đầu việc ──────────────────────────────────────────

    protected function seedItemTypes(): void
    {
        $ts = Carbon::parse('2026-01-15 09:15:00');
        $creatorId = $this->getCategoryCreatorId();
        $types = ['Soạn thảo văn bản', 'Tổ chức sự kiện', 'Báo cáo định kỳ', 'Kiểm tra, giám sát', 'Nghiên cứu, khảo sát'];

        foreach ($types as $i => $name) {
            $t = $ts->copy()->addMinutes($i * 2);
            $record = TaskAssignmentItemType::unguarded(fn () => TaskAssignmentItemType::withoutGlobalScopes()->firstOrCreate(
                ['name' => $name],
                ['status' => 'active', 'organization_id' => self::ORG_ID]
            ));
            DB::table('task_assignment_item_types')->where('id', $record->id)->update([
                'organization_id' => self::ORG_ID,
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $t, 'updated_at' => $t,
            ]);
        }
    }

    // ─── Gán user vào đơn vị ────────────────────────────────────

    protected function assignUsersToDepartments(): void
    {
        $orgId = getPermissionsTeamId() ?: (Organization::first()?->id ?? self::ORG_ID);
        $unitCodes = array_keys(self::UNITS);

        // 1. Tài khoản đại diện đơn vị: is_primary + is_representative.
        foreach (self::UNIT_ACCOUNTS as $code => $userName) {
            $user = User::where('user_name', $userName)->first();
            if (! $user) {
                continue;
            }

            $this->representatives[$code] = $user;
            $this->upsertDepartmentMember($user, $this->deptIds[$code], $orgId, true, true);
        }

        // 2. Các user còn lại chia đều làm thành viên 6 đơn vị.
        $representativeIds = collect($this->representatives)->pluck('id')->all();
        $others = User::whereNotIn('id', $representativeIds)->orderBy('id')->get();

        foreach ($others as $i => $user) {
            $hasAssignment = TaskAssignmentUser::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('organization_id', $orgId)
                ->exists();
            if ($hasAssignment) {
                continue;
            }

            $code = $unitCodes[$i % count($unitCodes)];
            $this->upsertDepartmentMember($user, $this->deptIds[$code], $orgId, false, false);
        }

        // 3. Cache thành viên theo đơn vị (đại diện đứng đầu danh sách).
        $membership = TaskAssignmentUser::withoutGlobalScopes()
            ->with('user')
            ->where('organization_id', $orgId)
            ->orderByDesc('is_representative')
            ->orderBy('user_id')
            ->get()
            ->groupBy('task_assignment_department_id');

        foreach ($this->deptIds as $code => $deptId) {
            $this->membersByUnit[$code] = ($membership[$deptId] ?? collect())->pluck('user')->filter()->values();
        }

        $this->typeIds = TaskAssignmentType::withoutGlobalScopes()->pluck('id', 'name')->all();
        $this->itemTypeIds = TaskAssignmentItemType::withoutGlobalScopes()->pluck('id', 'name')->all();
    }

    private function upsertDepartmentMember(User $user, int $deptId, int $orgId, bool $isPrimary, bool $isRepresentative): void
    {
        TaskAssignmentUser::withoutGlobalScopes()->firstOrCreate(
            [
                'user_id' => $user->id,
                'task_assignment_department_id' => $deptId,
                'organization_id' => $orgId,
            ],
            [
                'is_primary' => $isPrimary,
                'is_representative' => $isRepresentative,
                'status' => 'active',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]
        );
    }

    // ─── Văn bản giao việc & đầu việc ───────────────────────────

    protected function seedDocumentsAndItems(): void
    {
        foreach ($this->getDocumentData() as $docIndex => $docData) {
            $createdAt = Carbon::parse($docData['issue_date'])->subDays(2)->setTime(8, 15 + $docIndex);
            $updatedAt = $docData['issued_at'] ? Carbon::parse($docData['issued_at']) : $createdAt->copy()->addHours(3);
            $creatorId = $this->getAuthorizedUserId();

            $doc = TaskAssignmentDocument::unguarded(fn () => TaskAssignmentDocument::withoutGlobalScopes()->firstOrCreate(
                ['name' => $docData['name']],
                [
                    'summary' => $docData['summary'],
                    'issue_date' => $docData['issue_date'],
                    'status' => $docData['status'],
                    'issued_at' => $docData['issued_at'],
                    'task_assignment_type_id' => $this->typeIds[$docData['type']] ?? null,
                    'organization_id' => self::ORG_ID,
                    'created_by' => $creatorId,
                    'updated_by' => $creatorId,
                ]
            ));

            // Ghi đè timestamp đúng (booted hook set auth()->id() và Eloquent set now())
            DB::table('task_assignment_documents')->where('id', $doc->id)->update([
                'organization_id' => self::ORG_ID,
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $createdAt, 'updated_at' => $updatedAt,
            ]);

            foreach ($docData['items'] as $itemIndex => $itemData) {
                $this->seedItem($doc->id, $docData, $itemData, $itemIndex, $creatorId);
            }
        }
    }

    private function seedItem(int $docId, array $docData, array $itemData, int $itemIndex, int $docCreatorId): void
    {
        $unit = $itemData['unit'];
        $deptId = $this->deptIds[$unit] ?? null;
        $assignee = $this->representatives[$unit] ?? null;
        if (! $deptId || ! $assignee) {
            return;
        }

        $status = $itemData['processing_status'];
        $percent = $itemData['completion_percent'];
        $startAt = Carbon::parse($itemData['start_at']);
        $endAt = $itemData['end_at'] ? Carbon::parse($itemData['end_at']) : null;
        $completedAt = isset($itemData['completed_at']) ? Carbon::parse($itemData['completed_at']) : null;

        // Giao việc trước ngày bắt đầu 1 ngày; báo cáo cuối cùng chốt trạng thái.
        $itemCreatedAt = $startAt->copy()->subDay()->setTime(8, 30 + $itemIndex);
        $reportedAt = $this->resolveReportedAt($itemData, $startAt, $endAt, $completedAt);
        $itemUpdatedAt = $completedAt ?? $reportedAt ?? $itemCreatedAt->copy()->addDays(3)->setTime(16, 0);

        $item = TaskAssignmentItem::unguarded(fn () => TaskAssignmentItem::withoutGlobalScopes()->firstOrCreate(
            [
                'task_assignment_document_id' => $docId,
                'name' => $itemData['name'],
            ],
            [
                'description' => $itemData['description'] ?? null,
                'task_assignment_item_type_id' => $this->itemTypeIds[$itemData['item_type']] ?? null,
                'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
                'start_at' => $startAt,
                'end_at' => $endAt,
                'processing_status' => $status,
                'completion_percent' => $percent,
                'priority' => $itemData['priority'],
                'completed_at' => $completedAt,
                'organization_id' => self::ORG_ID,
            ]
        ));

        DB::table('task_assignment_items')->where('id', $item->id)->update([
            'organization_id' => self::ORG_ID,
            'assigned_by' => $docCreatorId,
            'reported_at' => $reportedAt,
            'reported_by' => $reportedAt ? $assignee->id : null,
            'rejection_reason' => $itemData['rejection_reason'] ?? null,
            'approved_by' => $status === TaskProgressStatusEnum::Done->value ? $docCreatorId : null,
            'created_by' => $docCreatorId, 'updated_by' => $status === TaskProgressStatusEnum::Todo->value ? $docCreatorId : $assignee->id,
            'created_at' => $itemCreatedAt, 'updated_at' => $itemUpdatedAt,
        ]);

        $this->attachAssignees($item, $unit, $deptId, $assignee, $status, $startAt, $completedAt, $reportedAt);
        $this->seedReports($item, $docData, $itemData, $deptId, $assignee, $docCreatorId, $startAt, $endAt, $completedAt, $reportedAt);
        $this->seedNotes($item, $itemData, $assignee, $docCreatorId, $itemCreatedAt, $reportedAt);
    }

    /** Ngày đơn vị chốt báo cáo hoàn thành (done/pending_approval). */
    private function resolveReportedAt(array $itemData, Carbon $startAt, ?Carbon $endAt, ?Carbon $completedAt): ?Carbon
    {
        $status = $itemData['processing_status'];

        if (! empty($itemData['reported_at'])) {
            return Carbon::parse($itemData['reported_at']);
        }

        if ($status === TaskProgressStatusEnum::Done->value) {
            return ($completedAt ?? $endAt ?? $startAt)->copy();
        }

        if ($status === TaskProgressStatusEnum::PendingApproval->value) {
            return $endAt
                ? $endAt->copy()->subDays(2)->setTime(15, 30)
                : $startAt->copy()->addDays(10)->setTime(15, 30);
        }

        return null;
    }

    /** Gắn người đảm nhiệm chính (đại diện đơn vị) + 1 người phối hợp cùng đơn vị. */
    private function attachAssignees(
        TaskAssignmentItem $item,
        string $unit,
        int $deptId,
        User $assignee,
        string $status,
        Carbon $startAt,
        ?Carbon $completedAt,
        ?Carbon $reportedAt
    ): void {
        $isFinished = in_array($status, [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::PendingApproval->value], true);
        $assignmentStatus = $isFinished
            ? TaskUserAssignmentStatusEnum::Done->value
            : TaskUserAssignmentStatusEnum::Assigned->value;

        if (! $item->users()->where('user_id', $assignee->id)->exists()) {
            $item->users()->attach($assignee->id, [
                'department_id' => $deptId,
                'department_role' => 'main',
                'assignment_role' => 'main',
                'assignment_status' => $assignmentStatus,
                'assigned_at' => $startAt,
                'accepted_at' => $status === TaskProgressStatusEnum::Todo->value ? null : $startAt,
                'completed_at' => $completedAt ?? $reportedAt,
                'note' => 'Đơn vị chủ trì thực hiện và báo cáo kết quả.',
            ]);
            $this->fixPivotTimestamps($item->id, $assignee->id, $startAt, $completedAt ?? $reportedAt ?? $startAt);
        }

        // Người phối hợp: thành viên tiếp theo của chính đơn vị đó (nếu có).
        $support = ($this->membersByUnit[$unit] ?? collect())
            ->first(fn (User $u) => $u->id !== $assignee->id);

        if ($support && ! $item->users()->where('user_id', $support->id)->exists()) {
            $item->users()->attach($support->id, [
                'department_id' => $deptId,
                'department_role' => 'main',
                'assignment_role' => 'support',
                'assignment_status' => $assignmentStatus,
                'assigned_at' => $startAt,
                'accepted_at' => $status === TaskProgressStatusEnum::Todo->value ? null : $startAt,
                'completed_at' => $completedAt ?? $reportedAt,
                'note' => 'Phối hợp thực hiện, chuẩn bị số liệu và hồ sơ.',
            ]);
            $this->fixPivotTimestamps($item->id, $support->id, $startAt, $completedAt ?? $reportedAt ?? $startAt);
        }
    }

    /**
     * Báo cáo của đơn vị:
     * - Đầu việc có tiến độ ≥ 60%: 1 báo cáo tiến độ giữa kỳ + 1 báo cáo kết quả.
     * - Đầu việc đang làm/tạm dừng: 1 báo cáo tiến độ.
     * - Đầu việc chưa thực hiện (todo) hoặc đã huỷ: không có báo cáo.
     */
    private function seedReports(
        TaskAssignmentItem $item,
        array $docData,
        array $itemData,
        int $deptId,
        User $assignee,
        int $docCreatorId,
        Carbon $startAt,
        ?Carbon $endAt,
        ?Carbon $completedAt,
        ?Carbon $reportedAt
    ): void {
        $status = $itemData['processing_status'];
        $percent = (int) $itemData['completion_percent'];

        if (in_array($status, [TaskProgressStatusEnum::Todo->value, TaskProgressStatusEnum::Cancelled->value], true) || $percent === 0) {
            return;
        }

        $unitName = self::UNITS[$itemData['unit']]['name'];
        $finalAt = $completedAt ?? $reportedAt ?? $startAt->copy()->addDays(14)->setTime(16, 0);
        $reports = [];

        if ($percent >= 60) {
            $interimPercent = (int) (floor($percent / 2 / 10) * 10);
            $interimAt = $startAt->copy()->addDays(max(1, (int) ($startAt->diffInDays($finalAt) / 2)))->setTime(15, 0);

            $reports[] = [
                'percent' => max(10, $interimPercent),
                'reported_at' => $interimAt,
                'completed_at' => null,
                'excerpt' => 'Báo cáo tiến độ thực hiện: '.$itemData['name'],
                'content' => $unitName.' báo cáo tiến độ thực hiện nhiệm vụ đến ngày '.$interimAt->format('d/m/Y')
                    .': đã hoàn thành khoảng '.max(10, $interimPercent).'% khối lượng công việc được giao. '
                    .($itemData['progress_note'] ?? 'Đơn vị đang bám sát tiến độ, chưa phát sinh vướng mắc.'),
            ];
        }

        $isFinished = in_array($status, [TaskProgressStatusEnum::Done->value, TaskProgressStatusEnum::PendingApproval->value], true);

        $reports[] = [
            'percent' => $percent,
            'reported_at' => $isFinished ? $finalAt : ($endAt ? $startAt->copy()->addDays(max(1, (int) ($startAt->diffInDays($endAt) * 0.7)))->setTime(16, 0) : $startAt->copy()->addDays(20)->setTime(16, 0)),
            'completed_at' => $isFinished ? $finalAt : null,
            'excerpt' => ($isFinished ? 'Báo cáo kết quả thực hiện: ' : 'Báo cáo tiến độ thực hiện: ').$itemData['name'],
            'content' => $this->buildReportContent($unitName, $itemData, $status, $percent, $finalAt),
        ];

        foreach ($reports as $i => $data) {
            $record = TaskAssignmentItemReport::withoutGlobalScopes()
                ->where('task_assignment_item_id', $item->id)
                ->where('report_document_number', $this->reportDocumentNumber($docData['issue_date'], $item->id, $i))
                ->first();

            if ($record) {
                continue;
            }

            $record = TaskAssignmentItemReport::unguarded(fn () => TaskAssignmentItemReport::create([
                'task_assignment_item_id' => $item->id,
                'reporter_user_id' => $assignee->id,
                'assignee_user_id' => $assignee->id,
                'completion_percent' => $data['percent'],
                'completed_at' => $data['completed_at'],
                'report_document_number' => $this->reportDocumentNumber($docData['issue_date'], $item->id, $i),
                'report_document_excerpt' => $data['excerpt'],
                'report_document_content' => $data['content'],
                'organization_id' => self::ORG_ID,
            ]));

            DB::table('task_assignment_item_reports')->where('id', $record->id)->update([
                'organization_id' => self::ORG_ID,
                'created_by' => $assignee->id, 'updated_by' => $assignee->id,
                'created_at' => $data['reported_at'], 'updated_at' => $data['reported_at'],
            ]);
        }
    }

    private function buildReportContent(string $unitName, array $itemData, string $status, int $percent, Carbon $finalAt): string
    {
        $name = $itemData['name'];

        return match ($status) {
            TaskProgressStatusEnum::Done->value => $unitName.' đã hoàn thành 100% nhiệm vụ "'.$name.'" vào ngày '
                .$finalAt->format('d/m/Y').'. '.($itemData['result_note'] ?? 'Kết quả thực hiện bảo đảm yêu cầu về nội dung và thời gian, hồ sơ đã được gửi về Văn phòng Đảng uỷ để tổng hợp.'),
            TaskProgressStatusEnum::PendingApproval->value => $unitName.' đã hoàn thành nhiệm vụ "'.$name
                .'" và trình Thường trực Đảng uỷ xem xét, phê duyệt kết quả. '
                .($itemData['result_note'] ?? 'Đơn vị đã gửi kèm hồ sơ, tài liệu minh chứng theo quy định.'),
            TaskProgressStatusEnum::Paused->value => $unitName.' báo cáo nhiệm vụ "'.$name.'" đang tạm dừng ở mức '
                .$percent.'%. '.($itemData['result_note'] ?? 'Đề nghị Thường trực Đảng uỷ cho chủ trương để đơn vị tiếp tục triển khai.'),
            default => $unitName.' báo cáo nhiệm vụ "'.$name.'" đã thực hiện được '.$percent
                .'% khối lượng công việc. '.($itemData['result_note'] ?? 'Đơn vị tiếp tục triển khai, bảo đảm hoàn thành đúng thời hạn được giao.'),
        };
    }

    /** Trao đổi giữa lãnh đạo giao việc và đơn vị thực hiện. */
    private function seedNotes(
        TaskAssignmentItem $item,
        array $itemData,
        User $assignee,
        int $docCreatorId,
        Carbon $itemCreatedAt,
        ?Carbon $reportedAt
    ): void {
        $notes = [[
            'author_user_id' => $docCreatorId,
            'author_role' => 'manager',
            'content' => 'Đề nghị đơn vị chủ động triển khai, báo cáo tiến độ về Văn phòng Đảng uỷ trước thời hạn kết thúc.',
            'created_at' => $itemCreatedAt->copy()->addMinutes(20),
        ]];

        if ($itemData['processing_status'] !== TaskProgressStatusEnum::Todo->value) {
            $notes[] = [
                'author_user_id' => $assignee->id,
                'author_role' => 'assignee',
                'content' => 'Đơn vị đã tiếp nhận nhiệm vụ, phân công cán bộ phụ trách và triển khai theo kế hoạch.',
                'created_at' => $itemCreatedAt->copy()->addDay()->setTime(9, 30),
            ];
        }

        if (! empty($itemData['rejection_reason'])) {
            $notes[] = [
                'author_user_id' => $docCreatorId,
                'author_role' => 'manager',
                'content' => 'Kết quả báo cáo chưa đạt yêu cầu: '.$itemData['rejection_reason'].' Đề nghị đơn vị bổ sung và báo cáo lại.',
                'created_at' => ($reportedAt ?? $itemCreatedAt)->copy()->addDay()->setTime(10, 0),
            ];
        }

        foreach ($notes as $note) {
            $record = TaskAssignmentItemNote::withoutGlobalScopes()->firstOrCreate(
                [
                    'task_assignment_item_id' => $item->id,
                    'author_user_id' => $note['author_user_id'],
                    'author_role' => $note['author_role'],
                    'content' => $note['content'],
                ],
                ['organization_id' => self::ORG_ID]
            );

            DB::table('task_assignment_item_notes')->where('id', $record->id)->update([
                'organization_id' => self::ORG_ID,
                'created_at' => $note['created_at'],
            ]);
        }
    }

    /** withTimestamps() trên pivot ghi đè created_at/updated_at = now() → chỉnh lại theo timeline. */
    private function fixPivotTimestamps(int $itemId, int $userId, Carbon $createdAt, Carbon $updatedAt): void
    {
        DB::table('task_assignment_item_user')
            ->where('task_assignment_item_id', $itemId)
            ->where('user_id', $userId)
            ->update(['created_at' => $createdAt, 'updated_at' => $updatedAt]);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** Lấy ngẫu nhiên user có quyền tạo danh mục (Super Admin, Admin, Quản trị). */
    private function getCategoryCreatorId(): int
    {
        if (empty($this->categoryCreatorUserIds)) {
            $this->categoryCreatorUserIds = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['Super Admin', 'Admin', 'Quản trị']);
            })->pluck('id')->toArray();

            if (empty($this->categoryCreatorUserIds)) {
                $this->categoryCreatorUserIds = User::whereIn('user_name', ['admin', 'quantri'])->pluck('id')->toArray();
            }
            if (empty($this->categoryCreatorUserIds)) {
                $this->categoryCreatorUserIds = [User::first()->id];
            }
        }

        return $this->categoryCreatorUserIds[array_rand($this->categoryCreatorUserIds)];
    }

    /**
     * Người ký/giao việc: Super Admin, Admin, Quản trị — không lấy 6 tài khoản đơn vị
     * để phân biệt rõ vai "giao việc" và vai "nhận việc, báo cáo".
     */
    private function getAuthorizedUserId(): int
    {
        if (empty($this->authorizedUserIds)) {
            $this->authorizedUserIds = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['Super Admin', 'Admin', 'Quản trị']);
            })
                ->whereNotIn('user_name', array_values(self::UNIT_ACCOUNTS))
                ->pluck('id')->toArray();

            if (empty($this->authorizedUserIds)) {
                $this->authorizedUserIds = User::whereIn('user_name', ['admin', 'quantri'])->pluck('id')->toArray();
            }
            if (empty($this->authorizedUserIds)) {
                $this->authorizedUserIds = [User::first()->id];
            }
        }

        return $this->authorizedUserIds[array_rand($this->authorizedUserIds)];
    }

    private function reportDocumentNumber(string $issueDate, int $itemId, int $index): string
    {
        return sprintf('%03d-BC/ĐU-%s', ($itemId * 2 + $index) % 900 + 100, date('Y', strtotime($issueDate)));
    }

    /**
     * 15 văn bản giao việc × 5 đầu việc = 75 đầu việc, trải từ 03/2026 đến 08/2026.
     *
     * Phân bổ trạng thái: 52 hoàn thành, 6 chờ duyệt, 12 đang thực hiện,
     * 3 chưa thực hiện, 1 tạm dừng, 1 đã huỷ.
     */
    private function getDocumentData(): array
    {
        return [
            // ═══════════════════ THÁNG 3/2026 ═══════════════════
            [
                'name' => 'Chương trình công tác quý II năm 2026 của Đảng uỷ',
                'summary' => 'Xác định nhiệm vụ trọng tâm và phân công các đơn vị triển khai chương trình công tác quý II/2026 của Đảng uỷ.',
                'issue_date' => '2026-03-02', 'status' => 'issued', 'issued_at' => '2026-03-02 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Xây dựng dự thảo chương trình công tác quý II/2026', 'description' => 'Tổng hợp nhiệm vụ từ các đơn vị, dự thảo chương trình công tác quý II trình Ban Thường vụ Đảng uỷ.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-03-02 08:00:00', 'end_at' => '2026-03-12 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-11 15:00:00', 'result_note' => 'Dự thảo đã được Thường trực Đảng uỷ thông qua, không có ý kiến khác.'],
                    ['unit' => 'BXDD', 'name' => 'Rà soát, tổng hợp nhiệm vụ công tác xây dựng Đảng quý II', 'description' => 'Rà soát các nhiệm vụ về tổ chức, cán bộ, đảng viên, tuyên giáo, dân vận để đưa vào chương trình quý II.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-03-02 08:00:00', 'end_at' => '2026-03-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-14 14:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Đề xuất nội dung kiểm tra, giám sát đưa vào chương trình quý II', 'description' => 'Đề xuất 3 chuyên đề kiểm tra và 2 chuyên đề giám sát trọng tâm quý II/2026.', 'item_type' => 'Nghiên cứu, khảo sát', 'start_at' => '2026-03-03 08:00:00', 'end_at' => '2026-03-16 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-16 11:00:00'],
                    ['unit' => 'DUUBND', 'name' => 'Tổng hợp nhiệm vụ trọng tâm của khối chính quyền quý II', 'description' => 'Tổng hợp chỉ tiêu kinh tế - xã hội và nhiệm vụ trọng tâm của khối chính quyền quý II/2026.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-03-03 08:00:00', 'end_at' => '2026-03-18 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-17 16:00:00'],
                    ['unit' => 'VPDU', 'name' => 'Tổ chức hội nghị Đảng uỷ thông qua chương trình công tác quý II', 'description' => 'Chuẩn bị tài liệu, giấy mời, hội trường và phục vụ hội nghị Đảng uỷ mở rộng.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-03-18 08:00:00', 'end_at' => '2026-03-27 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-26 17:00:00', 'result_note' => 'Hội nghị có 100% đảng uỷ viên dự, chương trình được thông qua với tỷ lệ tán thành tuyệt đối.'],
                ],
            ],
            [
                'name' => 'Kế hoạch kiểm tra, giám sát năm 2026 của Đảng uỷ',
                'summary' => 'Triển khai chương trình kiểm tra, giám sát năm 2026 đối với tổ chức đảng và đảng viên thuộc Đảng bộ.',
                'issue_date' => '2026-03-10', 'status' => 'issued', 'issued_at' => '2026-03-10 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'UBKT', 'name' => 'Xây dựng kế hoạch kiểm tra, giám sát năm 2026', 'description' => 'Soạn thảo kế hoạch kiểm tra, giám sát năm 2026 kèm đề cương, mốc thời gian và phân công đoàn kiểm tra.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-03-10 08:00:00', 'end_at' => '2026-03-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-19 15:30:00'],
                    ['unit' => 'UBKT', 'name' => 'Kiểm tra việc chấp hành Điều lệ Đảng tại Chi bộ Văn phòng', 'description' => 'Kiểm tra hồ sơ sinh hoạt chi bộ, nghị quyết, sổ ghi biên bản và việc chấp hành nguyên tắc tập trung dân chủ.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-03-23 08:00:00', 'end_at' => '2026-04-03 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-04-02 16:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Hướng dẫn các chi bộ xây dựng kế hoạch tự kiểm tra', 'description' => 'Ban hành văn bản hướng dẫn nội dung, biểu mẫu tự kiểm tra cho các chi bộ trực thuộc.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-03-23 08:00:00', 'end_at' => '2026-04-06 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-04-06 10:00:00'],
                    ['unit' => 'DUCA', 'name' => 'Giám sát chuyên đề công tác quản lý đảng viên tại Đảng uỷ Công an', 'description' => 'Giám sát việc quản lý hồ sơ đảng viên, chuyển sinh hoạt đảng và thực hiện chế độ báo cáo.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-03-25 08:00:00', 'end_at' => '2026-04-10 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-04-09 15:00:00'],
                    ['unit' => 'CUQS', 'name' => 'Báo cáo kết quả tự kiểm tra của Chi uỷ Quân sự', 'description' => 'Tự kiểm tra việc chấp hành Điều lệ Đảng, quy chế làm việc và báo cáo kết quả về Uỷ ban Kiểm tra.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-03-25 08:00:00', 'end_at' => '2026-04-12 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'low', 'completed_at' => '2026-04-13 09:00:00', 'result_note' => 'Báo cáo nộp chậm 01 ngày do trùng lịch huấn luyện, nội dung bảo đảm yêu cầu.'],
                ],
            ],
            [
                'name' => 'Kế hoạch tổ chức sinh hoạt chính trị chuyên đề năm 2026',
                'summary' => 'Tổ chức đợt sinh hoạt chính trị chuyên đề trong toàn Đảng bộ gắn với học tập và làm theo tư tưởng, đạo đức, phong cách Hồ Chí Minh.',
                'issue_date' => '2026-03-18', 'status' => 'issued', 'issued_at' => '2026-03-18 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Soạn thảo đề cương sinh hoạt chính trị chuyên đề', 'description' => 'Biên soạn đề cương, tài liệu tham khảo và gợi ý thảo luận cho các chi bộ.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-03-18 08:00:00', 'end_at' => '2026-03-30 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-29 14:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Tổ chức hội nghị quán triệt chuyên đề cho đảng viên', 'description' => 'Tổ chức hội nghị quán triệt chuyên đề cho toàn thể đảng viên trong Đảng bộ.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-04-01 08:00:00', 'end_at' => '2026-04-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-04-14 16:30:00', 'result_note' => 'Hội nghị có 236/248 đảng viên tham dự, đạt 95,2%.'],
                    ['unit' => 'DUUBND', 'name' => 'Triển khai sinh hoạt chuyên đề tại các chi bộ khối UBND', 'description' => 'Chỉ đạo 9 chi bộ khối UBND tổ chức sinh hoạt chuyên đề và gửi biên bản về Đảng uỷ.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-04-01 08:00:00', 'end_at' => '2026-04-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-04-18 15:00:00'],
                    ['unit' => 'DUCA', 'name' => 'Triển khai sinh hoạt chuyên đề tại Đảng bộ Công an', 'description' => 'Tổ chức sinh hoạt chuyên đề gắn với xây dựng lực lượng Công an nhân dân thật sự trong sạch, vững mạnh.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-04-01 08:00:00', 'end_at' => '2026-04-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-04-19 16:00:00'],
                    ['unit' => 'CUQS', 'name' => 'Báo cáo kết quả sinh hoạt chính trị chuyên đề', 'description' => 'Tổng hợp kết quả sinh hoạt chuyên đề của chi bộ quân sự và lực lượng dân quân tự vệ.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-04-20 08:00:00', 'end_at' => '2026-04-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'low', 'completed_at' => '2026-04-27 11:00:00'],
                ],
            ],

            // ═══════════════════ THÁNG 4/2026 ═══════════════════
            [
                'name' => 'Kế hoạch học tập, quán triệt Nghị quyết Hội nghị Trung ương',
                'summary' => 'Tổ chức học tập, quán triệt và triển khai thực hiện Nghị quyết Hội nghị Trung ương trong toàn Đảng bộ.',
                'issue_date' => '2026-04-05', 'status' => 'issued', 'issued_at' => '2026-04-05 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Xây dựng kế hoạch học tập, quán triệt Nghị quyết', 'description' => 'Xây dựng kế hoạch, phân công báo cáo viên và chuẩn bị điều kiện tổ chức hội nghị trực tuyến.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-04-05 08:00:00', 'end_at' => '2026-04-12 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'urgent', 'completed_at' => '2026-04-11 16:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Tổ chức hội nghị trực tuyến quán triệt Nghị quyết', 'description' => 'Kết nối điểm cầu, bố trí đại biểu và bảo đảm nội dung hội nghị trực tuyến toàn Đảng bộ.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-04-13 08:00:00', 'end_at' => '2026-04-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-04-24 17:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Giám sát việc tổ chức học tập Nghị quyết tại các chi bộ', 'description' => 'Giám sát tỷ lệ đảng viên tham gia, chất lượng bài thu hoạch và việc xây dựng chương trình hành động.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-04-26 08:00:00', 'end_at' => '2026-05-10 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-05-09 15:00:00'],
                    ['unit' => 'DUUBND', 'name' => 'Tổng hợp bài thu hoạch của cán bộ, đảng viên khối UBND', 'description' => 'Thu nhận, chấm và tổng hợp bài thu hoạch của 187 cán bộ, đảng viên khối chính quyền.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-04-26 08:00:00', 'end_at' => '2026-05-08 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-05-08 16:00:00'],
                    ['unit' => 'CUQS', 'name' => 'Hội thi tìm hiểu Nghị quyết trong lực lượng vũ trang', 'description' => 'Tổ chức hội thi tìm hiểu Nghị quyết cho cán bộ, chiến sĩ và dân quân tự vệ.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-05-05 08:00:00', 'end_at' => '2026-05-20 17:00:00', 'processing_status' => 'cancelled', 'completion_percent' => 0, 'priority' => 'low', 'rejection_reason' => 'Huỷ tổ chức do trùng lịch diễn tập khu vực phòng thủ, chuyển nội dung sang hình thức sinh hoạt chi bộ.'],
                ],
            ],
            [
                'name' => 'Chương trình công tác dân vận và thực hiện quy chế dân chủ ở cơ sở',
                'summary' => 'Triển khai công tác dân vận, thực hiện quy chế dân chủ ở cơ sở năm 2026 gắn với đối thoại giữa cấp uỷ với nhân dân.',
                'issue_date' => '2026-04-14', 'status' => 'issued', 'issued_at' => '2026-04-14 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Xây dựng chương trình công tác dân vận năm 2026', 'description' => 'Soạn thảo chương trình công tác dân vận, xác định chỉ tiêu và phân công đơn vị chủ trì từng nội dung.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-04-14 08:00:00', 'end_at' => '2026-04-24 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-04-23 15:00:00'],
                    ['unit' => 'DUUBND', 'name' => 'Kiểm tra thực hiện quy chế dân chủ tại các phòng chuyên môn', 'description' => 'Kiểm tra việc công khai, minh bạch và tiếp nhận ý kiến cán bộ, công chức tại 12 phòng chuyên môn.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-04-27 08:00:00', 'end_at' => '2026-05-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-05-14 16:00:00'],
                    ['unit' => 'DUCA', 'name' => 'Tổ chức đối thoại giữa cấp uỷ với đảng viên, quần chúng', 'description' => 'Tổ chức 2 hội nghị đối thoại, tiếp nhận và giải đáp kiến nghị của đảng viên, quần chúng.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-04-27 08:00:00', 'end_at' => '2026-05-18 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-05-16 17:00:00', 'result_note' => 'Đã tiếp nhận 23 ý kiến, giải đáp trực tiếp 19 ý kiến, chuyển cơ quan chức năng 4 ý kiến.'],
                    ['unit' => 'BXDD', 'name' => 'Theo dõi, đôn đốc công tác dân vận tại các chi bộ', 'description' => 'Nhiệm vụ thường xuyên: theo dõi, nhắc việc và tổng hợp tình hình công tác dân vận của các chi bộ trực thuộc.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-04-14 08:00:00', 'end_at' => null, 'processing_status' => 'in_progress', 'completion_percent' => 60, 'priority' => 'low', 'progress_note' => 'Đã theo dõi và nhắc việc đối với 18/24 chi bộ trực thuộc.'],
                    ['unit' => 'CUQS', 'name' => 'Báo cáo sơ kết công tác dân vận 6 tháng đầu năm', 'description' => 'Sơ kết công tác dân vận trong lực lượng vũ trang, gắn với phong trào "Dân vận khéo".', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-06-01 08:00:00', 'end_at' => '2026-06-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-06-19 14:00:00'],
                ],
            ],
            [
                'name' => 'Kế hoạch rà soát, bổ sung quy hoạch cán bộ nhiệm kỳ 2026-2031',
                'summary' => 'Rà soát, bổ sung quy hoạch cấp uỷ và cán bộ lãnh đạo, quản lý nhiệm kỳ 2026-2031 theo hướng dẫn của cấp trên.',
                'issue_date' => '2026-04-22', 'status' => 'issued', 'issued_at' => '2026-04-22 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'BXDD', 'name' => 'Xây dựng kế hoạch rà soát, bổ sung quy hoạch cán bộ', 'description' => 'Xây dựng kế hoạch, tiêu chuẩn, quy trình 5 bước và biểu mẫu phục vụ rà soát quy hoạch.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-04-22 08:00:00', 'end_at' => '2026-05-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-05-04 16:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Tổ chức hội nghị lấy phiếu tín nhiệm giới thiệu nguồn quy hoạch', 'description' => 'Tổ chức hội nghị cán bộ chủ chốt, hội nghị Ban Chấp hành lấy phiếu giới thiệu nguồn quy hoạch.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-05-06 08:00:00', 'end_at' => '2026-05-22 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-05-21 17:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Thẩm định tiêu chuẩn chính trị nhân sự quy hoạch', 'description' => 'Thẩm định tiêu chuẩn chính trị, kết luận về lịch sử chính trị và chính trị hiện nay của nhân sự quy hoạch.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-05-06 08:00:00', 'end_at' => '2026-05-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-05-25 15:00:00'],
                    ['unit' => 'VPDU', 'name' => 'Hoàn thiện hồ sơ quy hoạch trình cấp uỷ cấp trên', 'description' => 'Hoàn thiện, đóng quyển hồ sơ quy hoạch và trình Ban Thường vụ cấp trên phê duyệt.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-05-26 08:00:00', 'end_at' => '2026-06-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-06-04 16:30:00'],
                    ['unit' => 'DUUBND', 'name' => 'Rà soát, bổ sung quy hoạch cán bộ khối chính quyền', 'description' => 'Rà soát nguồn quy hoạch các chức danh lãnh đạo phòng, ban thuộc UBND.', 'item_type' => 'Nghiên cứu, khảo sát', 'start_at' => '2026-05-06 08:00:00', 'end_at' => '2026-06-10 17:00:00', 'processing_status' => 'paused', 'completion_percent' => 40, 'priority' => 'medium', 'progress_note' => 'Đã rà soát 6/12 phòng chuyên môn.', 'result_note' => 'Tạm dừng chờ hướng dẫn mới của cấp trên về tiêu chuẩn chức danh sau sắp xếp bộ máy.'],
                ],
            ],

            // ═══════════════════ THÁNG 5/2026 ═══════════════════
            [
                'name' => 'Kế hoạch tổ chức Đại hội chi bộ trực thuộc nhiệm kỳ 2026-2028',
                'summary' => 'Chỉ đạo và tổ chức đại hội các chi bộ trực thuộc nhiệm kỳ 2026-2028 bảo đảm đúng nguyên tắc, quy trình, tiến độ.',
                'issue_date' => '2026-05-06', 'status' => 'issued', 'issued_at' => '2026-05-06 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Ban hành kế hoạch và hướng dẫn tổ chức Đại hội chi bộ', 'description' => 'Ban hành kế hoạch, hướng dẫn và bộ biểu mẫu tổ chức đại hội chi bộ nhiệm kỳ 2026-2028.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-05-06 08:00:00', 'end_at' => '2026-05-18 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'urgent', 'completed_at' => '2026-05-17 15:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Hướng dẫn xây dựng văn kiện và nhân sự Đại hội chi bộ', 'description' => 'Hướng dẫn, thẩm định báo cáo chính trị và đề án nhân sự của 24 chi bộ trực thuộc.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-05-19 08:00:00', 'end_at' => '2026-06-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-06-04 16:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Kiểm tra công tác chuẩn bị Đại hội tại các chi bộ', 'description' => 'Kiểm tra tiến độ, hồ sơ nhân sự và điều kiện tổ chức đại hội tại các chi bộ trực thuộc.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-06-08 08:00:00', 'end_at' => '2026-06-26 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-06-25 16:00:00'],
                    ['unit' => 'DUCA', 'name' => 'Tổ chức Đại hội điểm tại Chi bộ Công an', 'description' => 'Tổ chức đại hội điểm để rút kinh nghiệm chung cho toàn Đảng bộ.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-06-15 08:00:00', 'end_at' => '2026-07-03 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'urgent', 'completed_at' => '2026-07-02 17:00:00', 'result_note' => 'Đại hội điểm thành công, Đảng uỷ đã tổ chức rút kinh nghiệm ngay sau đại hội.'],
                    ['unit' => 'CUQS', 'name' => 'Tổ chức Đại hội Chi bộ Quân sự nhiệm kỳ 2026-2028', 'description' => 'Tổ chức đại hội chi bộ quân sự, bầu chi uỷ và bí thư, phó bí thư nhiệm kỳ mới.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-06-20 08:00:00', 'end_at' => '2026-07-10 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-09 16:00:00'],
                ],
            ],
            [
                'name' => 'Kế hoạch bảo đảm an ninh trật tự và phòng chống tội phạm quý II/2026',
                'summary' => 'Tăng cường lãnh đạo công tác bảo đảm an ninh trật tự, phòng chống tội phạm và tệ nạn xã hội trên địa bàn quý II/2026.',
                'issue_date' => '2026-05-13', 'status' => 'issued', 'issued_at' => '2026-05-13 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'DUCA', 'name' => 'Xây dựng phương án bảo đảm ANTT trên địa bàn quý II', 'description' => 'Xây dựng phương án, phân công lực lượng và địa bàn trọng điểm bảo đảm an ninh trật tự quý II.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-05-13 08:00:00', 'end_at' => '2026-05-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-05-24 15:00:00'],
                    ['unit' => 'DUCA', 'name' => 'Mở đợt cao điểm tấn công trấn áp tội phạm', 'description' => 'Triển khai đợt cao điểm tấn công trấn áp tội phạm, bảo đảm an ninh trật tự trên địa bàn.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-05-26 08:00:00', 'end_at' => '2026-06-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'urgent', 'completed_at' => '2026-06-24 17:00:00', 'result_note' => 'Đã điều tra, xử lý 31 vụ việc; phạm pháp hình sự giảm 12% so với cùng kỳ.'],
                    ['unit' => 'CUQS', 'name' => 'Phối hợp tuần tra bảo đảm an ninh khu vực trọng điểm', 'description' => 'Phối hợp lực lượng dân quân tự vệ và công an tuần tra các khu vực trọng điểm về an ninh trật tự.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-05-26 08:00:00', 'end_at' => '2026-06-30 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-06-29 16:00:00'],
                    ['unit' => 'DUUBND', 'name' => 'Tuyên truyền phòng chống tội phạm tại khu dân cư', 'description' => 'Tổ chức tuyên truyền pháp luật, phòng chống tội phạm và tệ nạn xã hội tại 15 khu dân cư.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-06-01 08:00:00', 'end_at' => '2026-07-10 17:00:00', 'processing_status' => 'pending_approval', 'completion_percent' => 100, 'priority' => 'medium', 'reported_at' => '2026-07-08 15:30:00', 'result_note' => 'Đã tổ chức 15/15 buổi tuyên truyền với hơn 1.800 lượt người dân tham dự, kính trình Thường trực Đảng uỷ phê duyệt kết quả.'],
                    ['unit' => 'VPDU', 'name' => 'Tổng hợp báo cáo tình hình ANTT quý II báo cáo cấp uỷ', 'description' => 'Tổng hợp số liệu, dự thảo báo cáo tình hình an ninh trật tự quý II trình Thường trực Đảng uỷ.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-06-25 08:00:00', 'end_at' => '2026-07-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-07-04 15:00:00'],
                ],
            ],
            [
                'name' => 'Chương trình kiểm tra việc thực hiện Điều lệ Đảng tại các chi bộ trực thuộc',
                'summary' => 'Kiểm tra việc chấp hành Điều lệ Đảng, nghị quyết và quy chế làm việc tại các chi bộ trực thuộc Đảng bộ.',
                'issue_date' => '2026-05-20', 'status' => 'issued', 'issued_at' => '2026-05-20 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'UBKT', 'name' => 'Thành lập đoàn kiểm tra và ban hành đề cương kiểm tra', 'description' => 'Ra quyết định thành lập đoàn kiểm tra, ban hành đề cương và lịch kiểm tra cụ thể.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-05-20 08:00:00', 'end_at' => '2026-05-30 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-05-29 15:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Kiểm tra tại Chi bộ Đảng uỷ UBND', 'description' => 'Kiểm tra hồ sơ, sổ sách, nghị quyết và việc thực hiện nguyên tắc tập trung dân chủ tại chi bộ khối UBND.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-06-01 08:00:00', 'end_at' => '2026-06-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-06-19 16:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Kiểm tra tại Chi bộ Công an', 'description' => 'Kiểm tra việc chấp hành Điều lệ Đảng và quy chế làm việc tại Chi bộ Công an.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-06-22 08:00:00', 'end_at' => '2026-07-10 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-07-09 16:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Kiểm tra tại Chi bộ Quân sự', 'description' => 'Kiểm tra việc chấp hành Điều lệ Đảng, chế độ sinh hoạt và quản lý đảng viên tại Chi bộ Quân sự.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-07-13 08:00:00', 'end_at' => '2026-08-05 17:00:00', 'processing_status' => 'pending_approval', 'completion_percent' => 100, 'priority' => 'medium', 'reported_at' => '2026-08-04 15:30:00', 'result_note' => 'Đoàn kiểm tra đã hoàn tất làm việc, dự thảo báo cáo kết quả trình Uỷ ban Kiểm tra xem xét.'],
                    ['unit' => 'BXDD', 'name' => 'Tổng hợp, ban hành thông báo kết luận kiểm tra', 'description' => 'Tổng hợp kết quả 3 cuộc kiểm tra, dự thảo thông báo kết luận và kiến nghị khắc phục.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-08-06 08:00:00', 'end_at' => '2026-08-25 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 55, 'priority' => 'high', 'progress_note' => 'Đã dự thảo xong phần đánh giá chung, đang hoàn thiện phần kiến nghị.'],
                ],
            ],

            // ═══════════════════ THÁNG 6/2026 ═══════════════════
            [
                'name' => 'Kế hoạch sơ kết công tác xây dựng Đảng 6 tháng đầu năm 2026',
                'summary' => 'Sơ kết công tác xây dựng Đảng 6 tháng đầu năm, xác định nhiệm vụ trọng tâm 6 tháng cuối năm 2026.',
                'issue_date' => '2026-06-03', 'status' => 'issued', 'issued_at' => '2026-06-03 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'BXDD', 'name' => 'Xây dựng đề cương báo cáo sơ kết 6 tháng', 'description' => 'Xây dựng đề cương báo cáo sơ kết và biểu mẫu số liệu gửi các đơn vị.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-06-03 08:00:00', 'end_at' => '2026-06-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-06-14 15:00:00'],
                    ['unit' => 'VPDU', 'name' => 'Tổng hợp số liệu, dự thảo báo cáo sơ kết', 'description' => 'Tổng hợp số liệu từ 6 đơn vị, dự thảo báo cáo sơ kết công tác xây dựng Đảng 6 tháng đầu năm.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-06-16 08:00:00', 'end_at' => '2026-07-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-04 16:00:00'],
                    ['unit' => 'UBKT', 'name' => 'Báo cáo kết quả công tác kiểm tra, giám sát 6 tháng', 'description' => 'Tổng hợp kết quả kiểm tra, giám sát và thi hành kỷ luật đảng 6 tháng đầu năm 2026.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-06-16 08:00:00', 'end_at' => '2026-07-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-07-06 10:00:00', 'result_note' => 'Báo cáo hoàn thành chậm 01 ngày so với hạn do phải bổ sung số liệu của 2 chi bộ.'],
                    ['unit' => 'DUUBND', 'name' => 'Báo cáo kết quả lãnh đạo thực hiện nhiệm vụ chính trị 6 tháng', 'description' => 'Báo cáo kết quả lãnh đạo thực hiện nhiệm vụ chính trị, chỉ tiêu kinh tế - xã hội 6 tháng đầu năm.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-06-16 08:00:00', 'end_at' => '2026-07-08 17:00:00', 'processing_status' => 'pending_approval', 'completion_percent' => 100, 'priority' => 'high', 'reported_at' => '2026-07-07 16:00:00', 'result_note' => 'Báo cáo đã hoàn thiện với đầy đủ số liệu 12 phòng chuyên môn, trình Thường trực Đảng uỷ duyệt.'],
                    ['unit' => 'VPDU', 'name' => 'Tổ chức hội nghị sơ kết công tác xây dựng Đảng 6 tháng', 'description' => 'Chuẩn bị và phục vụ hội nghị sơ kết công tác xây dựng Đảng 6 tháng đầu năm 2026.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-07-08 08:00:00', 'end_at' => '2026-07-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-18 17:00:00'],
                ],
            ],
            [
                'name' => 'Kế hoạch diễn tập khu vực phòng thủ năm 2026',
                'summary' => 'Lãnh đạo, chỉ đạo công tác chuẩn bị và tổ chức diễn tập khu vực phòng thủ năm 2026.',
                'issue_date' => '2026-06-12', 'status' => 'issued', 'issued_at' => '2026-06-12 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'CUQS', 'name' => 'Xây dựng kế hoạch và ý định diễn tập khu vực phòng thủ', 'description' => 'Xây dựng kế hoạch, ý định diễn tập và hệ thống văn kiện diễn tập khu vực phòng thủ năm 2026.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-06-12 08:00:00', 'end_at' => '2026-06-30 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-06-29 16:00:00'],
                    ['unit' => 'CUQS', 'name' => 'Tổ chức luyện tập các khung diễn tập', 'description' => 'Tổ chức luyện tập khung A, khung B theo từng giai đoạn diễn tập.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-07-01 08:00:00', 'end_at' => '2026-08-25 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 70, 'priority' => 'high', 'progress_note' => 'Đã hoàn thành luyện tập giai đoạn 1 và 2, đang chuẩn bị giai đoạn 3.'],
                    ['unit' => 'DUCA', 'name' => 'Xây dựng phương án bảo đảm an ninh trong diễn tập', 'description' => 'Xây dựng phương án bảo đảm an ninh, an toàn và phân luồng giao thông phục vụ diễn tập.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-07-01 08:00:00', 'end_at' => '2026-08-10 17:00:00', 'processing_status' => 'pending_approval', 'completion_percent' => 100, 'priority' => 'high', 'reported_at' => '2026-08-08 15:00:00', 'result_note' => 'Phương án đã hiệp đồng xong với Ban Chỉ huy Quân sự, trình Thường trực Đảng uỷ phê duyệt.'],
                    ['unit' => 'DUUBND', 'name' => 'Bảo đảm hậu cần, kinh phí phục vụ diễn tập', 'description' => 'Bố trí kinh phí, bảo đảm hậu cần, vật chất và nơi ăn nghỉ cho lực lượng tham gia diễn tập.', 'item_type' => 'Nghiên cứu, khảo sát', 'start_at' => '2026-07-05 08:00:00', 'end_at' => '2026-08-28 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 45, 'priority' => 'medium', 'result_note' => 'Đã bố trí 60% kinh phí, đang hoàn tất thủ tục mua sắm vật chất bảo đảm.'],
                    ['unit' => 'VPDU', 'name' => 'Tổng hợp, báo cáo tiến độ chuẩn bị diễn tập', 'description' => 'Theo dõi, tổng hợp tiến độ chuẩn bị của các đơn vị và báo cáo Thường trực Đảng uỷ hằng tuần.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-07-05 08:00:00', 'end_at' => '2026-08-30 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 60, 'priority' => 'medium', 'progress_note' => 'Đã báo cáo tiến độ 6 tuần liên tiếp, các đơn vị cơ bản bám sát kế hoạch.'],
                ],
            ],
            [
                'name' => 'Kế hoạch cải cách hành chính và chuyển đổi số năm 2026',
                'summary' => 'Lãnh đạo thực hiện nhiệm vụ cải cách hành chính, chuyển đổi số trong hệ thống chính trị năm 2026.',
                'issue_date' => '2026-06-24', 'status' => 'issued', 'issued_at' => '2026-06-24 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'DUUBND', 'name' => 'Rà soát, đơn giản hoá thủ tục hành chính thuộc thẩm quyền', 'description' => 'Rà soát toàn bộ thủ tục hành chính thuộc thẩm quyền giải quyết, đề xuất cắt giảm thời gian xử lý.', 'item_type' => 'Nghiên cứu, khảo sát', 'start_at' => '2026-06-24 08:00:00', 'end_at' => '2026-07-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-19 16:00:00', 'result_note' => 'Đã rà soát 148 thủ tục, đề xuất cắt giảm thời gian xử lý 27 thủ tục.'],
                    ['unit' => 'DUUBND', 'name' => 'Triển khai dịch vụ công trực tuyến toàn trình', 'description' => 'Triển khai, hướng dẫn người dân sử dụng dịch vụ công trực tuyến toàn trình trên Cổng dịch vụ công.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-07-21 08:00:00', 'end_at' => '2026-08-31 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 65, 'priority' => 'high', 'progress_note' => 'Tỷ lệ hồ sơ trực tuyến đạt 65%, đang tiếp tục hỗ trợ người dân tại bộ phận một cửa.'],
                    ['unit' => 'VPDU', 'name' => 'Số hoá hồ sơ, tài liệu lưu trữ của Đảng uỷ', 'description' => 'Số hoá hồ sơ, tài liệu lưu trữ giai đoạn 2020-2025 và đưa lên hệ thống quản lý văn bản.', 'item_type' => 'Nghiên cứu, khảo sát', 'start_at' => '2026-07-01 08:00:00', 'end_at' => '2026-09-30 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 50, 'priority' => 'medium', 'progress_note' => 'Đã số hoá 1.240/2.500 hồ sơ, tiến độ bảo đảm theo kế hoạch.'],
                    ['unit' => 'BXDD', 'name' => 'Cập nhật cơ sở dữ liệu đảng viên lên phần mềm quản lý', 'description' => 'Rà soát, chuẩn hoá và cập nhật dữ liệu 248 đảng viên lên phần mềm quản lý đảng viên.', 'item_type' => 'Nghiên cứu, khảo sát', 'start_at' => '2026-07-01 08:00:00', 'end_at' => '2026-08-31 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 75, 'priority' => 'high', 'progress_note' => 'Đã cập nhật 186/248 hồ sơ đảng viên, còn lại chờ bổ sung giấy tờ.'],
                    ['unit' => 'DUCA', 'name' => 'Triển khai ứng dụng định danh điện tử trong tiếp dân', 'description' => 'Ứng dụng tài khoản định danh điện tử trong tiếp nhận, giải quyết thủ tục cho công dân.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-07-01 08:00:00', 'end_at' => '2026-08-10 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-08-08 16:00:00'],
                ],
            ],

            // ═══════════════════ THÁNG 7/2026 ═══════════════════
            [
                'name' => 'Kế hoạch tuyên truyền kỷ niệm 79 năm Ngày Thương binh - Liệt sĩ 27/7',
                'summary' => 'Tổ chức các hoạt động tuyên truyền, tri ân nhân kỷ niệm 79 năm Ngày Thương binh - Liệt sĩ (27/7/1947 - 27/7/2026).',
                'issue_date' => '2026-07-06', 'status' => 'issued', 'issued_at' => '2026-07-06 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Xây dựng kế hoạch tuyên truyền kỷ niệm 27/7', 'description' => 'Xây dựng kế hoạch tổng thể và phân công các đơn vị tổ chức hoạt động kỷ niệm 27/7.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-07-06 08:00:00', 'end_at' => '2026-07-12 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'urgent', 'completed_at' => '2026-07-11 15:00:00'],
                    ['unit' => 'DUUBND', 'name' => 'Tổ chức thăm hỏi, tặng quà gia đình chính sách', 'description' => 'Tổ chức các đoàn thăm hỏi, tặng quà thương binh, bệnh binh, thân nhân liệt sĩ trên địa bàn.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-07-13 08:00:00', 'end_at' => '2026-07-26 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-25 17:00:00', 'result_note' => 'Đã thăm hỏi, tặng 214 suất quà cho gia đình chính sách với tổng kinh phí 214 triệu đồng.'],
                    ['unit' => 'CUQS', 'name' => 'Tổ chức lễ dâng hương, thắp nến tri ân tại nghĩa trang liệt sĩ', 'description' => 'Phối hợp tổ chức lễ dâng hương, lễ thắp nến tri ân các anh hùng liệt sĩ.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-07-20 08:00:00', 'end_at' => '2026-07-27 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-27 06:30:00'],
                    ['unit' => 'DUCA', 'name' => 'Bảo đảm an ninh trật tự các hoạt động kỷ niệm', 'description' => 'Bố trí lực lượng bảo đảm an ninh, trật tự, an toàn giao thông tại các điểm tổ chức hoạt động kỷ niệm.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-07-20 08:00:00', 'end_at' => '2026-07-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-07-27 22:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Báo cáo kết quả các hoạt động kỷ niệm 27/7', 'description' => 'Tổng hợp, báo cáo kết quả tổ chức các hoạt động kỷ niệm 27/7 về cấp uỷ cấp trên.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-07-28 08:00:00', 'end_at' => '2026-08-08 17:00:00', 'processing_status' => 'pending_approval', 'completion_percent' => 100, 'priority' => 'medium', 'reported_at' => '2026-08-06 15:00:00', 'result_note' => 'Báo cáo đã tổng hợp đầy đủ số liệu của 6 đơn vị, trình Thường trực Đảng uỷ xem xét trước khi gửi cấp trên.'],
                ],
            ],
            [
                'name' => 'Kế hoạch kiểm tra công tác quản lý đảng viên và thu nộp đảng phí',
                'summary' => 'Kiểm tra công tác quản lý hồ sơ đảng viên, chuyển sinh hoạt đảng và việc thu nộp, sử dụng đảng phí năm 2026.',
                'issue_date' => '2026-07-15', 'status' => 'issued', 'issued_at' => '2026-07-15 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['unit' => 'UBKT', 'name' => 'Ban hành kế hoạch kiểm tra quản lý đảng viên, đảng phí', 'description' => 'Ban hành kế hoạch, đề cương và lịch kiểm tra công tác quản lý đảng viên, thu nộp đảng phí.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-07-15 08:00:00', 'end_at' => '2026-07-24 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-07-23 15:00:00'],
                    ['unit' => 'BXDD', 'name' => 'Rà soát hồ sơ đảng viên và sổ thu nộp đảng phí', 'description' => 'Rà soát hồ sơ 248 đảng viên và đối chiếu sổ thu nộp đảng phí của 24 chi bộ.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-07-27 08:00:00', 'end_at' => '2026-08-25 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 60, 'priority' => 'high', 'progress_note' => 'Đã rà soát xong 15/24 chi bộ, phát hiện 3 trường hợp chậm nộp đảng phí.'],
                    ['unit' => 'UBKT', 'name' => 'Kiểm tra trực tiếp tại 3 chi bộ trực thuộc', 'description' => 'Kiểm tra trực tiếp hồ sơ, sổ sách và làm việc với chi uỷ 3 chi bộ được chọn.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-08-03 08:00:00', 'end_at' => '2026-08-28 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 40, 'priority' => 'medium', 'result_note' => 'Đã kiểm tra xong 1/3 chi bộ, tiếp tục làm việc với 2 chi bộ còn lại trong tháng 8.'],
                    ['unit' => 'DUCA', 'name' => 'Báo cáo tình hình quản lý đảng viên đi nước ngoài', 'description' => 'Rà soát, báo cáo tình hình đảng viên đi nước ngoài và việc chấp hành quy định về quản lý đảng viên.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-07-27 08:00:00', 'end_at' => '2026-08-14 17:00:00', 'processing_status' => 'pending_approval', 'completion_percent' => 100, 'priority' => 'medium', 'reported_at' => '2026-08-12 15:30:00', 'result_note' => 'Báo cáo đã rà soát đầy đủ, không có trường hợp vi phạm quy định về quản lý đảng viên đi nước ngoài.'],
                    ['unit' => 'CUQS', 'name' => 'Đối chiếu số liệu thu nộp đảng phí 7 tháng', 'description' => 'Đối chiếu số liệu thu nộp, trích nộp đảng phí 7 tháng đầu năm 2026 của Chi bộ Quân sự.', 'item_type' => 'Báo cáo định kỳ', 'start_at' => '2026-08-24 08:00:00', 'end_at' => '2026-09-05 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'low'],
                ],
            ],

            // ═══════════════════ THÁNG 8/2026 ═══════════════════
            [
                'name' => 'Chương trình công tác tháng 8/2026 và chuẩn bị kỷ niệm Quốc khánh 02/9',
                'summary' => 'Triển khai nhiệm vụ trọng tâm tháng 8/2026 và chuẩn bị các hoạt động kỷ niệm 81 năm Cách mạng Tháng Tám và Quốc khánh 02/9.',
                'issue_date' => '2026-08-03', 'status' => 'issued', 'issued_at' => '2026-08-03 08:00:00',
                'type' => 'Thường trực Đảng uỷ giao',
                'items' => [
                    ['unit' => 'VPDU', 'name' => 'Xây dựng chương trình công tác tháng 8/2026', 'description' => 'Tổng hợp, dự thảo và ban hành chương trình công tác tháng 8/2026 của Đảng uỷ.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-08-03 08:00:00', 'end_at' => '2026-08-08 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-08-07 15:00:00'],
                    ['unit' => 'VPDU', 'name' => 'Xây dựng kế hoạch tuyên truyền kỷ niệm Cách mạng Tháng Tám và Quốc khánh 02/9', 'description' => 'Xây dựng kế hoạch tuyên truyền, khẩu hiệu và phân công đơn vị thực hiện các hoạt động kỷ niệm.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-08-10 08:00:00', 'end_at' => '2026-08-25 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 70, 'priority' => 'high', 'progress_note' => 'Đã hoàn thành dự thảo kế hoạch, đang lấy ý kiến các đơn vị.'],
                    ['unit' => 'DUCA', 'name' => 'Xây dựng phương án bảo đảm ANTT dịp lễ Quốc khánh 02/9', 'description' => 'Xây dựng phương án bảo đảm an ninh trật tự, phòng chống cháy nổ dịp nghỉ lễ Quốc khánh 02/9.', 'item_type' => 'Soạn thảo văn bản', 'start_at' => '2026-08-10 08:00:00', 'end_at' => '2026-08-28 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 55, 'priority' => 'high', 'progress_note' => 'Đã khảo sát xong địa bàn trọng điểm, đang hoàn thiện phương án bố trí lực lượng.'],
                    ['unit' => 'CUQS', 'name' => 'Tổ chức trực sẵn sàng chiến đấu dịp lễ 02/9', 'description' => 'Xây dựng và triển khai kế hoạch trực sẵn sàng chiến đấu, bảo đảm quân số trong dịp nghỉ lễ.', 'item_type' => 'Kiểm tra, giám sát', 'start_at' => '2026-08-25 08:00:00', 'end_at' => '2026-09-03 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'high'],
                    ['unit' => 'DUUBND', 'name' => 'Trang trí, chỉnh trang đô thị phục vụ lễ kỷ niệm', 'description' => 'Tổ chức trang trí, chỉnh trang đô thị, treo cờ Tổ quốc và pano tuyên truyền phục vụ lễ kỷ niệm.', 'item_type' => 'Tổ chức sự kiện', 'start_at' => '2026-08-20 08:00:00', 'end_at' => '2026-08-31 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'medium'],
                ],
            ],
        ];
    }
}
