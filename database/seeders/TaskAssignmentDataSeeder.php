<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDepartment;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemReport;
use App\Modules\TaskAssignment\Models\TaskAssignmentItemType;
use App\Modules\TaskAssignment\Models\TaskAssignmentType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed dữ liệu mẫu đầy đủ cho module TaskAssignment.
 *
 * - 7 phòng ban (gán user vào phòng)
 * - 3 loại văn bản, 5 loại đầu việc
 * - 12 văn bản trải 3 tháng (02–04/2026)
 * - ~85 đầu việc phủ đều trạng thái
 * - Báo cáo cho việc hoàn thành
 * - created_at/updated_at khớp timeline thực tế
 * - created_by/updated_by phân bổ đa dạng theo phòng ban
 */
class TaskAssignmentDataSeeder extends Seeder
{
    /** Cache user IDs theo department. */
    private $usersByDept = [];
    private array $deptIds = [];
    private array $typeIds = [];
    private array $itemTypeIds = [];
    private array $authorizedUserIds = [];
    private array $categoryCreatorUserIds = [];

    public function run(): void
    {
        // Đăng nhập tạm để model booted() không ghi đè created_by/updated_by = null
        auth()->login(User::first());

        $this->seedDepartments();
        $this->seedTypes();
        $this->seedItemTypes();
        $this->assignUsersToDepartments();
        $this->seedDocumentsAndItems();

        auth()->logout();
    }

    // ─── Phòng ban ───────────────────────────────────────────────

    protected function seedDepartments(): void
    {
        $now = Carbon::parse('2026-01-15 08:30:00');
        $creatorId = $this->getCategoryCreatorId();
        $departments = [
            ['code' => 'TTBCXB', 'name' => 'Phòng Tuyên truyền, Báo chí - Xuất bản', 'sort_order' => 1],
            ['code' => 'TTTH',   'name' => 'Phòng Thông tin - Tổng hợp', 'sort_order' => 2],
            ['code' => 'DVDTTG', 'name' => 'Phòng Dân vận các CQNN, dân tộc, tôn giáo', 'sort_order' => 3],
            ['code' => 'VP',     'name' => 'Văn phòng', 'sort_order' => 4],
            ['code' => 'DTCH',   'name' => 'Phòng đoàn thể và các hội', 'sort_order' => 5],
            ['code' => 'LLCTLSD','name' => 'Phòng Lý luận chính trị, lịch sử Đảng', 'sort_order' => 6],
            ['code' => 'KGVHVN', 'name' => 'Phòng Khoa giáo, Văn hoá - Văn nghệ', 'sort_order' => 7],
        ];

        foreach ($departments as $i => $dept) {
            $ts = $now->copy()->addMinutes($i * 5);
            $record = TaskAssignmentDepartment::unguarded(fn () =>
                TaskAssignmentDepartment::firstOrCreate(
                    ['code' => $dept['code']],
                    ['name' => $dept['name'], 'status' => 'active', 'sort_order' => $dept['sort_order']]
                )
            );
            DB::table('task_assignment_departments')->where('id', $record->id)->update([
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $ts, 'updated_at' => $ts,
            ]);
        }
    }

    // ─── Loại văn bản ────────────────────────────────────────────

    protected function seedTypes(): void
    {
        $ts = Carbon::parse('2026-01-15 09:00:00');
        $creatorId = $this->getCategoryCreatorId();

        foreach (['Thường trực Thành ủy giao', 'Công việc chuyên môn', 'Công việc phát sinh'] as $i => $name) {
            $t = $ts->copy()->addMinutes($i * 3);
            $record = TaskAssignmentType::unguarded(fn () =>
                TaskAssignmentType::firstOrCreate(['name' => $name], ['status' => 'active'])
            );
            DB::table('task_assignment_types')->where('id', $record->id)->update([
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
            $record = TaskAssignmentItemType::unguarded(fn () =>
                TaskAssignmentItemType::firstOrCreate(['name' => $name], ['status' => 'active'])
            );
            DB::table('task_assignment_item_types')->where('id', $record->id)->update([
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $t, 'updated_at' => $t,
            ]);
        }
    }

    // ─── Gán user vào phòng ban ──────────────────────────────────

    protected function assignUsersToDepartments(): void
    {
        $this->deptIds = TaskAssignmentDepartment::orderBy('sort_order')->pluck('id', 'code')->all();
        $users = User::orderBy('id')->get();

        $deptCodes = array_keys($this->deptIds);
        foreach ($users as $i => $user) {
            $code = $deptCodes[$i % count($deptCodes)];
            $user->update(['task_assignment_department_id' => $this->deptIds[$code]]);
        }

        $this->usersByDept = User::whereNotNull('task_assignment_department_id')
            ->get()->groupBy('task_assignment_department_id');

        $this->typeIds = TaskAssignmentType::pluck('id', 'name')->all();
        $this->itemTypeIds = TaskAssignmentItemType::pluck('id', 'name')->all();
    }

    // ─── Văn bản & đầu việc ─────────────────────────────────────

    protected function seedDocumentsAndItems(): void
    {
        TaskAssignmentItemReport::query()->delete();
        TaskAssignmentItem::query()->delete();
        TaskAssignmentDocument::query()->delete();

        foreach ($this->getDocumentData() as $docData) {
            $createdAt = Carbon::parse($docData['issue_date'])->subDays(rand(1, 3))->setTime(rand(7, 9), rand(0, 59));
            $updatedAt = $docData['issued_at'] ? Carbon::parse($docData['issued_at']) : $createdAt->copy()->addHours(rand(1, 4));
            $creatorId = $this->getAuthorizedUserId();

            $doc = TaskAssignmentDocument::unguarded(fn () =>
                TaskAssignmentDocument::create([
                    'name' => $docData['name'],
                    'summary' => $docData['summary'],
                    'issue_date' => $docData['issue_date'],
                    'status' => $docData['status'],
                    'issued_at' => $docData['issued_at'],
                    'task_assignment_type_id' => $this->typeIds[$docData['type']],
                    'created_by' => $creatorId,
                    'updated_by' => $creatorId,
                ])
            );
            // Ghi đè timestamp đúng (booted hook set auth()->id() và Eloquent set now())
            DB::table('task_assignment_documents')->where('id', $doc->id)->update([
                'created_by' => $creatorId, 'updated_by' => $creatorId,
                'created_at' => $createdAt, 'updated_at' => $updatedAt,
            ]);

            foreach ($docData['items'] as $itemData) {
                $itemCreatedAt = Carbon::parse($itemData['start_at'])->subDays(rand(0, 2))->setTime(rand(7, 9), rand(0, 59));
                $itemUpdatedAt = ($itemData['completed_at'] ?? null)
                    ? Carbon::parse($itemData['completed_at'])
                    : $itemCreatedAt->copy()->addDays(rand(1, 5))->setTime(rand(8, 17), rand(0, 59));
                $itemCreatorId = $this->getAuthorizedUserId();
                $itemEditorId = $this->getAuthorizedUserId();

                $item = TaskAssignmentItem::unguarded(fn () =>
                    TaskAssignmentItem::create([
                        'task_assignment_document_id' => $doc->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'task_assignment_item_type_id' => $this->itemTypeIds[$itemData['item_type']],
                        'deadline_type' => $itemData['deadline_type'],
                        'start_at' => $itemData['start_at'],
                        'end_at' => $itemData['end_at'],
                        'processing_status' => $itemData['processing_status'],
                        'completion_percent' => $itemData['completion_percent'],
                        'priority' => $itemData['priority'],
                        'completed_at' => $itemData['completed_at'] ?? null,
                    ])
                );
                DB::table('task_assignment_items')->where('id', $item->id)->update([
                    'created_by' => $itemCreatorId, 'updated_by' => $itemEditorId,
                    'created_at' => $itemCreatedAt, 'updated_at' => $itemUpdatedAt,
                ]);

                // Gán phòng ban
                foreach ($itemData['departments'] as $deptCode => $role) {
                    if (isset($this->deptIds[$deptCode])) {
                        $item->departments()->attach($this->deptIds[$deptCode], ['role' => $role]);
                    }
                }

                // Gán user
                $mainDeptCode = array_key_first($itemData['departments']);
                $mainDeptId = $this->deptIds[$mainDeptCode] ?? null;
                if ($mainDeptId && isset($this->usersByDept[$mainDeptId])) {
                    $deptUsers = $this->usersByDept[$mainDeptId]->values();
                    foreach ($deptUsers as $idx => $user) {
                        $item->users()->attach($user->id, [
                            'department_id' => $mainDeptId,
                            'assignment_role' => $idx === 0 ? 'main' : 'support',
                            'assignment_status' => $this->mapAssignmentStatus($itemData['processing_status']),
                            'assigned_at' => $itemData['start_at'],
                            'accepted_at' => in_array($itemData['processing_status'], ['in_progress', 'done']) ? $itemData['start_at'] : null,
                            'completed_at' => $itemData['processing_status'] === 'done' ? $itemData['completed_at'] : null,
                        ]);
                    }
                }

                // Báo cáo cho việc hoàn thành
                if ($itemData['processing_status'] === 'done' && $mainDeptId && isset($this->usersByDept[$mainDeptId])) {
                    $reporter = $this->usersByDept[$mainDeptId]->first();
                    $reportCreatedAt = Carbon::parse($itemData['completed_at'])->addHours(rand(1, 8));
                    TaskAssignmentItemReport::unguarded(fn () =>
                        TaskAssignmentItemReport::create([
                            'task_assignment_item_id' => $item->id,
                            'reporter_user_id' => $reporter->id,
                            'completed_at' => $itemData['completed_at'],
                            'report_document_number' => $this->fakeDocumentNumber($docData['issue_date']),
                            'report_document_excerpt' => 'Báo cáo kết quả thực hiện: '.$itemData['name'],
                            'report_document_content' => 'Đã hoàn thành công việc theo đúng kế hoạch được giao.',
                            'created_at' => $reportCreatedAt,
                            'updated_at' => $reportCreatedAt,
                        ])
                    );
                }
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────

    /** Lấy ngẫu nhiên user có quyền tạo danh mục (Admin, Quản trị). */
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

    /** Lấy ngẫu nhiên user có quyền tạo văn bản/công việc (Admin, Trưởng phòng). */
    private function getAuthorizedUserId(): int
    {
        if (empty($this->authorizedUserIds)) {
            $this->authorizedUserIds = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['Super Admin', 'Admin', 'Trưởng phòng']);
            })->pluck('id')->toArray();

            if (empty($this->authorizedUserIds)) {
                $this->authorizedUserIds = User::whereIn('user_name', ['admin', 'truongphong'])->pluck('id')->toArray();
            }
            if (empty($this->authorizedUserIds)) {
                $this->authorizedUserIds = [User::first()->id];
            }
        }

        return $this->authorizedUserIds[array_rand($this->authorizedUserIds)];
    }

    private function mapAssignmentStatus(string $processingStatus): string
    {
        return match ($processingStatus) {
            'done' => 'done',
            'in_progress' => 'accepted',
            'cancelled' => 'rejected',
            default => 'assigned',
        };
    }

    private function fakeDocumentNumber(string $issueDate): string
    {
        return rand(100, 999).'-BC/BTGTU-'.date('Y', strtotime($issueDate));
    }

    /**
     * 12 văn bản, ~85 đầu việc, trải 3 tháng 02–04/2026.
     * Phân bổ: done, in_progress, todo, overdue, paused, cancelled.
     */
    private function getDocumentData(): array
    {
        return [
            // ═══════════════════ THÁNG 2/2026 ═══════════════════
            [
                'name' => 'Kế hoạch tuyên truyền kỷ niệm 96 năm ngày thành lập Đảng',
                'summary' => 'Triển khai kế hoạch tuyên truyền kỷ niệm 96 năm ngày thành lập Đảng Cộng sản Việt Nam (03/02/1930 - 03/02/2026).',
                'issue_date' => '2026-02-01', 'status' => 'issued', 'issued_at' => '2026-02-01 08:00:00',
                'type' => 'Thường trực Thành ủy giao',
                'items' => [
                    ['name' => 'Soạn đề cương tuyên truyền kỷ niệm 96 năm thành lập Đảng', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-01 08:00:00', 'end_at' => '2026-02-10 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-02-09 15:00:00', 'departments' => ['TTBCXB' => 'main']],
                    ['name' => 'Tổ chức hội nghị báo cáo viên tháng 2', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-05 08:00:00', 'end_at' => '2026-02-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-02-18 16:30:00', 'departments' => ['TTBCXB' => 'main', 'VP' => 'supporting']],
                    ['name' => 'Kiểm tra công tác tuyên truyền tại quận Hải Châu', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-10 08:00:00', 'end_at' => '2026-02-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-02-24 11:00:00', 'departments' => ['TTBCXB' => 'main', 'TTTH' => 'supporting']],
                    ['name' => 'Báo cáo tổng kết công tác tuyên truyền tháng 2', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-20 08:00:00', 'end_at' => '2026-02-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-02-27 14:00:00', 'departments' => ['TTBCXB' => 'main']],
                ],
            ],
            [
                'name' => 'Chương trình công tác dân vận tháng 2/2026',
                'summary' => 'Triển khai chương trình dân vận tháng 2, tập trung công tác dân tộc, tôn giáo.',
                'issue_date' => '2026-02-03', 'status' => 'issued', 'issued_at' => '2026-02-03 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Khảo sát tình hình tôn giáo tại quận Sơn Trà', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-05 08:00:00', 'end_at' => '2026-02-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-02-19 16:00:00', 'departments' => ['DVDTTG' => 'main']],
                    ['name' => 'Soạn báo cáo tình hình dân tộc, tôn giáo quý I', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-10 08:00:00', 'end_at' => '2026-02-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-02-26 10:00:00', 'departments' => ['DVDTTG' => 'main']],
                    ['name' => 'Tổ chức gặp mặt chức sắc tôn giáo nhân dịp Tết', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-05 08:00:00', 'end_at' => '2026-02-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-02-14 17:00:00', 'departments' => ['DVDTTG' => 'main', 'VP' => 'supporting']],
                    ['name' => 'Giám sát thực hiện QCDC ở cơ sở tại huyện Hòa Vang', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-15 08:00:00', 'end_at' => '2026-02-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'low', 'completed_at' => '2026-03-01 09:00:00', 'departments' => ['DVDTTG' => 'main', 'DTCH' => 'supporting']],
                ],
            ],
            [
                'name' => 'Kế hoạch đào tạo bồi dưỡng lý luận chính trị năm 2026',
                'summary' => 'Xây dựng kế hoạch tổng thể đào tạo, bồi dưỡng lý luận chính trị cho cán bộ, đảng viên.',
                'issue_date' => '2026-02-10', 'status' => 'issued', 'issued_at' => '2026-02-10 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Tổng hợp nhu cầu đào tạo từ các quận huyện', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-10 08:00:00', 'end_at' => '2026-02-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-02-23 14:00:00', 'departments' => ['LLCTLSD' => 'main']],
                    ['name' => 'Soạn kế hoạch đào tạo LLCT năm 2026', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-15 08:00:00', 'end_at' => '2026-03-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-04 16:00:00', 'departments' => ['LLCTLSD' => 'main']],
                    ['name' => 'Phối hợp Trường Chính trị mở lớp Trung cấp LLCT', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-02-20 08:00:00', 'end_at' => '2026-03-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-12 10:00:00', 'departments' => ['LLCTLSD' => 'main', 'VP' => 'supporting']],
                ],
            ],

            // ═══════════════════ THÁNG 3/2026 ═══════════════════
            [
                'name' => 'Kế hoạch tuyên truyền Đại hội Đảng bộ thành phố lần thứ XVIII',
                'summary' => 'Triển khai kế hoạch tuyên truyền trước, trong và sau Đại hội Đảng bộ thành phố.',
                'issue_date' => '2026-03-01', 'status' => 'issued', 'issued_at' => '2026-03-01 08:00:00',
                'type' => 'Thường trực Thành ủy giao',
                'items' => [
                    ['name' => 'Soạn thảo đề cương tuyên truyền Đại hội lần thứ XVIII', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-01 08:00:00', 'end_at' => '2026-03-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-18 15:00:00', 'departments' => ['TTBCXB' => 'main', 'TTTH' => 'supporting']],
                    ['name' => 'Tổ chức hội nghị báo cáo viên tuyên truyền Đại hội', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-15 08:00:00', 'end_at' => '2026-03-31 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-28 17:00:00', 'departments' => ['TTBCXB' => 'main', 'VP' => 'supporting']],
                    ['name' => 'Kiểm tra công tác trang trí, khánh tiết Đại hội', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-20 08:00:00', 'end_at' => '2026-04-01 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 70, 'priority' => 'high', 'departments' => ['TTBCXB' => 'main', 'KGVHVN' => 'supporting']],
                    ['name' => 'Theo dõi dư luận xã hội trước Đại hội', 'description' => 'Nắm bắt, tổng hợp dư luận xã hội về Đại hội Đảng bộ TP.', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'no_deadline', 'start_at' => '2026-03-01 08:00:00', 'end_at' => null, 'processing_status' => 'in_progress', 'completion_percent' => 50, 'priority' => 'medium', 'departments' => ['TTTH' => 'main']],
                ],
            ],
            [
                'name' => 'Chương trình công tác đoàn thể quý I/2026',
                'summary' => 'Triển khai công tác đoàn thể, hội quần chúng quý I năm 2026.',
                'issue_date' => '2026-03-05', 'status' => 'issued', 'issued_at' => '2026-03-05 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Tổ chức hội nghị sơ kết phong trào thi đua yêu nước', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-05 08:00:00', 'end_at' => '2026-03-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-19 16:00:00', 'departments' => ['DTCH' => 'main']],
                    ['name' => 'Khảo sát hoạt động của Hội Liên hiệp Phụ nữ cơ sở', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-10 08:00:00', 'end_at' => '2026-03-25 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-24 10:00:00', 'departments' => ['DTCH' => 'main']],
                    ['name' => 'Soạn báo cáo công tác đoàn thể quý I', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-20 08:00:00', 'end_at' => '2026-03-31 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-30 14:00:00', 'departments' => ['DTCH' => 'main']],
                    ['name' => 'Kiểm tra việc thực hiện quy chế dân chủ ở cơ sở', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-10 08:00:00', 'end_at' => '2026-03-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'low', 'completed_at' => '2026-03-30 09:00:00', 'departments' => ['DTCH' => 'main', 'DVDTTG' => 'supporting']],
                    ['name' => 'Hướng dẫn tổ chức Đại hội Hội Nông dân cấp cơ sở', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-15 08:00:00', 'end_at' => '2026-04-02 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 60, 'priority' => 'medium', 'departments' => ['DTCH' => 'main']],
                    ['name' => 'Theo dõi hoạt động các hội quần chúng', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'no_deadline', 'start_at' => '2026-03-05 08:00:00', 'end_at' => null, 'processing_status' => 'in_progress', 'completion_percent' => 40, 'priority' => 'low', 'departments' => ['DTCH' => 'main']],
                ],
            ],
            [
                'name' => 'Kế hoạch công tác khoa giáo tháng 3/2026',
                'summary' => 'Triển khai các nhiệm vụ khoa giáo, giáo dục, y tế, thể dục thể thao tháng 3.',
                'issue_date' => '2026-03-03', 'status' => 'issued', 'issued_at' => '2026-03-03 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Kiểm tra công tác phòng chống dịch bệnh mùa hè', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-05 08:00:00', 'end_at' => '2026-03-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-19 15:00:00', 'departments' => ['KGVHVN' => 'main']],
                    ['name' => 'Soạn kế hoạch tổ chức Ngày sách và Văn hóa đọc', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-05 08:00:00', 'end_at' => '2026-03-15 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-14 16:00:00', 'departments' => ['KGVHVN' => 'main', 'TTBCXB' => 'supporting']],
                    ['name' => 'Tổ chức Ngày sách và Văn hóa đọc Việt Nam (21/4)', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-15 08:00:00', 'end_at' => '2026-04-21 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 55, 'priority' => 'high', 'departments' => ['KGVHVN' => 'main', 'VP' => 'supporting']],
                    ['name' => 'Nghiên cứu mô hình giáo dục STEM tại các trường', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-10 08:00:00', 'end_at' => '2026-04-01 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 35, 'priority' => 'low', 'departments' => ['KGVHVN' => 'main']],
                    ['name' => 'Báo cáo công tác khoa giáo tháng 3', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-25 08:00:00', 'end_at' => '2026-03-31 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-30 11:00:00', 'departments' => ['KGVHVN' => 'main']],
                    ['name' => 'Theo dõi tình hình giáo dục, y tế trên địa bàn', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'no_deadline', 'start_at' => '2026-03-03 08:00:00', 'end_at' => null, 'processing_status' => 'in_progress', 'completion_percent' => 30, 'priority' => 'low', 'departments' => ['KGVHVN' => 'main']],
                ],
            ],
            [
                'name' => 'Xử lý phản ánh tình hình tư tưởng cán bộ tại cơ sở',
                'summary' => 'Tiếp nhận và xử lý thông tin phản ánh liên quan đến tư tưởng cán bộ tháng 3.',
                'issue_date' => '2026-03-10', 'status' => 'issued', 'issued_at' => '2026-03-10 08:00:00',
                'type' => 'Công việc phát sinh',
                'items' => [
                    ['name' => 'Tổng hợp thông tin phản ánh từ các quận huyện tháng 3', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-10 08:00:00', 'end_at' => '2026-03-20 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-19 15:00:00', 'departments' => ['TTTH' => 'main', 'DTCH' => 'supporting']],
                    ['name' => 'Xác minh phản ánh tại quận Thanh Khê', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-12 08:00:00', 'end_at' => '2026-03-22 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-03-21 14:00:00', 'departments' => ['TTTH' => 'main']],
                    ['name' => 'Xác minh phản ánh tại quận Liên Chiểu', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-15 08:00:00', 'end_at' => '2026-03-28 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'medium', 'completed_at' => '2026-03-29 10:00:00', 'departments' => ['TTTH' => 'main']],
                ],
            ],

            // ═══════════════════ THÁNG 4/2026 ═══════════════════
            [
                'name' => 'Đề án Văn hóa - Văn nghệ phục vụ kỷ niệm ngày 30/4',
                'summary' => 'Xây dựng và triển khai đề án tổ chức các hoạt động văn hóa văn nghệ chào mừng ngày 30/4.',
                'issue_date' => '2026-04-01', 'status' => 'issued', 'issued_at' => '2026-04-01 08:00:00',
                'type' => 'Thường trực Thành ủy giao',
                'items' => [
                    ['name' => 'Lên kịch bản chương trình văn nghệ chào mừng 30/4', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-01 08:00:00', 'end_at' => '2026-04-15 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 45, 'priority' => 'high', 'departments' => ['KGVHVN' => 'main']],
                    ['name' => 'Phối hợp tổ chức đêm biểu diễn văn nghệ', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-15 08:00:00', 'end_at' => '2026-04-29 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'high', 'departments' => ['KGVHVN' => 'main', 'VP' => 'supporting', 'TTBCXB' => 'supporting']],
                    ['name' => 'Tuyên truyền trên báo chí về các hoạt động 30/4', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-10 08:00:00', 'end_at' => '2026-04-28 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'medium', 'departments' => ['TTBCXB' => 'main']],
                    ['name' => 'Kiểm tra công tác chuẩn bị lễ kỷ niệm 30/4', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-20 08:00:00', 'end_at' => '2026-04-28 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'medium', 'departments' => ['VP' => 'main', 'KGVHVN' => 'supporting']],
                ],
            ],
            [
                'name' => 'Chương trình công tác tháng 4/2026 của Văn phòng',
                'summary' => 'Triển khai nhiệm vụ hành chính, hậu cần, tổng hợp tháng 4 của Văn phòng Ban.',
                'issue_date' => '2026-04-01', 'status' => 'issued', 'issued_at' => '2026-04-01 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Chuẩn bị nội dung cuộc họp giao ban tháng 4', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-01 08:00:00', 'end_at' => '2026-04-03 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-04-03 11:00:00', 'departments' => ['VP' => 'main']],
                    ['name' => 'Tổng hợp chương trình công tác tháng 5/2026 từ các phòng', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-15 08:00:00', 'end_at' => '2026-04-25 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'medium', 'departments' => ['VP' => 'main']],
                    ['name' => 'Kiểm tra cơ sở vật chất, trang thiết bị làm việc', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-25 08:00:00', 'end_at' => '2026-04-03 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 30, 'priority' => 'low', 'departments' => ['VP' => 'main']],
                    ['name' => 'Quản lý tài sản, văn phòng phẩm', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'no_deadline', 'start_at' => '2026-04-01 08:00:00', 'end_at' => null, 'processing_status' => 'in_progress', 'completion_percent' => 20, 'priority' => 'low', 'departments' => ['VP' => 'main']],
                    ['name' => 'Xử lý công văn đến/đi tháng 4', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'no_deadline', 'start_at' => '2026-04-01 08:00:00', 'end_at' => null, 'processing_status' => 'in_progress', 'completion_percent' => 25, 'priority' => 'medium', 'departments' => ['VP' => 'main']],
                ],
            ],
            [
                'name' => 'Kế hoạch nghiên cứu lịch sử Đảng bộ thành phố giai đoạn 2020-2025',
                'summary' => 'Nghiên cứu, biên soạn lịch sử Đảng bộ thành phố giai đoạn 2020-2025.',
                'issue_date' => '2026-04-02', 'status' => 'issued', 'issued_at' => '2026-04-02 08:00:00',
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Thu thập tư liệu lịch sử Đảng bộ TP giai đoạn 2020-2025', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-02 08:00:00', 'end_at' => '2026-05-30 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 10, 'priority' => 'medium', 'departments' => ['LLCTLSD' => 'main']],
                    ['name' => 'Soạn đề cương biên soạn lịch sử Đảng bộ TP', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-20 08:00:00', 'end_at' => '2026-04-02 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 40, 'priority' => 'high', 'departments' => ['LLCTLSD' => 'main']],
                    ['name' => 'Tổ chức hội thảo khoa học Lý luận chính trị năm 2026', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-15 08:00:00', 'end_at' => '2026-05-15 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'high', 'departments' => ['LLCTLSD' => 'main', 'VP' => 'supporting']],
                    ['name' => 'Tổ chức lớp bồi dưỡng chuyên đề cho cán bộ nguồn', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-10 08:00:00', 'end_at' => '2026-04-30 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 20, 'priority' => 'medium', 'departments' => ['LLCTLSD' => 'main']],
                ],
            ],
            [
                'name' => 'Xử lý vấn đề phát sinh trên mạng xã hội tháng 4',
                'summary' => 'Theo dõi, xử lý các thông tin sai lệch, xuyên tạc trên mạng xã hội.',
                'issue_date' => '2026-04-03', 'status' => 'issued', 'issued_at' => '2026-04-03 08:00:00',
                'type' => 'Công việc phát sinh',
                'items' => [
                    ['name' => 'Rà soát các trang mạng xã hội có nội dung xuyên tạc', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-28 08:00:00', 'end_at' => '2026-04-03 17:00:00', 'processing_status' => 'in_progress', 'completion_percent' => 50, 'priority' => 'high', 'departments' => ['TTBCXB' => 'main', 'TTTH' => 'supporting']],
                    ['name' => 'Soạn bài viết phản bác thông tin sai lệch', 'item_type' => 'Soạn thảo văn bản', 'deadline_type' => 'has_deadline', 'start_at' => '2026-03-30 08:00:00', 'end_at' => '2026-04-03 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'high', 'departments' => ['TTBCXB' => 'main']],
                    ['name' => 'Báo cáo nhanh tình hình thông tin mạng xã hội', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-03 08:00:00', 'end_at' => '2026-04-05 17:00:00', 'processing_status' => 'done', 'completion_percent' => 100, 'priority' => 'high', 'completed_at' => '2026-04-04 16:00:00', 'departments' => ['TTTH' => 'main']],
                ],
            ],
            [
                'name' => 'Chương trình công tác dân vận tháng 4/2026',
                'summary' => 'Triển khai nhiệm vụ dân vận, dân tộc, tôn giáo tháng 4.',
                'issue_date' => '2026-04-01', 'status' => 'draft', 'issued_at' => null,
                'type' => 'Công việc chuyên môn',
                'items' => [
                    ['name' => 'Kiểm tra công tác dân tộc tại huyện Hòa Vang', 'item_type' => 'Kiểm tra, giám sát', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-10 08:00:00', 'end_at' => '2026-04-20 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'medium', 'departments' => ['DVDTTG' => 'main']],
                    ['name' => 'Tổ chức gặp mặt đồng bào dân tộc thiểu số', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-15 08:00:00', 'end_at' => '2026-04-25 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'medium', 'departments' => ['DVDTTG' => 'main', 'VP' => 'supporting']],
                    ['name' => 'Soạn báo cáo tình hình dân tộc, tôn giáo tháng 4', 'item_type' => 'Báo cáo định kỳ', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-25 08:00:00', 'end_at' => '2026-04-30 17:00:00', 'processing_status' => 'todo', 'completion_percent' => 0, 'priority' => 'low', 'departments' => ['DVDTTG' => 'main']],
                    ['name' => 'Theo dõi tình hình an ninh tôn giáo', 'item_type' => 'Nghiên cứu, khảo sát', 'deadline_type' => 'no_deadline', 'start_at' => '2026-04-01 08:00:00', 'end_at' => null, 'processing_status' => 'paused', 'completion_percent' => 15, 'priority' => 'low', 'departments' => ['DVDTTG' => 'main']],
                    ['name' => 'Hội nghị tổng kết 10 năm Chỉ thị 18-CT/TW (đã hủy)', 'item_type' => 'Tổ chức sự kiện', 'deadline_type' => 'has_deadline', 'start_at' => '2026-04-20 08:00:00', 'end_at' => '2026-04-30 17:00:00', 'processing_status' => 'cancelled', 'completion_percent' => 0, 'priority' => 'medium', 'departments' => ['DVDTTG' => 'main', 'DTCH' => 'supporting']],
                ],
            ],
        ];
    }
}
