# Xin gia hạn thời hạn công việc — phân tích và thiết kế

> Ngày tạo: 18:05:47 10/09/2026  
> Cập nhật lần cuối: 09:20:00 12/09/2026

Yêu cầu: người thực hiện xin gia hạn thời hạn công việc kèm lý do; chỉ người có
quyền hoặc người quản lý công việc duyệt thì hạn mới đổi; lưu lại lịch sử xin
gia hạn và lý do.

Tài liệu này là **thiết kế, chưa triển khai**. Sáu điểm mở đã được chốt ngày
12/09/2026 — xem mục 10.

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
| `processing_status` không thuộc `done` / `cancelled` / `pending_approval` | Việc đã đóng thì không gia hạn; việc đang chờ duyệt hoàn thành là chờ duyệt, không phải chờ hạn (chốt điểm 4) |
| `requested_end_at` chỉ cần là ngày hợp lệ — **không ràng buộc gì thêm** | Người xin tự biết mình cần hạn nào; **người duyệt là cửa chặn, không phải form**. Chốt ngày 12/09/2026 |
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

> **Cạm bẫy — đã vá ngày 11/09/2026.** Module từng có một bất đối xứng: `markDone`
> chỉ người giao làm được, nhưng `reject` và `reopen` gác bằng `changeStatus` nên
> **người thực hiện cũng làm được**. Nay `reject` và `reopen` đã có policy riêng
> theo đúng khuôn `markDone` (quyền + đối chiếu `assigned_by`), và chính hai
> method đó là hình mẫu gần nhất để viết `approveExtension`.
>
> Vẫn giữ nguyên cảnh báo cũ: **không** gác duyệt gia hạn bằng `changeStatus` —
> policy đó (dùng cho `PATCH /{item}/status`, tức tạm dừng / huỷ) chỉ đòi "người
> liên quan", nên người thực hiện sẽ tự duyệt yêu cầu của chính mình.

## 6. API

| Method | Đường dẫn | Gác |
|---|---|---|
| `GET` | `/api/task-assignment-items/{item}/extensions` | `permission:my-received-tasks.requestExtension\|my-assigned-tasks.approveExtension` |
| `POST` | `/api/task-assignment-items/{item}/extensions` | `can:requestExtension,taskAssignmentItem` |
| `PATCH` | `/api/task-assignment-items/{item}/extensions/{extension}/approve` | `can:approveExtension,taskAssignmentItem` |
| `PATCH` | `/api/task-assignment-items/{item}/extensions/{extension}/reject` | `can:approveExtension,taskAssignmentItem` |
| `DELETE` | `/api/task-assignment-items/{item}/extensions/{extension}` | người xin thu hồi khi còn `pending` |

Cả hai nhận `{ review_note }` (bắt buộc khi từ chối, tuỳ chọn khi duyệt).

**Chốt điểm 5:** dùng endpoint riêng theo tên nghiệp vụ, bám tiền lệ của chính
module (`/mark-done`, `/reject`, `/reopen`, `/pause`, `/cancel` — tách ngày
11/09/2026), **không** dùng `PATCH /{id}/status`. Duyệt và từ chối là hai nghiệp
vụ, không phải hai giá trị của một trường trạng thái. Quy ước chung trong
CLAUDE.md mục 3 đã được cập nhật theo hướng này.

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

## 10. Sáu điểm đã chốt (12/09/2026)

| # | Điểm | Chốt |
|---|---|---|
| 1 | Số lần gia hạn tối đa | **Không giới hạn.** Hiển thị "Đã gia hạn N lần" cạnh thời hạn để người duyệt tự cân nhắc |
| 2 | Mốc tính thời hạn | **Dời hạn chỉ có hiệu lực khi đã duyệt** — chi tiết ở 10.1 |
| 3 | Quản lý sửa `end_at` trực tiếp | **Vẫn cho**, giữ nguyên hiện trạng |
| 4 | Xin gia hạn khi đang `pending_approval` | **Không** |
| 5 | Kiểu endpoint duyệt | **`/approve` + `/reject`** theo tiền lệ module, đồng thời **cập nhật lại quy ước chung** — chi tiết ở 10.2 |
| 6 | Miniapp | **Cùng đợt** với web |

### 10.1. Điểm 2 — "dời hạn phải được duyệt thì mới tính từ mốc dời hạn"

Kéo theo bốn hệ quả, phải làm đúng cả bốn:

- **Trong lúc chờ duyệt, `end_at` KHÔNG đổi.** Việc đang trễ vẫn là trễ, vẫn nằm
  trong thống kê Trễ hạn, lịch nhắc vẫn chạy theo hạn cũ. Gửi yêu cầu gia hạn
  **không phải** là cách tạm hoãn — đây là điểm dễ hiểu nhầm nhất khi triển khai.
- **Duyệt xong mới đổi `end_at`**, từ đó trễ hạn và lịch nhắc tính theo hạn mới.
  Việc đang trễ trở thành đúng hạn — hệ quả cố ý, dấu vết nằm ở bảng lịch sử.
- **Không thêm cột `original_end_at`.** Muốn biết hạn gốc thì đọc `current_end_at`
  của lần gia hạn đầu tiên; bảng lịch sử đã chụp đủ.
- **Từ chối thì không đổi gì** — `end_at` giữ nguyên, lịch nhắc giữ nguyên.

**Không ràng buộc `requested_end_at`.** Bản thiết kế đầu định chặn hạn xin phải lớn
hơn hạn cũ và lớn hơn hiện tại; chốt ngày 12/09/2026 là **bỏ cả hai**. Người xin tự
biết mình cần hạn nào, và mọi yêu cầu đều phải qua người duyệt — thêm luật ở form chỉ
chặn nhầm trường hợp hợp lệ (việc đã trễ, việc cần dời gấp trong vài ngày). Bảng lịch
sử vẫn chụp `current_end_at` → `requested_end_at` nên hạn có bị rút ngắn cũng nhìn ra.

### 10.2. Điểm 5 — sửa cả quy ước chung, không chỉ chọn cho tính năng này

Quy ước trong CLAUDE.md mục 3 vốn ghi *"Đổi trạng thái đơn → `PATCH /{id}/status`"*.
Từ đợt 11/09/2026, module đã tách `/mark-done`, `/reject`, `/reopen`, `/pause`,
`/cancel` thành endpoint riêng vì mỗi cái là **một nghiệp vụ có quyền riêng** —
gộp chung `/status` thì không gác quyền riêng cho từng thao tác được.

Nên quy ước chung được cập nhật lại cho khớp thực tế:

- `PATCH /{id}/status` — dành cho đổi trạng thái **không** có nghiệp vụ riêng
  (bật/tắt `active`–`inactive`, chuyển qua lại giữa hai trạng thái tiến độ).
- Thao tác có **quyền riêng** thì có **endpoint riêng đặt theo tên nghiệp vụ**
  (`/approve`, `/reject`, `/mark-done`, `/pause`, …), kebab-case.

Duyệt và từ chối gia hạn là hai nghiệp vụ, không phải hai giá trị của một trường.

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
