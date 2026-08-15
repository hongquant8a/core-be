# Schema module Beneficiary (Người có công)

> Ngày tạo: 13:20:00 15/08/2026  
> Cập nhật lần cuối: 13:20:00 15/08/2026

Thiết kế v2 — một hồ sơ = một người có công. Phân tích đầy đủ kèm lý do từng quyết định:
[answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md](../answer/module-nguoi-co-cong-thiet-ke-lai-v2_110031_15082026.md).

---

## Sơ đồ quan hệ

```
beneficiary_residential_areas  (danh mục: Tổ dân phố/Thôn)
beneficiary_types              (danh mục: Loại đối tượng)
beneficiary_relationships      (danh mục: Mối quan hệ)
        │  │  │
        │  │  └────────────────────────────┐
        │  └──────────────┐                │
        ↓ (restrict)      ↓ (restrict)     ↓ (restrict)
   beneficiaries ─┬─< beneficiary_type_relations   [dạng D] + media(attachments)
   (BẢNG CHÍNH)   │
                  ├─< beneficiary_dependents        [dạng B]
                  │
                  └─< beneficiary_documents         [dạng A] + media(files)
```

| Bảng con | Dạng B5 | Bộ action |
|---|---|---|
| `beneficiary_type_relations` | **D** — bảng nối n–n mang `is_primary` + tệp | 6 |
| `beneficiary_dependents` | **B** — 1–n không tệp | 6 |
| `beneficiary_documents` | **A** — 1–n có tệp | 6 |

---

## `beneficiaries` — bảng chính

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | bigint PK | |
| `organization_id` | FK → `organizations` | NOT NULL, cascade |
| `full_name` | varchar(255) | NOT NULL |
| `birth_date` | date | nullable |
| `birth_year` | smallint unsigned | nullable — model tự suy từ `birth_date` khi có |
| `gender` | varchar(20) | nullable, `GenderEnum` |
| `id_number` | varchar(20) | nullable, **UNIQUE(`organization_id`, `id_number`)** |
| `phone` | varchar(20) | nullable |
| `residential_area_id` | FK → `beneficiary_residential_areas` | nullable, **restrict** |
| `address` | varchar(500) | nullable |
| `latitude` | decimal(10,7) | nullable |
| `longitude` | decimal(10,7) | nullable |
| `note` | text | nullable |
| `created_by` / `updated_by` | FK → `users` | nullable, nullOnDelete |
| timestamps + `deleted_at` | | SoftDeletes **bắt buộc** |

**KHÔNG có cột `status`** — hồ sơ không có trạng thái nghiệp vụ. Đây là điều CLAUDE.md B3 cho
phép sau khi nới (15/08/2026): `status` chỉ thêm khi nghiệp vụ thực sự có.

**Index:** `(organization_id, residential_area_id)`, `(organization_id, full_name)`,
`(organization_id, birth_year)`.

**Bẫy UNIQUE + SoftDeletes:** dòng đã xoá mềm vẫn chiếm chỗ trong unique index (đưa
`deleted_at` vào unique không cứu được — MySQL coi mọi NULL là khác nhau). `store()` và
`import()` phải `withTrashed()` → `restore()` thay vì `create()`.

---

## `beneficiary_type_relations` — Đối tượng (dạng D)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` | bigint PK | Khoá chính riêng — **không** `extends Pivot` |
| `organization_id` | FK | cascade |
| `beneficiary_id` | FK → `beneficiaries` | cascade |
| `beneficiary_type_id` | FK → `beneficiary_types` | **restrict** |
| `is_primary` | boolean | default false |
| `created_by` / `updated_by` / timestamps / `deleted_at` | | |

**UNIQUE:** `(beneficiary_id, beneficiary_type_id)` — đặt tên tường minh
`btr_beneficiary_type_unique` vì tên tự sinh dài 68 ký tự, vượt giới hạn 64 của MySQL.

**Media:** collection `beneficiary_type_attachments`, disk mặc định (`public`).

`$touches = ['beneficiary']`.

---

## `beneficiary_dependents` — Thân nhân (dạng B)

Cột dữ liệu cá nhân giống hệt `beneficiaries` (`full_name`, `birth_date`, `birth_year`,
`gender`, `id_number`, `phone`, `residential_area_id`, `address`, `latitude`, `longitude`,
`note`), cộng thêm:

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `beneficiary_id` | FK → `beneficiaries` | cascade |
| `relationship_id` | FK → `beneficiary_relationships` | nullable, **restrict** |
| `is_primary` | boolean | default false |

**`id_number` KHÔNG unique** — hệ quả trực tiếp của việc chọn 1–n thay vì n–n: hai hồ sơ cùng
khai một người con thì đúng là hai dòng cùng CCCD.

`$touches = ['beneficiary']`. Không có media.

---

## `beneficiary_documents` — Tài liệu (dạng A)

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `beneficiary_id` | FK → `beneficiaries` | cascade |
| `name` | varchar(255) | NOT NULL |
| `note` | text | nullable |

**Media:** collection `beneficiary_document_files`, disk mặc định (`public`).
`$touches = ['beneficiary']`.

---

## Ba bảng danh mục

Cùng một khuôn: `beneficiary_residential_areas`, `beneficiary_types`,
`beneficiary_relationships`.

| Cột | Kiểu | Ràng buộc |
|---|---|---|
| `id` / `organization_id` / `created_by` / `updated_by` / timestamps / `deleted_at` | | Chuẩn |
| `name` | varchar(255) | NOT NULL, **UNIQUE(`organization_id`, `name`)** |
| `note` | text | nullable |
| `sort_order` | int | NOT NULL, default 0 |
| `status` | varchar(20) | NOT NULL, default `active` — `CatalogStatusEnum` |

**Không có cột `code`** — `name` là định danh duy nhất, nên bắt buộc UNIQUE theo tổ chức:
import Excel tra ngược danh mục hoàn toàn dựa vào nó.

**Danh mục CÓ `status`, bảng chính thì không.** Không mâu thuẫn: mục danh mục cũ phải ngừng
dùng cho hồ sơ mới nhưng vẫn giữ nguyên cho hàng trăm hồ sơ đang tham chiếu, mà
`restrictOnDelete` không cho xoá. `inactive` CHỈ chặn gán mới — validate `status = active` chỉ
chạy ở `store`/`update`, không ở `show`/`index`.

---

## Morph map

Chỉ hai model tham gia quan hệ polymorphic (media):

```php
'beneficiary_type_relation' => BeneficiaryTypeRelation::class,
'beneficiary_document'      => BeneficiaryDocument::class,
```

Hồ sơ và thân nhân không gắn media nên không có alias.

---

## Migration

7 file, chạy theo thứ tự: 3 danh mục → `beneficiaries` → 3 bảng con.

```
2026_08_15_120001_create_beneficiary_residential_areas_table.php
2026_08_15_120002_create_beneficiary_types_table.php
2026_08_15_120003_create_beneficiary_relationships_table.php
2026_08_15_120004_create_beneficiaries_table.php
2026_08_15_120005_create_beneficiary_type_relations_table.php
2026_08_15_120006_create_beneficiary_dependents_table.php
2026_08_15_120007_create_beneficiary_documents_table.php
```

`softDeletes()` ở bảng cha là **bắt buộc** chứ không tuỳ chọn: ba bảng con dùng
`onDelete('cascade')`, nên cha xoá cứng sẽ khiến MySQL xoá cứng toàn bộ dòng con — bỏ qua
SoftDeletes của chúng, bỏ qua model event, và để lại tệp media mồ côi trên đĩa.
