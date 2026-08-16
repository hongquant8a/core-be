# Module Beneficiary — Người có công

> Ngày tạo: 13:30:00 15/08/2026  
> Cập nhật lần cuối: 13:30:00 15/08/2026

---

## 1. Module này làm gì

Quản lý hồ sơ người có công của một địa phương: thông tin cá nhân kèm toạ độ để chấm lên bản
đồ, các **loại đối tượng** mà người đó thuộc về (kèm giấy tờ chứng minh), danh sách **thân
nhân**, và các **tài liệu** hồ sơ.

Đây là bản **v2**, dựng lại từ đầu sau khi gỡ bỏ v1 ngày 15/08/2026. v1 dựng theo trục hộ gia
đình với 7 bảng, quan hệ n–n giữa người có công và thân nhân, kèm các nhánh trợ cấp / lịch
thăm hỏi / báo cáo biến động — vượt xa nhu cầu thực tế của cán bộ nhập liệu.

Phân tích đầy đủ kèm lý do từng quyết định:
[docs/answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md](../../answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md).
Schema: [docs/database/Beneficiary.md](../../database/Beneficiary.md).

---

## 2. Vì sao module này khác các module khác

Ba điểm lệch so với phần còn lại của repo, đều có chủ đích:

### 2.1. Là module **mới** theo B5, không phải module cũ

Dù tên `Beneficiary` đã tồn tại từ trước, v2 dựng lại từ con số không nên tính là module mới →
bắt buộc theo [docs/system/QUAN_HE_CHA_CON.md](../../system/QUAN_HE_CHA_CON.md): có `save-full`,
optimistic lock, route lồng cho sub-resource, và gọi spatie thẳng trong Service thay vì qua
`Core\Services\MediaService`.

### 2.2. Bảng chính KHÔNG có `status`, ba danh mục thì có

Đây là module đầu tiên dùng quy ước B3 sau khi nới (15/08/2026): `status` chỉ thêm khi nghiệp
vụ thực sự có trạng thái.

| | `beneficiaries` + 3 bảng con | 3 bảng danh mục |
|---|---|---|
| Cột `status` | **Không** | **Có** (`CatalogStatusEnum`) |
| `changeStatus` / `bulkUpdateStatus` | Không | Có |
| Bộ lọc `status` ở `index` | Không | Có |

Lý do khác nhau: hồ sơ một người có công không có trạng thái nghiệp vụ — hoặc còn trong danh
sách quản lý, hoặc bị xoá. Còn danh mục **có**: mục cũ phải ngừng dùng cho hồ sơ mới nhưng vẫn
giữ nguyên cho hàng trăm hồ sơ đang tham chiếu, mà `restrictOnDelete` không cho xoá.

**Quy tắc quan trọng nhất về `inactive`: chỉ chặn *gán mới*, không đụng dữ liệu đã gán.**
Validate `status = active` chỉ chạy ở `store`/`update` (qua `activeCatalogRule()` trong
FormRequest), không chạy ở `show`/`index`.

### 2.3. Thân nhân là 1–n, không phải n–n

v1 cho một thân nhân dùng chung nhiều hồ sơ. v2 chốt 1–n để đơn giản hoá.

**Đánh đổi đã chấp nhận có ý thức:** hai vợ chồng cùng khai một người con sẽ có hai dòng độc
lập, sửa phải sửa cả hai, và `beneficiary_dependents.id_number` **không thể** unique.

Dấu hiệu cần xem lại: nghiệp vụ bắt đầu hỏi "người này là thân nhân của những ai", hoặc cần
đếm số **người** (không phải số **dòng**) thân nhân toàn xã.

---

## 3. Cấu trúc thư mục

```
app/Modules/Beneficiary/
├── Controllers/
│   ├── BeneficiaryController.php                    ← bảng chính + save-full + export/import
│   ├── BeneficiaryTypeRelationController.php        ← dạng D
│   ├── BeneficiaryDependentController.php           ← dạng B
│   ├── BeneficiaryDocumentController.php            ← dạng A
│   ├── BeneficiaryResidentialAreaController.php     ← danh mục
│   ├── BeneficiaryTypeController.php                ← danh mục
│   ├── BeneficiaryRelationshipController.php        ← danh mục
│   └── EnumController.php
├── Enums/{GenderEnum, CatalogStatusEnum}.php
├── Events/BeneficiaryProfileSaved.php
├── Exceptions/CatalogInUseException.php
├── Exports/{BeneficiaryExport, BeneficiaryCatalogExport}.php
├── Imports/{BeneficiaryImport, BeneficiaryCatalogImport}.php
├── Models/                                          ← 7 model
├── Requests/
│   ├── Catalog/                                     ← khuôn chung 3 danh mục
│   └── ...
├── Resources/                                       ← 13 file, gồm Concerns/HasParentLockVersion
├── Routes/{beneficiary, residential_area, beneficiary_type, relationship, enum}.php
└── Services/
    ├── BeneficiaryService.php                       ← 9 action + saveFull + 3 hàm sync
    ├── BeneficiaryTypeRelationService.php
    ├── BeneficiaryDependentService.php
    ├── BeneficiaryDocumentService.php
    └── BeneficiaryCatalogService.php                ← dùng chung cho cả 3 danh mục
```

**Ba danh mục dùng chung một Service** (`BeneficiaryCatalogService` nhận `$modelClass`) nhưng
**ba Controller riêng** — theo đúng tiền lệ `Meeting\Services\CatalogService`. Không gộp
controller được vì route model binding cần type-hint model cụ thể.

---

## 4. API

Chi tiết: [docs/api/beneficiary.md](../../api/beneficiary.md). Tóm tắt:

| Prefix | Bộ action |
|---|---|
| `/api/beneficiaries` | `stats, dashboard, index, show, store, update, destroy, bulkDestroy, export, import, importTemplate` **+ `save-full`** |
| `/api/beneficiaries/{beneficiary}/type-relations` | 6 action (dạng D) |
| `/api/beneficiaries/{beneficiary}/dependents` | 6 action (dạng B) |
| `/api/beneficiaries/{beneficiary}/documents` | 6 action (dạng A) |
| `/api/beneficiary-residential-areas` | Bộ đầy đủ + `changeStatus`, `bulkUpdateStatus`, `reorder` |
| `/api/beneficiary-types` | như trên |
| `/api/beneficiary-relationships` | như trên |
| `/api/beneficiary-enums` | `index` — không `ensure.route.org`, không `permission:` |

`save-full`, `import-template`, `reorder` và `dashboard` **dùng chung permission**
`.store`/`.update`/`.import`/`.stats` — không tạo permission riêng.

**`dashboard`** phục vụ trang thống kê (khác `stats` nhẹ dùng cho badge): một request trả
`kpis` (6 chỉ số), `charts` (8 biểu đồ: giới tính, loại đối tượng, tổ dân phố Top 10 + Khác,
nhóm tuổi, tháp tuổi × giới, tiến độ nhập 12 tháng, thân nhân theo quan hệ, chất lượng dữ
liệu) và `tables` (ma trận tổ × loại, tổng hợp theo loại, hồ sơ cần hoàn thiện). Lọc theo
`from_date`/`to_date`/`residential_area_id`. Biểu đồ "tiến độ nhập" đếm theo `created_at`
(thời điểm NHẬP LIỆU, không phải tăng/giảm đối tượng thực) và luôn phủ 12 tháng gần nhất.

---

## 5. Bốn chỗ dễ hỏng nhất

### 5.1. Thứ tự luồng media KHÔNG được đổi

```
snapshot getMedia()  →  commit  →  addMedia()  →  mới xoá tệp cũ
```

Snapshot chụp **sau** khi upload thì tệp vừa tải lên không nằm trong `keep_media_ids` và bị xoá
ngay. Xoá tệp **trong** transaction thì rollback không cứu được — xoá tệp vật lý không rollback.

Ghi tệp nằm ngoài transaction còn vì lý do khác: trong transaction có `lockForUpdate()` trên
dòng cha, giữ khoá suốt thời gian copy hàng chục tệp khiến request thứ hai chờ tới
`innodb_lock_wait_timeout` (mặc định 50s) rồi 500, thay vì nhận 409 sạch sẽ.

### 5.2. `whereNotIn()->delete()` không kích hoạt `$touches`

`saveFull` và `bulkDestroy` chạy qua Query Builder nên model event không nổ →
`beneficiaries.updated_at` đứng yên. Optimistic lock sẽ mù đúng vào thao tác phá hoại nhất
(xoá hàng loạt dòng con). Vì vậy service phải `$beneficiary->touch()` tay.

Có test bảo vệ: `BeneficiarySaveFullTest::test_deleting_only_child_rows_still_bumps_parent_updated_at`.

### 5.3. UNIQUE + SoftDeletes cần nhánh restore — ở BA chỗ

Dòng đã xoá mềm vẫn chiếm chỗ trong unique index (MySQL coi mọi NULL là khác nhau nên đưa
`deleted_at` vào unique không cứu được). Ba chỗ dính:

| Bảng | Unique | Xử lý |
|---|---|---|
| `beneficiaries` | `(organization_id, id_number)` | `store()` + `import()` restore theo CCCD |
| `beneficiary_type_relations` | `(beneficiary_id, beneficiary_type_id)` | `store()` + `syncTypeRelations()` restore |
| 3 danh mục | `(organization_id, name)` | `store()` + `import()` restore theo tên |

### 5.4. `save-full` CẤM gọi từ màn hình có phân trang

`whereNotIn` xoá mềm sạch phần chưa load và vẫn trả 200. **Backend không chặn được điều này** —
đây là quy ước khác mọi quy ước khác ở chỗ vi phạm nó không tạo ra lỗi. Màn hình có phân trang
phải dùng sub-resource CRUD lẻ.

---

## 6. Test

`tests/Feature/Beneficiary/` — 21 test, 48 assertion:

| File | Bảo vệ điều gì |
|---|---|
| `BeneficiarySaveFullTest` | `[]` = xoá hết vs vắng mặt = giữ nguyên, optimistic lock, parent touch, `is_primary` nhiều nhất một |
| `BeneficiaryTypeRelationTest` | restore dòng đã xoá kèm tệp, `sync_attachments`, demote primary, `$touches` |
| `BeneficiaryCatalogTest` | xoá bị chặn 409, `inactive` không phá dữ liệu cũ, restore theo tên, `reorder`, tra tên không phân biệt hoa/thường |
| `BeneficiaryTenantTest` | cách ly đa tổ chức, unique theo tổ chức, cha xoá mềm giữ con |

---

## 7. Câu hỏi thường gặp

**Q: Vì sao `BeneficiaryTypeRelation` không `extends Pivot`?**

**A:** Nó cần khoá chính riêng để spatie gắn media, và cần model event để `$touches` nổ. `Pivot`
không có cả hai. Đây cũng là lý do B5 cấm `belongsToMany()->sync()` cho module mới.

**Q: Vì sao `birth_date` và `birth_year` cùng tồn tại?**

**A:** Nhiều hồ sơ cũ chỉ biết năm sinh. Model tự suy `birth_year` từ `birth_date` khi có
(`static::saving`), nên hai cột không bao giờ lệch; FormRequest báo lỗi nếu client gửi cả hai mà
mâu thuẫn. Lọc và thống kê theo tuổi dùng `birth_year` vì cột đó luôn có dữ liệu.

**Q: Vì sao tệp đính kèm để disk `public` mà không phải `private`?**

**A:** Quyết định ngày 15/08/2026 — dùng disk mặc định như 4 module hiện có, FE dùng URL trực
tiếp. **Đánh đổi đã biết:** spatie lưu theo tên gốc đã sanitize nên ai biết URL đều tải được,
không qua kiểm quyền. Cần siết thì thêm disk `private` + endpoint tải có kiểm quyền + đổi
`download_url` trong Resource — không phải sửa cấu trúc bảng.

**Q: Xoá một mục danh mục đang được dùng thì sao?**

**A:** Bị chặn 409 (`CatalogInUseException`) kèm số bản ghi đang tham chiếu và gợi ý chuyển sang
"Ngừng sử dụng". Nói thẳng đường đi tiếp là cố ý: không nói thì cán bộ gặp 409 sẽ bế tắc, tưởng
dữ liệu kẹt vĩnh viễn.

**Q: FE gọi danh mục để dựng dropdown thì làm sao ẩn mục đã ngừng dùng?**

**A:** Truyền `status=active` tường minh. Endpoint **không** tự lọc vì màn quản trị danh mục cần
thấy cả dòng `inactive`.
