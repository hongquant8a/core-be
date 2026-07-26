# Thân nhân chính + tọa độ bản đồ khi người có công đã mất

> Ngày tạo: 11:10:00 26/07/2026
> Cập nhật lần cuối: 11:10:00 26/07/2026

**Không breaking** — chỉ thêm trường, hành vi cũ giữ nguyên.

## Vấn đề

Người có công qua đời thì tọa độ của họ không còn ý nghĩa thực địa, nhưng cán bộ vẫn cần một điểm
trên bản đồ và một đầu mối liên hệ để đến thăm viếng / chi trả cho thân nhân. Trước đây không có
cách nào biết nên liên hệ ai trong số các thân nhân.

## 1. Cờ `is_primary` trên quan hệ thân nhân

Thêm `beneficiary_dependent_relations.is_primary` (boolean, mặc định `false`), **tối đa 1 dòng cho
mỗi người có công**.

Cờ nằm trên **quan hệ**, không phải trên thân nhân: cùng một người có thể là thân nhân chính của hồ
sơ này nhưng chỉ là thân nhân phụ của hồ sơ khác.

**Gửi lên** — cả hai đường liên kết đều nhận:

```jsonc
// POST/PUT /api/beneficiaries
"dependents": [
  { "dependent_id": 12, "relationship_type": "child", "is_primary": true, "note": "" }
]

// POST /api/beneficiary-dependents/{id}/relations
{ "beneficiary_id": 1, "relationship_type": "child", "is_primary": true }
```

- Mảng `dependents[]`: gửi 2 phần tử `is_primary: true` → **422** tại field `dependents`.
- Endpoint quan hệ lẻ: đặt `is_primary: true` sẽ **tự hạ** thân nhân chính cũ của hồ sơ đó xuống
  phụ, không báo lỗi. FE không cần bỏ cờ thủ công trước.

**Trả về** — `dependents[].is_primary`, và thêm khóa `primary_dependent` (chính là phần tử đang
`is_primary`, tách riêng cho tiện) trong `show`/`store`/`update`.

## 2. Ba khóa tọa độ bản đồ

`index` và `show` giờ trả thêm:

| Khóa | Ý nghĩa |
|---|---|
| `map_latitude`, `map_longitude` | Tọa độ **để chấm lên bản đồ** |
| `map_source` | `self` \| `primary_dependent` |

Quy tắc: người có công **đã mất** (`status = deceased`) và có thân nhân chính **đã có tọa độ** →
lấy theo thân nhân chính (`map_source = primary_dependent`). Mọi trường hợp còn lại → tọa độ gốc
(`map_source = self`).

> `latitude` / `longitude` **vẫn là dữ liệu gốc**, không bị ghi đè.
> - Bản đồ, cụm marker → dùng `map_*`
> - Form nhập/sửa tọa độ → dùng cặp gốc

## 3. Export

Thêm cột **"Thân nhân chính"** (tên thân nhân), đặt trước cột "Loại đối tượng".

## Việc FE cần làm

- [ ] Thêm cột/radio "Thân nhân chính" vào bảng thân nhân trong form hồ sơ (chỉ chọn được 1 dòng).
- [ ] Màn chi tiết: hiện đầu mối liên hệ từ `primary_dependent` (tên + quan hệ + SĐT), nhất là với
      hồ sơ `deceased`.
- [ ] Bản đồ: đổi sang đọc `map_latitude`/`map_longitude` thay vì `latitude`/`longitude`.
- [ ] Khi `map_source === 'primary_dependent'`, hiển thị chú thích kiểu _"Vị trí theo thân nhân
      chính: Nguyễn Thị B"_ — nếu không, người dùng sẽ thắc mắc vì sao một người đã mất lại có vị trí
      trên bản đồ.
- [ ] Nhắc cán bộ gán thân nhân chính khi đổi trạng thái sang "Đã mất" mà hồ sơ chưa có.

## Dữ liệu cũ

Toàn bộ quan hệ hiện có mặc định `is_primary = false` — migration **không tự đoán** ai là thân nhân
chính. Hồ sơ đã mất sẽ giữ `map_source = self` cho tới khi cán bộ chỉ định.
