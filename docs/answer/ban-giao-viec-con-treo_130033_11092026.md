# Bàn giao — các việc còn treo của phân hệ Quản lý công việc

> Ngày tạo: 13:00:33 11/09/2026  
> Cập nhật lần cuối: 09:25:00 12/09/2026

Tài liệu này để **phiên làm việc khác (hoặc người khác) đọc là làm tiếp được ngay**,
không cần đọc lại hội thoại cũ. Mỗi mục ghi đủ: đang ở đâu, vướng gì, ai quyết,
làm gì tiếp.

Mốc mã nguồn khi bàn giao (nhánh `quanlycongviec` cả ba repo):

| Repo | Commit | Tình trạng |
|---|---|---|
| core-be | `e8b890d` | sạch, đã push |
| core-fe | `33373560` | sạch, đã push; còn `_shot.mjs` chưa theo dõi (xem mục 3) |
| core-miniapp | `883ce47` | sạch, đã push |

---

## 1. Thao tác hàng loạt ở màn "Công việc được giao" — CHỜ QUYẾT ĐỊNH

**Bối cảnh.** Luật thao tác hàng loạt nay là **toàn có hoặc toàn không**: service
gom điều kiện qua `pullAuthorized()` (`TaskAssignmentItemService`), có một dòng
không đạt là từ chối cả lô kèm thông báo `x/y công việc được chọn không đạt điều kiện`.
Làm vậy để điều kiện nằm đúng ở policy, không nhân bản trong service.

**Vướng.** Thanh công cụ hàng loạt ở `core-fe/src/modules/task/item/views/TaskItemList.vue`
(`canBulkAction`, khoảng dòng 441 và 919) chỉ gác bằng **quyền**, không xét
`viewMode`. Ở chế độ `received` thì mọi dòng đều do người khác giao, mà `pause`,
`cancel`, `delete` đòi `assigned_by === user->id` → bấm là từ chối trọn lô. Người
dùng thấy nút bật nhưng không bao giờ dùng được.

**Ba hướng:**

1. **Thêm `:item-selectable`** chặn chọn những dòng không đủ điều kiện — giống cách
   đã làm cho `PetitionList.vue` (`isRowSelectable`). Nút vẫn còn, chỉ không chọn
   được dòng vô vọng. *Đây là hướng tôi đề xuất.*
2. **Ẩn thanh công cụ khi `viewMode === 'received'`** — đơn giản nhất, nhưng mất luôn
   thao tác hàng loạt hợp lệ (ví dụ đổi `todo` ↔ `in_progress` việc của mình).
3. **Chuyển sang thực thi một phần** — làm được dòng nào làm dòng đó, trả về số
   thành công/bỏ qua. Phải sửa cả BE (`pullAuthorized` bỏ ném exception) và FE.

**Quyết định của người dùng.** Chọn xong thì FE khoảng 30 phút (hướng 1 hoặc 2),
hoặc BE+FE khoảng nửa ngày (hướng 3).

---

## 2. Xin gia hạn thời hạn công việc — ĐÃ CHỐT, SẴN SÀNG VIẾT CODE

**Thiết kế đầy đủ:** [`docs/answer/gia-han-thoi-han-cong-viec_180547_10092026.md`](gia-han-thoi-han-cong-viec_180547_10092026.md)

Sáu điểm mở đã được chốt ngày 12/09/2026 (mục 10 của tài liệu đó):

| # | Chốt |
|---|---|
| 1 | Không giới hạn số lần gia hạn; hiển thị "Đã gia hạn N lần" |
| 2 | Dời hạn **chỉ có hiệu lực khi đã duyệt**; chờ duyệt thì vẫn tính trễ theo hạn cũ; không thêm cột `original_end_at` |
| 3 | Quản lý vẫn sửa `end_at` trực tiếp được, giữ nguyên hiện trạng |
| 4 | Không cho xin gia hạn khi việc đang `pending_approval` |
| 5 | `/approve` + `/reject` theo tiền lệ module; quy ước chung ở CLAUDE.md mục 3 **đã cập nhật** theo hướng này |
| 6 | Miniapp làm cùng đợt với web |

**Còn lại:** viết code, ước lượng ~4 ngày (BE 1,5–2 · web 1–1,5 · miniapp 0,5 ·
tài liệu và kiểm thử 0,5).

## 3. `core-fe/_shot.mjs` — script chụp màn hình, đang hỏng

Script Playwright sinh 25 ảnh hướng dẫn trong `core-fe/docs/images/`. Hiện **chưa
commit** và **đang hỏng**: nó đăng nhập bằng `nhanvien5`, `truongphong1`, `quanly1`
— ba tài khoản demo đã bị xoá khi chuyển dữ liệu hệ thống cũ.

**Việc cần làm (~10 phút):** đổi sang tài khoản thật hiện có — `admin`,
`phongkthtdt`, `huynhnt` — rồi chạy lại. Cách chạy trong container + cầu TCP đã
ghi ở memory `reference_chup_man_hinh_fe.md`.

**Quyết định:** sau khi sửa thì commit vào repo hay để ngoài. Kèm theo đó, 25 ảnh
hướng dẫn trong `docs/images/` vẫn đang là dữ liệu demo cũ — chụp lại thì tài liệu
hướng dẫn mới khớp thực tế.

---

## 4. Thay đổi API đã phá vỡ tương thích — CẦN THÔNG BÁO RA NGOÀI

`PUT /api/task-assignment-items/{id}` và `PATCH /api/task-assignment-items/{id}/status`
**không còn nhận** `done`, `paused`, `cancelled` — trả 422 kèm thông báo tiếng Việt.
Lý do: sửa công việc từng là cửa sau để duyệt việc, bỏ qua điều kiện chờ duyệt, bỏ
qua ghi `approved_by`/`completed_at`, bỏ qua thông báo. Miniapp đang duyệt đúng theo
đường đó.

Thay bằng endpoint riêng, mỗi cái một quyền:

| Thao tác | Endpoint | Quyền | Policy |
|---|---|---|---|
| Hoàn thành | `PATCH /{id}/mark-done` | `my-assigned-tasks.markDone` | chỉ người giao |
| Trả lại báo cáo | `PATCH /{id}/reject` | `my-assigned-tasks.reject` | chỉ người giao |
| Mở lại | `PATCH /{id}/reopen` | `my-assigned-tasks.reopen` | chỉ người giao |
| Tạm dừng | `PATCH /{id}/pause` | `my-assigned-tasks.pause` | chỉ người giao |
| Huỷ | `PATCH /{id}/cancel` | `my-assigned-tasks.cancel` | chỉ người giao |
| `todo` ↔ `in_progress` | `PATCH /{id}/status` | `my-assigned-tasks.changeStatus` | người liên quan |

Web và miniapp đã sửa. **Còn phải kiểm:** luồng n8n, script tích hợp, hoặc bất kỳ
chỗ nào ngoài hai app này gọi `PUT` để đổi trạng thái. Changelog:
`docs/changelogs/2026-09-11-tach-quyen-tam-dung-huy-tra-lai-mo-lai-fe.md`.

---

## 5. Bản sao lưu để đẩy lên production

Ở `/home/quandh/danatec-core/backups/`:

| Tệp | Nội dung |
|---|---|
| `laravel_20260911_122040.sql.gz` (257 KB) | 133 migration, 312 quyền, dữ liệu đã chuyển — đã kiểm bằng cách phục hồi vào schema nháp |
| `storage_public_20260910_163843.tar.gz` (430 MB) | 507 tệp đính kèm, đã đối chiếu MD5 từng tệp |
| `HUONG-DAN-KHOI-PHUC.md` | hướng dẫn phục hồi; **không cần** `migrate`/`seed` với bản dump này |
| `CHECKSUM.md5` | `md5sum -c` → OK |
| `cu-20260910/` | bản dump cũ, đã cách ly, có ghi chú `DUNG-DUNG-BAN-NAY.md` |

**Việc tuỳ chọn (30 giây):** dump lại để bản sao lưu mang theo 4 nhãn quyền tiếng
Việt mới thêm ở `e8b890d`. Không bắt buộc — chạy
`sail artisan db:seed --class=PermissionSeeder` sau khi phục hồi là tương đương.
Tệp media 430 MB giữ nguyên, không cần dump lại.

---

## 6. Nợ kỹ thuật có sẵn từ trước — ghi để biết, chưa sửa

Những mục dưới đây **đã tồn tại trước loạt việc này**, không phải do các thay đổi
vừa rồi gây ra (đã kiểm bằng cách `git stash` rồi chạy lại):

- `sail artisan test --filter=TaskAssignment`: **29 fail / 24 pass**, y hệt cả trước
  và sau các thay đổi. Phần lớn là `TaskAssignmentDepartmentService::syncUsers` sau
  đợt tái cấu trúc nhân viên–phòng ban ngày 24/08.
- core-be chưa Pint-clean. `PetitionResource.php`, `TaskAssignmentReminderResource.php`,
  `TaskAssignmentDocumentService.php` fail Pint từ trước. **Chỉ chạy Pint trên file
  đã sửa**, chạy cả thư mục sẽ format lại hàng chục file không liên quan.
- `core-fe/src/modules/task/item/components/TaskItemModal.vue`: 19 lỗi eslint có sẵn.
- `PetitionList.vue`: import `isTerminalStatus` không dùng nữa. Cố tình so sánh
  `'completed'` trực tiếp thay vì dùng helper đó, vì helper gộp cả `cancelled` mà BE
  chỉ khoá `completed`.

---

## 7. Cách tiếp tục ở phiên khác

1. Mở tài liệu này. Mục 1 và 3 cần **người dùng quyết**, không phải việc lập trình
   — hỏi trước khi làm. Mục 2 đã chốt xong, viết code được ngay.
2. Mục 4 làm được ngay: rà các tích hợp ngoài.
3. Mục 5 làm được ngay nếu muốn.
4. Mục 6 chỉ để tra khi thấy test đỏ hay Pint đỏ — đừng gom vào cùng commit với
   việc mới.
5. Nền tảng đã xong, đọc khi cần: `docs/database/TaskAssignment.md` (schema),
   `docs/changelogs/2026-09-1*.md` (ba đợt thay đổi API và UI),
   `docs/answer/gia-han-thoi-han-cong-viec_180547_10092026.md` (thiết kế gia hạn).

**Đã hoàn thành và không cần làm lại:** chuyển dữ liệu hệ thống cũ (100 văn bản,
505 công việc, 327 báo cáo, 506 tệp đính kèm, 60 người dùng, 8 phòng ban — đã đối
chiếu số liệu và MD5), khoá đơn thư khi hoàn thành + xoá mềm, tách 4 quyền
`reject`/`reopen`/`pause`/`cancel`, dồn điều kiện thao tác hàng loạt về policy, sửa
bố cục tên công việc dài ở 6 màn, bổ sung nhãn tiếng Việt còn thiếu.
