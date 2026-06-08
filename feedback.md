Code Review: Các vấn đề cần giải quyết
Module Task Assignment & Scheduling
Ngày đánh giá: 2026-06-07

Phạm vi: app/Modules/TaskAssignment/ và app/Modules/Scheduling/

Ưu tiên CAO
[ISSUE-01] N+1 Query trong statsByItemType và statsByDepartment
File: app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php:392-410, 437-462

Mức độ: Cao — Performance
Mô tả:

Mỗi item type / phòng ban trong danh sách gọi getTimingStats(). Mỗi lần getTimingStats() thực hiện 5 SQL queries riêng biệt. Với 10 phòng ban → 50+ queries chỉ cho timing stats.
Code hiện tại:
return $itemTypes->map(function ($type) use (...) {
    $base = TaskAssignmentItem::where(...);
    return [
        ...
        'timing_stats' => $this->getTimingStats($base), // ← 5 queries mỗi lần gọi
    ];
})->all();

Giải pháp:

Dùng một query GROUP BY với CASE WHEN để tính toàn bộ timing stats trong 1 lần, tương tự pattern đã dùng trong statsByUser().

[ISSUE-02] reject() bỏ qua tham số $note — mất dữ liệu
File: app/Modules/Scheduling/Services/ScheduleService.php:379-385

Mức độ: Cao — Mất dữ liệu
Mô tả:

Controller nhận rejection_note từ FE, truyền vào Service nhưng Service không lưu vào DB. Người dùng điền lý do từ chối nhưng lý do đó không được ghi lại ở đâu.
Code hiện tại:
public function reject(Schedule $schedule, string $note): Schedule
{
    $schedule->update([
        'approval_status' => ApprovalStatus::REJECTED->value,
        // $note KHÔNG được lưu!
    ]);
    return $this->show($schedule->fresh());
}

Giải pháp:
1.	Thêm cột rejection_note (nullable string) vào bảng schedules qua migration.
2.	Lưu $note vào cột đó trong reject().

[ISSUE-03] Module Scheduling không dùng MediaService — thiếu cleanup khi lỗi
File: app/Modules/Scheduling/Services/ScheduleService.php:251, 259, 466

Mức độ: Cao — Data integrity
Mô tả:

Module TaskAssignment dùng MediaService và có cơ chế try/catch + cleanupStoredFiles khi transaction fail. Module Scheduling dùng trực tiếp Storage::disk('public') và $file->storeAs(), không có cleanup mechanism → nếu transaction thất bại sau khi upload file, file vẫn tồn tại trên disk nhưng không có record trong DB.
Code hiện tại:
// upload (không được bảo vệ nếu transaction fail sau đây)
$path = $file->storeAs("schedules/{$yearMonth}", "{$uuid}.{$ext}", 'public');
ScheduleAttachment::create([...]);

// xóa trực tiếp
Storage::disk('public')->delete($att->file_path);

Giải pháp:

Chuyển sang dùng MediaService (giống TaskAssignment), hoặc thêm pattern try/catch với danh sách $storedFiles để cleanup khi transaction fail.

[ISSUE-04] Cross-tenant risk trong bulkDestroy / bulkUpdateStatus
File:
·	app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php:183, 189
·	app/Modules/Scheduling/Services/ScheduleService.php:296, 311
Mức độ: Cao — Security
Mô tả:

TenantModel global scope lý thuyết bảo vệ các query này, nhưng khi dùng whereIn('id', $ids) trực tiếp trên class (không qua instance được route model binding resolve), cần xác minh scope có được áp dụng đúng không. Không nên phụ thuộc hoàn toàn vào implicit scope cho thao tác destructive.
Code hiện tại:
TaskAssignmentItem::whereIn('id', $ids)->delete();
Schedule::whereIn('id', $ids)->delete();

Giải pháp:

Thêm explicit scope organization_id:
TaskAssignmentItem::whereIn('id', $ids)
    ->where('organization_id', getPermissionsTeamId())
    ->delete();


Ưu tiên TRUNG BÌNH
[ISSUE-05] bulkUpdateStatus có logic thừa và query không cần thiết
File: app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php:186-198

Mức độ: Trung bình — Logic bug
Mô tả:

Sau khi query 1 đã update processing_status, query 2 kiểm tra processing_status != done — điều kiện này luôn true vì query 1 vừa set xong. Query 2 hoàn toàn dư thừa.
Code hiện tại:
$data = $this->buildStatusUpdateData($status);
TaskAssignmentItem::whereIn('id', $ids)->update($data); // Query 1

if ($status !== TaskProgressStatusEnum::Done->value) {
    TaskAssignmentItem::whereIn('id', $ids)
        ->whereNotNull('completed_at')
        ->where('processing_status', '!=', TaskProgressStatusEnum::Done->value) // ← luôn true
        ->update(['completed_at' => null]); // Query 2 dư thừa
}

Giải pháp:

Merge logic clear completed_at vào buildStatusUpdateData():
private function buildStatusUpdateData(string $status): array
{
    $data = ['processing_status' => $status];
    if ($status === TaskProgressStatusEnum::Done->value) {
        $data['completion_percent'] = 100;
        $data['completed_at'] = now();
    } else {
        $data['completed_at'] = null; // clear khi không phải done
    }
    return $data;
}


[ISSUE-06] getWeeks() mutate internal Eloquent state — fragile
File: app/Modules/Scheduling/Services/ScheduleService.php:118-128

Mức độ: Trung bình — Code fragility
Mô tả:

Để tránh MySQL strict mode error với DISTINCT + SELECT DATE(date_time), code dùng 2 hack:
1.	Truyền sort_by = '' để bypass logic trong scopeFilter
2.	Gán $query->getQuery()->orders = null để xóa orderBy đã thêm
Đây là workaround phụ thuộc vào internal Eloquent structure, có thể break khi upgrade Laravel.
Code hiện tại:
$filters['sort_by'] = ''; // Hack bypass scopeFilter
...
$query->getQuery()->orders = null; // Mutate internal state

Giải pháp:

Fix scopeFilter trong model Schedule để không tự thêm orderBy mặc định khi sort_by trống, hoặc thêm flag skip_default_order vào filter.

Ưu tiên THẤP
[ISSUE-07] Hard-code role names bằng string tiếng Việt
File: app/Modules/TaskAssignment/Services/TaskAssignmentItemService.php:374

Mức độ: Thấp — Maintainability
Mô tả:

Role names được hard-code inline, bao gồm tên tiếng Việt. Nếu role được đổi tên trong DB thì logic phân quyền stats silently bị broken.
Code hiện tại:
if (! $user->hasAnyRole(['Quản trị', 'Super Admin', 'Admin'])) {

Giải pháp:

Định nghĩa constant hoặc dùng config:
// config/roles.php hoặc class constant
const ADMIN_ROLES = ['Quản trị', 'Super Admin', 'Admin'];

if (! $user->hasAnyRole(self::ADMIN_ROLES)) {


[ISSUE-08] Dead code block trong ScheduleService::update()
File: app/Modules/Scheduling/Services/ScheduleService.php:271-281

Mức độ: Thấp — Code quality
Mô tả:

Block if rỗng chứa chỉ một comment, không có tác dụng gì.
Code hiện tại:
if ($statusVal === ScheduleStatus::PUBLISHED->value) {
    // Observer tự động fire SchedulePublished hoặc ScheduleUpdated
}

Giải pháp:

Xóa toàn bộ block if này.

[ISSUE-09] Sort order race condition khi insert lịch mới
File: app/Modules/Scheduling/Services/ScheduleService.php:181-191

Mức độ: Thấp — Edge case
Mô tả:

Khi 2 user cùng insert lịch vào cùng 1 slot (cùng ngày + session + sort_order), cả 2 đều increment và tạo record với sort_order trùng nhau.
Code hiện tại:
Schedule::where(...)->where('sort_order', '>=', $data['sort_order'])->increment('sort_order');
$schedule = Schedule::create($data);

Giải pháp:

Thêm lockForUpdate() hoặc dùng SELECT MAX(sort_order) + 1 thay vì dựa vào giá trị FE gửi lên.

[ISSUE-10] File upload nhận từ 2 key (files và attachments) — ambiguous
File: app/Modules/Scheduling/Controllers/ScheduleController.php:192-195, 229-232

Mức độ: Thấp — UX / API consistency
Mô tả:

Controller merge cả files và attachments từ request. Nếu FE vô tình gửi cả hai key, file sẽ bị upload đôi.
Code hiện tại:
$files = array_merge(
    is_array($request->file('files')) ? $request->file('files') : [...],
    is_array($request->file('attachments')) ? $request->file('attachments') : [...]
);

Giải pháp:

Chọn 1 key duy nhất (khuyến nghị attachments cho đồng nhất với TaskAssignment) và bỏ key còn lại. Cập nhật FE tương ứng.

Checklist xử lý
ID	Vấn đề	Mức độ	Trạng thái
ISSUE-01	N+1 Query trong stats by department/itemType	Cao	[ ] Chưa xử lý (để PR riêng)
ISSUE-02	reject() bỏ qua $note	Cao	[x] Đã xử lý
ISSUE-03	Scheduling bypass MediaService, thiếu cleanup	Cao	[x] Đã xử lý
ISSUE-04	Cross-tenant risk trong bulk ops	Cao	[x] Đã xử lý
ISSUE-05	bulkUpdateStatus logic thừa	Trung bình	[x] Đã xử lý
ISSUE-06	getWeeks() mutate internal Eloquent state	Trung bình	[x] Đã xử lý
ISSUE-07	Hard-code role names bằng string	Thấp	[x] Đã xử lý
ISSUE-08	Dead code block trong update()	Thấp	[x] Đã xử lý
ISSUE-09	Sort order race condition khi insert	Thấp	[x] Đã xử lý
ISSUE-10	File upload 2 key ambiguous	Thấp	[x] Đã xử lý

