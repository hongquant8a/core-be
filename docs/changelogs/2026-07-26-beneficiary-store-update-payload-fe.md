# Người có công: chuẩn hóa payload `store` / `update`

> Ngày tạo: 07:40:00 26/07/2026
> Cập nhật lần cuối: 07:40:00 26/07/2026

## ⚠️ BREAKING — đọc kỹ trước khi deploy

`POST /api/beneficiaries` và `PUT /api/beneficiaries/{id}` đổi hình dạng payload. FE phải sửa
form tạo/sửa hồ sơ trước khi BE lên production.

## Quy tắc chung

| Thông tin | Dạng gửi lên |
|---|---|
| Hộ gia đình | **1 ID** — `household_id` |
| Tổ dân phố / thôn | **1 ID** — `residential_area_id` |
| Thân nhân | **Mảng liên kết** — `dependent_id` + thông tin quan hệ |
| Tài liệu | **Mảng thông tin đầy đủ** — `name`, `note` |
| Loại đối tượng | **Mảng** — `type` (id loại) + thông tin quyết định |

```json
{
  "full_name": "Trần Văn B",
  "gender": "male",
  "household_id": 3,
  "residential_area_id": 5,

  "classifications": [
    { "type": "war_invalid", "decision_no": "123/QĐ", "decision_date": "2010-05-20",
      "issued_by": "UBND TP Đà Nẵng", "is_primary": true }
  ],
  "dependents": [
    { "dependent_id": 12, "relationship_type": "child", "note": "Con ruột" }
  ],
  "documents": [
    { "name": "Giấy chứng nhận thương binh", "note": "Bản sao công chứng" }
  ]
}
```

## 1. Bỏ nhánh tạo hộ gia đình lồng trong payload

**Trước:** `store` nhận `household: { head_name, address, ... }` để tạo hộ mới ngay lúc lập hồ sơ.
**Nay:** chỉ nhận `household_id`. Muốn tạo hộ mới → gọi `POST /api/beneficiary-households` trước,
lấy `id`, rồi truyền vào.

Gửi khóa `household` sẽ bị bỏ qua (không còn rule nào nhận nó).

## 2. `dependents` đổi từ "tạo mới" sang "liên kết"

**Trước:** mỗi phần tử là **thông tin đầy đủ của thân nhân** (`full_name`, `gender`, …) và BE **tạo
mới** bản ghi thân nhân.
**Nay:** mỗi phần tử **tham chiếu thân nhân đã có**:

```json
{ "id": 7, "dependent_id": 12, "relationship_type": "child", "note": "" }
```

- `dependent_id` — bắt buộc, phải tồn tại. Tạo thân nhân trước qua `POST /api/beneficiary-dependents`.
- `relationship_type` — bắt buộc, enum `DependentRelationshipEnum`.
- `id` — **id của bản ghi quan hệ** (không phải id thân nhân), chỉ gửi khi cập nhật dòng đã có.

Luồng FE: form thân nhân đổi từ "nhập thông tin thân nhân" thành "chọn thân nhân + chọn quan hệ"
(autocomplete từ `GET /api/beneficiary-dependents`). Có thể tái dùng `BeneficiaryDependentLinkDrawer`.

## 3. `documents` — mới, gửi kèm trong payload

```json
{ "id": 4, "name": "Giấy chứng nhận", "note": "Bản sao" }
```

`name` bắt buộc. **Tập tin đính kèm KHÔNG gửi trong JSON này** — vẫn upload riêng qua
`POST /api/beneficiary-documents` (multipart, trường `files[]`) hoặc
`PUT /api/beneficiary-documents/{id}`. Payload lồng chỉ quản lý phần metadata (tên + ghi chú).

Endpoint `beneficiary-documents` giữ nguyên, không bỏ.

## 4. `update` — mỗi mảng là TRẠNG THÁI ĐẦY ĐỦ (full replace)

Áp dụng **giống hệt nhau** cho `classifications`, `dependents`, `documents`:

| Gửi gì | Kết quả |
|---|---|
| **Không gửi khóa** | Giữ nguyên danh sách hiện có |
| Gửi mảng có phần tử | **Xóa sạch** danh sách cũ rồi tạo lại theo mảng gửi lên |
| Gửi `[]` | Xóa sạch danh sách đó |

```json
{ "documents": [{ "name": "Giấy A" }, { "name": "Giấy B" }] }
```

**Không có `*_deleted`** — FE chỉ việc gửi đúng bảng đang hiển thị trên màn hình, không phải theo
dõi dòng nào người dùng đã xóa.

**Không nhận `id`** trong phần tử (cả `store` lẫn `update`) — dòng cũ bị xóa nên `id` không còn ý
nghĩa. Gửi `id` → 422 tại `documents.0.id`, để FE không tưởng nhầm là đang cập nhật đúng dòng.

Nhờ vậy `PUT` **idempotent**: gửi lại đúng payload (FE retry khi mạng chập, bấm đúp nút Lưu) vẫn ra
đúng một trạng thái, không nhân bản dòng.

### ⚠️ Tài liệu: gửi `documents` là mất file đính kèm

Tài liệu và loại đối tượng có file scan gắn theo `id` của dòng. Full replace tạo dòng mới với `id`
mới nên **file cũ bị xóa theo**.

Vì vậy: chỉ gửi khóa `documents`/`classifications` khi cán bộ thực sự sửa danh sách đó. Màn hình
chỉ sửa thông tin cơ bản thì **đừng gửi hai khóa này** — form sẽ giữ nguyên tài liệu và file.
Sau khi thay danh sách, upload lại file qua `POST /api/beneficiary-documents`.

## 5. Response thêm `dependents`

`show` / `store` / `update` giờ trả kèm danh sách quan hệ thân nhân:

```json
"dependents": [
  { "id": 7, "dependent_id": 12, "relationship_type": "child",
    "relationship_type_label": "Con",
    "dependent": { "id": 12, "full_name": "Trần Thị C", "date_of_birth": "10/02/1980",
                   "id_number": "049...", "phone": "0905..." },
    "note": "Con ruột" }
]
```

Đây là khoảng trống cũ đã nêu: trước đây không có đường nào lấy danh sách thân nhân của một người
có công (`show` chỉ trả `dependents_count`).

`index` vẫn chỉ trả `dependents_count` / `documents_count` — chi tiết lấy ở `show`.

## 6. Phân quyền cho 2 mảng có resource riêng

`documents` và `dependents` ghi vào bảng có permission riêng, nên gửi kèm chúng trong payload
**đòi hỏi đúng quyền của resource đó** — không phải cứ có `beneficiaries.update` là làm được:

| Gửi trong payload | Permission cần thêm |
|---|---|
| `documents` khác rỗng | `beneficiary-documents.store` |
| `documents` (bất kỳ) khi hồ sơ đang có tài liệu → dòng cũ bị xóa | `beneficiary-documents.destroy` |
| `dependents` khác rỗng | `beneficiary-dependents.storeRelation` |
| `dependents` (bất kỳ) khi hồ sơ đang có quan hệ → dòng cũ bị xóa | `beneficiary-dependents.destroyRelation` |

Thiếu quyền → **403** cho cả request (không ghi gì cả, kể cả phần thông tin cơ bản của hồ sơ).

`classifications` không cần quyền phụ — nó không có resource riêng, là phần thân của hồ sơ.

**FE nên ẩn/khóa** editor tài liệu và editor thân nhân khi user không có quyền tương ứng, thay vì
để cán bộ nhập xong mới nhận 403 và mất trắng dữ liệu đã gõ.

## Việc FE cần làm

- [ ] Bỏ luồng "tạo hộ mới trong form người có công" → chuyển sang chọn hộ có sẵn hoặc tạo hộ ở màn hộ.
- [ ] Đổi editor thân nhân: chọn thân nhân có sẵn + quan hệ, thay vì nhập thông tin thân nhân.
- [ ] Thêm editor tài liệu (tên + ghi chú) vào form; upload file vẫn qua endpoint riêng.
- [ ] Khi sửa: gửi nguyên bảng đang hiển thị (không có `*_deleted`); **bỏ hẳn khóa** `documents`/`classifications` nếu form không đụng tới chúng, để không mất file đính kèm.
- [ ] Đọc `data.dependents[]` ở màn chi tiết thay vì gọi API phụ.
- [ ] Ẩn editor tài liệu / thân nhân khi user thiếu permission tương ứng (mục 6) để tránh 403 sau khi nhập.
