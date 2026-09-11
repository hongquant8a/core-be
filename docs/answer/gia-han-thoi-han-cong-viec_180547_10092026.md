# Xin gia hạn thời hạn công việc — phân tích và thiết kế

> Ngày tạo: 18:05:47 10/09/2026  
> Cập nhật lần cuối: 18:05:47 10/09/2026

Yêu cầu: người thực hiện xin gia hạn thời hạn công việc kèm lý do; chỉ người có
quyền hoặc người quản lý công việc duyệt thì hạn mới đổi; lưu lại lịch sử xin
gia hạn và lý do.

Tài liệu này là **thiết kế, chưa triển khai**. Mục 10 liệt kê những chỗ cần chốt
trước khi viết code.

---

## 1. Hiện trạng

**Chưa có khái niệm gia hạn.** Tìm toàn module không có extension/gia hạn nào.

**Ai đổi được `end_at` hiện nay:** chỉ qua `PUT /api/task-assignment-items/{item}`,
gác `can:update` → policy đòi quyền `task-assignment-documents.updateItem` **và**
là người liên quan. Trong năm vai trò, chỉ **Quản lý công việc** (và Super Admin)
có quyền đó. Nhân viên, Trưởng phòng, Lãnh đạo không sửa được hạn bằng bất kỳ
đường nào.

Luật kiểm tra hiện tại rất lỏng: `'end_at' => 'nullable|date|after_or_equal:start_at'`
— không chặn lùi hạn, không so với hiện tại, không cấm sửa khi việc đã hoàn thành.
Thay đổi hạn **không để lại dấu vết** nào: dòng thời gian chỉ gom ghi chú và
điều chuyển.

**Hệ quả:** người thực hiện gặp vướng chỉ còn cách nhắn ghi chú hoặc gọi điện,
rồi quản lý tự vào sửa hạn. Không ai biết đã gia hạn mấy lần, vì lý do gì.

## 2. Ba điểm tựa có sẵn

Hệ thống đã có đúng khuôn mẫu cho tính năng này, không phải phát minh gì mới.

**Khuôn duyệt** — quy tắc xuyên suốt module là *"chỉ người đã giao việc mới duyệt
được việc đó"*. Policy `markDone` thể hiện đúng thế: kiểm quyền
`my-assigned-tasks.markDone`, rồi đối chiếu `assigned_by === user->id`. Riêng
`before()` cho `task-overview.manageAll` (quản trị hệ thống) đi tắt mọi kiểm tra
sở hữu — đây chính là lối thoát khi người giao việc nghỉ dài ngày.

**Khuôn bảng lịch sử** — `task_assignment_item_user_transfers` là bảng một-nhiều
gắn với công việc, có đủ model/service/controller/request/resource/route và một
endpoint lịch sử phân trang. Bảng gia hạn sao chép đúng khuôn đó.

**Khuôn dòng thời gian** — `TaskAssignmentTimelineService` gom ghi chú và điều
chuyển thành một dòng thời gian với shape `{type, id, timestamp, actor, data}`;
FE render theo `entry.type`. Thêm loại `extension` là một khối nhỏ ở service và
một nhánh `v-else-if` ở component.

## 3. Bảng mới `task_assignment_item_extensions`

| Cột | Kiểu | Ghi chú |
|---|---|---|
| `id` | bigint unsigned | PK |
| `task_assignment_item_id` | FK cascade | công việc |
| `requested_by_user_id` | FK nullOnDelete | người xin |
| `current_end_at` | dateTime | **hạn tại thời điểm xin** |
| `requested_end_at` | dateTime | hạn xin dời tới |
| `reason` | text | lý do xin — bắt buộc |
| `status` | varchar(20) | `pending` · `approved` · `rejected` · `cancelled` |
| `reviewed_by_user_id` | FK nullOnDelete | người duyệt |
| `reviewed_at` | dateTime nullable | |
| `review_note` | text nullable | lý do từ chối / ghi chú khi duyệt |
| `organization_id` | FK | tenant |
| `created_at` / `updated_at` | timestamps | |

Index: `[task_assignment_item_id, status]` và `[status, created_at]`.

**Vì sao phải chụp `current_end_at`:** sau khi duyệt, `items.end_at` đổi. Không
lưu hạn cũ thì dòng lịch sử mất nghĩa — đọc "xin dời tới 20/01" mà không biết dời
từ đâu.

Đặt tên khoá ngoại thủ công (`fk_ta_extensions_*`): tên Laravel tự sinh cho bảng
này vượt giới hạn 64 ký tự của MySQL, đúng vấn đề bảng điều chuyển đã gặp.

## 4. Ràng buộc nghiệp vụ

| Ràng buộc | Vì sao |
|---|---|
| Chỉ 1 yêu cầu `pending` mỗi công việc | Tránh hàng đợi yêu cầu chồng nhau, người duyệt không biết duyệt cái nào |
| `deadline_type` phải là `has_deadline` | Việc không thời hạn thì gia hạn vô nghĩa |
| `processing_status` không thuộc `done` / `cancelled` | Việc đã đóng thì không gia hạn |
| `requested_end_at` > `end_at` hiện tại | Đây là **gia hạn**, không phải sửa hạn; rút ngắn hạn là việc của quản lý qua màn sửa công việc |
| Người xin phải nằm trong danh sách thực hiện | Người ngoài không xin hộ |
| `reason` bắt buộc, tối thiểu ~10 ký tự | Yêu cầu gốc là "có lý do"; để trống thì lịch sử vô dụng |

MySQL không có unique index có điều kiện, nên ràng buộc "1 yêu cầu pending" kiểm
ở tầng service kèm khoá dòng (`lockForUpdate`) để hai người bấm cùng lúc không
tạo được hai yêu cầu.

## 5. Phân quyền

Nhóm `task-assignment-items.*` đã bị **xoá hẳn** khỏi hệ thống quyền (xem
`$REMOVED_PERMISSIONS` trong PermissionSeeder) — thao tác cá nhân đi qua
`my-assigned-tasks.*` / `my-received-tasks.*`. Hai quyền mới phải nằm trong hai
nhóm đó, **không được tái sinh** `task-assignment-items`.

| Quyền mới | Ai làm | Cấp cho vai trò |
|---|---|---|
| `my-received-tasks.requestExtension` | Người thực hiện xin gia hạn | Nhân viên · Trưởng phòng · Lãnh đạo · Quản lý công việc |
| `my-assigned-tasks.approveExtension` | Người giao việc duyệt / từ chối | Quản lý công việc |

Policy — dùng nguyên khuôn `markDone`:

```php
public function requestExtension(User $user, TaskAssignmentItem $item): bool
{
    if (! $user->can('my-received-tasks.requestExtension')) {
        return false;
    }

    return $this->isAssignee($user, $item);
}

public function approveExtension(User $user, TaskAssignmentItem $item): bool
{
    if (! $user->can('my-assigned-tasks.approveExtension')) {
        return false;
    }

    return (int) $item->assigned_by === $user->id;
}
```

Policy hiện chỉ có helper `isOwnerOrAssigned()` — đúng cho *người liên quan*,
nhưng quá rộng cho việc xin gia hạn: người giao việc lọt vào đó, mà họ thì sửa
thẳng thời hạn được rồi, không cần xin ai. Cần thêm một helper hẹp hơn:

```php
/** Chỉ người thực hiện — người giao việc KHÔNG tính, họ sửa hạn trực tiếp được. */
private function isAssignee(User $user, TaskAssignmentItem $item): bool
{
    $item->loadMissing('users');

    return $item->users->contains('id', $user->id);
}
```

`before()` sẵn có cho `task-overview.manageAll` tự động cho quản trị hệ thống
duyệt hộ — đúng lối thoát mà sổ tay đã ghi cho trường hợp người giao nghỉ dài ngày.

> **Cạm bẫy phải tránh.** Module đang có một bất đối xứng: `markDone` chỉ người
> giao làm được, nhưng `reject` và `reopen` gác bằng `changeStatus` nên **người
> thực hiện cũng làm được**. Nếu bê `changeStatus` sang dùng cho duyệt gia hạn
> thì người thực hiện tự duyệt yêu cầu của chính mình. Bắt buộc dùng quyền riêng
> + đối chiếu `assigned_by`.

## 6. API

| Method | Đường dẫn | Gác |
|---|---|---|
| `GET` | `/api/task-assignment-items/{item}/extensions` | `permission:my-received-tasks.requestExtension\|my-assigned-tasks.approveExtension` |
| `POST` | `/api/task-assignment-items/{item}/extensions` | `can:requestExtension,taskAssignmentItem` |
| `PATCH` | `/api/task-assignment-items/{item}/extensions/{extension}/status` | `can:approveExtension,taskAssignmentItem` |
| `DELETE` | `/api/task-assignment-items/{item}/extensions/{extension}` | người xin thu hồi khi còn `pending` |

`PATCH .../status` nhận `{ status: approved\|rejected, review_note }` — theo quy
ước HTTP trong CLAUDE.md ("đổi trạng thái đơn → PATCH `/{id}/status`").

> Lưu ý: chính module này lại đang dùng endpoint riêng cho hành động —
> `PATCH /{item}/mark-done`, `/reject`, `/reopen`. Nếu ưu tiên nhất quán nội bộ
> module hơn quy ước chung thì đổi thành `/approve` và `/reject` (kebab-case).
> Cần chốt — xem mục 10.

## 7. Khi duyệt thì chuyện gì xảy ra

```php
DB::transaction(function () use ($extension, $item) {
    $extension->update([
        'status' => 'approved',
        'reviewed_by_user_id' => auth()->id(),
        'reviewed_at' => now(),
    ]);

    // BẮT BUỘC dùng Eloquent, không dùng query builder.
    $item->update(['end_at' => $extension->requested_end_at]);
});
```

**Vì sao bắt buộc Eloquent:** `TaskAssignmentItemObserver` bắt `saved()` và
`wasChanged(['end_at', ...])` để gọi `ReminderScheduler::scheduleFor($item)` —
huỷ lịch nhắc cũ, dựng lại theo hạn mới, đồng thời dời cả lịch nhắc tuỳ chỉnh.
Ghi bằng query builder hoặc mass update thì observer **không chạy**, lịch nhắc
giữ nguyên hạn cũ và người thực hiện bị nhắc sai ngày. Đây là lỗi im lặng, không
có gì báo.

Khi từ chối: chỉ cập nhật bản ghi yêu cầu, `end_at` giữ nguyên.

## 8. Lịch sử — hiện ở hai nơi

**Panel "Lịch sử gia hạn"** trong popup chi tiết công việc: bảng đầy đủ gồm ngày
xin, người xin, hạn cũ → hạn xin, lý do, kết quả, người duyệt, ghi chú duyệt.
Đây là chỗ trả lời yêu cầu "lưu lại lịch sử xin gia hạn và lý do".

**Dòng thời gian** (tab Trao Đổi & Thảo Luận): thêm loại `extension` vào
`TaskAssignmentTimelineService` để lần xin gia hạn nằm xen kẽ đúng thứ tự thời
gian với ghi chú và điều chuyển.

Đề xuất **hai mốc riêng** trên dòng thời gian — một lúc xin (`created_at`), một
lúc duyệt (`reviewed_at`) — thay vì gộp một mốc. Dòng thời gian phải phản ánh
đúng thứ tự sự việc; gộp một mốc thì lần duyệt hôm nay lại hiện ở vị trí của
ngày xin tuần trước.

## 9. Thông báo

Hai sự kiện mới, mỗi sự kiện chạm 7 chỗ (enum, event, listener, content builder,
NotificationServiceProvider, NotificationEventConfigSeeder, chỗ `event()`):

| Sự kiện | Báo cho ai |
|---|---|
| `deadline_extension_requested` | Người giao việc (`assigned_by`) |
| `deadline_extension_reviewed` | Người xin (`requested_by_user_id`) |

Gộp duyệt và từ chối vào một sự kiện `reviewed`, phân nhánh nội dung trong
ContentBuilder theo `status` — ít file hơn ba sự kiện tách rời, và người dùng
cũng chỉ cần một công tắc bật/tắt thay vì hai.

## 10. Sáu điểm cần chốt trước khi viết code

1. **Số lần gia hạn tối đa.** Đề xuất không giới hạn cứng, nhưng hiển thị "Đã gia
   hạn N lần" cạnh thời hạn để người duyệt cân nhắc. Nếu nghiệp vụ đòi chặn (ví
   dụ tối đa 2 lần) thì thêm kiểm tra ở service.

2. **Gia hạn có xoá dấu "trễ hạn" không.** Trễ hạn là cờ tính từ `end_at`, nên
   dời hạn xong việc đang trễ thành đúng hạn và thống kê "Trễ hạn" giảm. Đây có
   thể là điều mong muốn, cũng có thể là kẽ hở. Ba lựa chọn: (a) chấp nhận, dấu
   vết nằm ở lịch sử gia hạn; (b) thêm cột `original_end_at` trên công việc để
   thống kê so với hạn gốc; (c) đánh dấu công việc "đã gia hạn" và tách thành
   nhóm riêng trong thống kê. Đề xuất (a) cho đợt đầu.

3. **Có chặn quản lý sửa `end_at` trực tiếp không.** Hiện Quản lý công việc vẫn
   sửa hạn thẳng qua màn sửa công việc, không qua quy trình. Giữ nguyên thì lập
   kế hoạch vẫn thoải mái, nhưng thay đổi kiểu đó không nằm trong lịch sử gia
   hạn. Đề xuất giữ nguyên và ghi rõ trong sổ tay.

4. **Người thực hiện có được xin gia hạn khi việc đang `pending_approval` không.**
   Đề xuất không — đã báo cáo xong 100% rồi thì chờ duyệt, không phải chờ hạn.

5. **Kiểu endpoint đổi trạng thái** — `/status` theo quy ước chung, hay
   `/approve` + `/reject` theo tiền lệ trong module. Xem mục 6.

6. **Miniapp làm cùng đợt hay đợt sau.** Miniapp Zalo có màn duyệt
   (`TaskAssignedApprovalActions.jsx`) nên trưởng phòng duyệt trên điện thoại
   được. Nếu để đợt sau thì yêu cầu gia hạn gửi lúc đi công tác sẽ không duyệt
   được từ điện thoại.

## 11. Khối lượng

**core-be** — 1 migration · 1 model · 1 service · 1 controller · 3 request ·
1 resource · 1 file route · thêm 2 method vào policy · sửa TimelineService ·
thêm 2 quyền vào PermissionSeeder · 2 bộ thông báo (14 file) · cập nhật
`docs/database/TaskAssignment.md` và changelog FE.

**core-fe** — modal xin gia hạn · modal duyệt/từ chối · panel lịch sử · nhánh
timeline · service · store · i18n vi/en · nút trên hai màn danh sách.

**core-miniapp** — modal xin ở màn Được giao · nút duyệt trong
`TaskAssignedApprovalActions.jsx`.

| Giai đoạn | Ước lượng |
|---|---|
| Backend | 1,5 – 2 ngày |
| Web | 1 – 1,5 ngày |
| Miniapp | 0,5 ngày |
| Tài liệu, kiểm thử | 0,5 ngày |
| **Tổng** | **~4 ngày** |
