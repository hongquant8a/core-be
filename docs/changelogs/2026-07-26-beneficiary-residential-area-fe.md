# Người có công: tổ dân phố / thôn thành trường riêng

> Ngày tạo: 07:05:00 26/07/2026
> Cập nhật lần cuối: 07:05:00 26/07/2026

## Tóm tắt

Trước đây tổ dân phố của người có công chỉ **suy ra qua hộ gia đình** (`household.residential_area_id`) và
không hề được API CRUD trả ra. Nay `beneficiaries` có cột riêng `residential_area_id`, đối xứng với
`beneficiary_dependents` — người có công chưa gán hộ vẫn có địa bàn, và lọc/thống kê theo tổ dân phố hoạt động.

**Không breaking**: chỉ thêm field vào response và thêm 1 query param tùy chọn. Field cũ giữ nguyên.

## 1. Response `beneficiaries` — thêm 2 khóa

Áp dụng cho `index`, `show`, `store`, `update`, `PATCH /{id}/status`:

```json
{
  "id": 1,
  "household_id": 3,
  "household": { "id": 3, "head_name": "Trần Văn A" },
  "residential_area_id": 5,
  "residential_area": { "id": 5, "name": "Tổ 5" },
  "full_name": "Trần Văn B"
}
```

`residential_area` là `null` khi chưa gán. Dạng `{id, name}` giống hệt `residential_area` của
`beneficiary-dependents` và `beneficiary-households` — FE tái dùng được component hiện có.

## 2. Lọc danh sách theo tổ dân phố

```
GET /api/beneficiaries?residential_area_id=5
GET /api/beneficiaries/export?residential_area_id=5
```

Cho phép click từ số liệu thống kê `by_residential_area` sang danh sách người có công của tổ đó.

## 3. Body `store` / `update` — thêm `residential_area_id`

```json
{ "full_name": "Trần Văn B", "gender": "male", "residential_area_id": 5 }
```

`nullable|integer|exists:beneficiary_residential_areas,id`. Gửi `null` để bỏ gán.

**Lưu ý quan trọng cho FE:** trường này **độc lập với hộ** — chọn hộ (`household_id`) hoặc tạo hộ mới qua
`household` lồng **không** tự điền tổ dân phố cho người có công. Nếu form muốn hành vi "mặc định theo hộ",
FE tự prefill `residential_area_id` từ hộ đang chọn rồi cho cán bộ sửa lại.

## 4. Export / Import

- Export: thêm cột **"Tổ dân phố"** (sau "CCCD chủ hộ").
- Import: nhận cột **"Tổ dân phố"** dạng **tên** (vd `Tổ 5`), BE tra ngược về id. Không khớp thì để trống,
  **không chặn dòng**. Cột không bắt buộc. File mẫu (`/import-template`) đã có sẵn cột này.

## 5. Thống kê đổi nguồn dữ liệu

`GET /api/beneficiary-statistics` → `by_residential_area` giờ đếm theo `beneficiaries.residential_area_id`
thay vì join qua hộ. Format response **không đổi**.

Migration đã backfill từ hộ nên số liệu ngay sau khi deploy giữ nguyên. Nhưng từ nay: sửa tổ dân phố của
**hộ** sẽ không còn làm đổi số liệu người có công — phải sửa trên chính hồ sơ người có công.

## Việc FE cần làm

- [ ] Thêm cột "Tổ dân phố" ở màn danh sách người có công (đọc `residential_area.name`).
- [ ] Thêm select tổ dân phố vào form tạo/sửa hồ sơ (dropdown `GET /api/beneficiary-residential-areas`).
- [ ] Thêm bộ lọc `residential_area_id` ở danh sách.
- [ ] Cân nhắc prefill theo hộ khi cán bộ chọn hộ (xem mục 3).
