# Trang thống kê (Dashboard) người có công (FE)

> Ngày tạo: 15:34:17 16/08/2026  
> Cập nhật lần cuối: 15:34:17 16/08/2026

**Mức độ:** thêm mới, không phá vỡ tương thích. Backend bổ sung **một endpoint** phục vụ trang
thống kê. Endpoint `GET /api/beneficiaries/stats` cũ **giữ nguyên** (dùng cho badge/số liệu nhanh
đầu màn danh sách) — dashboard là endpoint riêng, giàu dữ liệu hơn.

Tài liệu API đầy đủ (sau khi chạy `scribe:generate`): [api/beneficiary.md](../api/beneficiary.md).

---

## 1. Endpoint

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `/api/beneficiaries/dashboard` |
| **Auth** | `auth:sanctum` — bắt buộc |
| **Header** | `X-Organization-Id: <id tổ chức>` (bắt buộc, như mọi endpoint tenant) |
| **Permission** | `beneficiaries.dashboard` (quyền RIÊNG, tách khỏi `beneficiaries.stats` của badge số liệu nhanh) |

### Query params (đều tùy chọn)

| Param | Kiểu | Ý nghĩa |
|---|---|---|
| `from_date` | date `Y-m-d` | Lọc hồ sơ theo ngày tạo, từ ngày |
| `to_date` | date `Y-m-d` | Lọc hồ sơ theo ngày tạo, đến ngày |
| `residential_area_id` | integer | Chỉ xem một tổ dân phố/thôn |

Không truyền param nào = thống kê toàn bộ hồ sơ của tổ chức.

> **Lưu ý:** biểu đồ `created_trend_12m` **cố ý bỏ qua** `from_date`/`to_date` (luôn phủ 12 tháng
> gần nhất), chỉ chịu tác động của `residential_area_id`. Mọi phần còn lại áp đủ 3 bộ lọc.

---

## 2. Cấu trúc response tổng thể

```jsonc
{
  "success": true,
  "data": {
    "kpis":   { ... },        // 6 chỉ số tổng
    "charts": { ... },        // 8 biểu đồ
    "tables": { ... }         // 3 bảng tổng hợp
  }
}
```

Toàn bộ dữ liệu biểu đồ đã **gán nhãn tiếng Việt sẵn** và theo format `[{ label, value }]` (trừ
tháp tuổi và các bảng) — render thẳng, không cần map lại `value → label`.

---

## 3. `kpis` — dải thẻ số (6 thẻ)

```jsonc
"kpis": {
  "total": 120,                    // Tổng hồ sơ (sau lọc)
  "new_in_30_days": 8,             // Hồ sơ tạo mới trong 30 ngày gần nhất
  "total_type_relations": 140,     // Tổng lượt "đối tượng" đang quản lý (1 hồ sơ có thể nhiều loại)
  "total_dependents": 210,         // Tổng thân nhân
  "with_coordinates_percent": 83.3,// % hồ sơ có đủ toạ độ (0..100, làm tròn 1 chữ số)
  "incomplete_count": 25           // Số hồ sơ thiếu CCCD hoặc năm sinh hoặc toạ độ
}
```

**Gợi ý hiển thị:** 6 thẻ số (KPI card). `with_coordinates_percent` hiển thị kèm `%`;
`incomplete_count` nên bấm được để nhảy tới bảng "Hồ sơ cần hoàn thiện" bên dưới.

---

## 4. `charts` — 8 biểu đồ

Mỗi mô tả gồm: **shape dữ liệu**, **loại biểu đồ đề xuất**, **cách map**.

### 4.1. `by_gender` — Cơ cấu giới tính

```jsonc
"by_gender": [ { "label": "Nam", "value": 90 }, { "label": "Nữ", "value": 30 } ]
```
- **Loại:** Donut/Pie.
- Nhãn có thể gồm `Nam`, `Nữ`, `Khác`, `Chưa xác định` (hồ sơ chưa nhập giới tính).

### 4.2. `by_type` — Cơ cấu theo loại đối tượng ⭐ (quan trọng nhất)

```jsonc
"by_type": [ { "label": "Thương binh", "value": 55 }, { "label": "Bệnh binh", "value": 30 } ]
```
- **Loại:** Cột ngang (horizontal bar), sắp sẵn giảm dần.
- `value` = số **hồ sơ** thuộc loại đó (đã đếm distinct, không nhân đôi khi 1 người thuộc nhiều loại).
- Tổng các `value` có thể **lớn hơn** `kpis.total` vì một hồ sơ thuộc nhiều loại — đây là đúng, đừng tính % trên tổng cột.

### 4.3. `by_residential_area` — Phân bố theo tổ dân phố (Top 10 + Khác)

```jsonc
"by_residential_area": [ { "label": "Tổ dân phố 1", "value": 40 }, { "label": "Khác", "value": 12 } ]
```
- **Loại:** Cột đứng.
- Backend **đã gộp** phần ngoài Top 10 vào mục `"Khác"`. Hồ sơ chưa gán tổ → nhãn `"Chưa phân tổ"`.

### 4.4. `by_age_group` — Phân bố nhóm tuổi

```jsonc
"by_age_group": [
  { "label": "0-29", "value": 2 }, { "label": "30-44", "value": 5 },
  { "label": "45-59", "value": 18 }, { "label": "60-74", "value": 40 },
  { "label": "75-89", "value": 30 }, { "label": "90+", "value": 10 },
  { "label": "Không rõ", "value": 15 }
]
```
- **Loại:** Cột đứng. Thứ tự nhóm giữ nguyên như trên (đừng sort lại theo value).
- `"Không rõ"` = hồ sơ chưa có năm sinh — **luôn giữ**, đừng ẩn.
- Tuổi tính theo **năm hiện tại − năm sinh** tại thời điểm gọi API.

### 4.5. `age_gender_pyramid` — Tháp tuổi × giới

```jsonc
"age_gender_pyramid": [
  { "age_group": "75-89", "male": 20, "female": 10, "other": 0, "unknown": 0 },
  ...
]
```
- **Loại:** Tháp dân số (population pyramid) — cột ngang đối xứng: **Nam bên trái (giá trị âm)**, **Nữ bên phải**.
- Mỗi phần tử là một nhóm tuổi (cùng bộ nhãn với 4.4, gồm cả `"Không rõ"`).
- `other`/`unknown` thường nhỏ — có thể gộp vào chú thích hoặc bỏ qua tùy thiết kế; nhưng dữ liệu vẫn trả để tổng khớp.
- Mẹo render: với thư viện bar ngang, đặt `male` thành số âm (`-male`) để cột đổ về trái.

### 4.6. `created_trend_12m` — Tiến độ nhập hồ sơ 12 tháng

```jsonc
"created_trend_12m": [ { "label": "09/2025", "value": 3 }, ..., { "label": "08/2026", "value": 8 } ]
```
- **Loại:** Đường (line) hoặc vùng (area). Luôn đủ **12 mốc** (tháng không có hồ sơ = 0), theo thứ tự thời gian tăng dần.
- ⚠️ **Cảnh báo nghiệp vụ — bắt buộc ghi nhãn:** đây là **tiến độ NHẬP LIỆU của cán bộ** (theo `created_at`), **KHÔNG** phải số người có công tăng/giảm thực tế. Ghi chú tiêu đề kiểu *"Số hồ sơ được nhập vào hệ thống theo tháng"* để lãnh đạo không đọc nhầm.
- Không chịu tác động của `from_date`/`to_date` (xem mục 1).

### 4.7. `dependents_by_relationship` — Thân nhân theo mối quan hệ

```jsonc
"dependents_by_relationship": [ { "label": "Con", "value": 80 }, { "label": "Vợ", "value": 45 } ]
```
- **Loại:** Donut hoặc cột ngang. Sắp sẵn giảm dần.
- Thân nhân chưa gán quan hệ → nhãn `"Chưa phân loại"`.

### 4.8. `data_quality` — Chất lượng dữ liệu

```jsonc
"data_quality": [
  { "label": "Đủ toạ độ", "value": 100 }, { "label": "Thiếu toạ độ", "value": 20 },
  { "label": "Thiếu CCCD/CMND", "value": 15 }, { "label": "Thiếu năm sinh", "value": 12 }
]
```
- **Loại:** Cột đứng (4 cột độc lập).
- Đây là **4 chỉ số độc lập**, **không cộng lại thành 100%** — một hồ sơ có thể thiếu đồng thời nhiều thứ. Đừng vẽ dạng pie/stacked-100%.

---

## 5. `tables` — 3 bảng tổng hợp

### 5.1. `area_type_matrix` — Ma trận Tổ dân phố × Loại đối tượng

```jsonc
"area_type_matrix": {
  "types": ["Bệnh binh", "Thương binh"],           // tên cột, đã sắp a→z
  "rows": [
    { "area": "Tổ dân phố 1", "counts": { "Bệnh binh": 5, "Thương binh": 20 }, "total": 25 },
    { "area": "Chưa phân tổ", "counts": { "Bệnh binh": 0, "Thương binh": 3 },  "total": 3 }
  ]
}
```
- **Render:** bảng chéo. Header động lấy từ `types`; mỗi dòng đọc `counts[typeName]` (mặc định 0 nếu thiếu key), cột cuối là `total`. Dòng đã sắp theo `total` giảm dần.
- `counts` đếm distinct hồ sơ. Nên cho **xuất Excel** ở bảng này (báo cáo hành chính hay dùng).

### 5.2. `type_summary` — Tổng hợp theo loại đối tượng

```jsonc
"type_summary": [ { "name": "Thương binh", "total": 55, "percent": 45.8 } ]
```
- **Render:** bảng 3 cột: Loại · Số lượng · Tỉ lệ %. `percent` = `total / kpis.total × 100` (đã làm tròn 1 chữ số).

### 5.3. `incomplete_profiles` — Hồ sơ cần hoàn thiện

```jsonc
"incomplete_profiles": [
  { "id": 12, "full_name": "Nguyễn Văn A", "residential_area": "Tổ dân phố 1", "missing": ["Toạ độ"] },
  { "id": 30, "full_name": "Trần Thị B",  "residential_area": null,          "missing": ["CCCD/CMND", "Năm sinh"] }
]
```
- **Render:** bảng danh sách, cột `missing` hiển thị dạng chip/tag. Mỗi dòng bấm được → mở màn sửa hồ sơ `id`.
- **Tối đa 50 dòng** (cắt ở backend). Tổng số đầy đủ nằm ở `kpis.incomplete_count` — nếu `incomplete_count > 50`, hiện dòng *"…và N hồ sơ khác"* + nút lọc sang màn danh sách.
- `residential_area` có thể `null` (chưa gán tổ).

---

## 6. Gợi ý bố cục trang

```
┌──────────────────────────── Bộ lọc: [Từ ngày] [Đến ngày] [Tổ dân phố ▾] ────────────────────────────┐
│ [Tổng hồ sơ] [Mới 30 ngày] [Đối tượng] [Thân nhân] [% có toạ độ] [Cần hoàn thiện]                     │ ← kpis
├──────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [Donut giới tính]        [Cột ngang loại đối tượng ⭐]        [Donut thân nhân theo quan hệ]           │
│ [Cột nhóm tuổi]          [Tháp tuổi × giới]                   [Cột chất lượng dữ liệu]                 │
│ [Đường: tiến độ nhập 12 tháng — cả chiều rộng]                                                          │
│ [Cột: phân bố theo tổ dân phố — cả chiều rộng]                                                          │
├──────────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [Bảng ma trận Tổ × Loại + nút Xuất Excel]                                                               │
│ [Bảng tổng hợp theo loại]            [Bảng hồ sơ cần hoàn thiện]                                        │
└──────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 7. Ví dụ gọi API

```ts
async function loadDashboard(params: {
  from_date?: string; to_date?: string; residential_area_id?: number;
}) {
  const qs = new URLSearchParams(
    Object.entries(params).filter(([, v]) => v != null).map(([k, v]) => [k, String(v)])
  );
  const res = await http.get(`/api/beneficiaries/dashboard?${qs}`, {
    headers: { 'X-Organization-Id': currentOrgId },
  });
  return res.data.data; // { kpis, charts, tables }
}
```

Ví dụ đổ vào một thư viện biểu đồ bất kỳ (dữ liệu đã `{label, value}` nên map trực tiếp):

```ts
const { charts } = await loadDashboard({});

// Donut giới tính
donut(charts.by_gender.map(d => ({ name: d.label, value: d.value })));

// Tháp tuổi × giới (bar ngang đối xứng)
pyramid(charts.age_gender_pyramid.map(g => ({
  group: g.age_group,
  nam: -g.male,   // âm để đổ về trái
  nu:  g.female,
})));
```

---

## 8. Loading & empty state

- Tổ chức chưa có hồ sơ nào: `kpis.total = 0`, các mảng biểu đồ **rỗng** (`[]`), bảng rỗng. FE hiển thị "Chưa có dữ liệu" cho từng widget thay vì để trống.
- Endpoint tổng hợp ~12–15 truy vấn, chỉ **một request** cho cả trang — nên hiển thị một skeleton chung, không cần loading từng widget.
- Khi đổi bộ lọc: gọi lại đúng endpoint này với query mới.
