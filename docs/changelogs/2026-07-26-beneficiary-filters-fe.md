# Người có công: mở rộng tìm kiếm + lọc theo loại đối tượng

> Ngày tạo: 10:40:00 26/07/2026
> Cập nhật lần cuối: 10:40:00 26/07/2026

**Không breaking** — chỉ thêm khả năng, tham số cũ giữ nguyên hành vi.

Áp dụng cho cả ba endpoint dùng chung bộ lọc: `GET /api/beneficiaries`,
`GET /api/beneficiaries/stats`, `GET /api/beneficiaries/export`.

## 1. `search` quét rộng hơn: 2 cột → 6 cột

| Trước | Nay |
|---|---|
| Họ tên người có công | Họ tên người có công |
| CCCD người có công | CCCD người có công |
| | **SĐT người có công** |
| | **Họ tên thân nhân** |
| | **CCCD thân nhân** |
| | **SĐT thân nhân** |

Gõ một cái tên, một số CCCD hay một số điện thoại bất kỳ là ra hồ sơ liên quan — cán bộ không cần
biết mảnh thông tin đó thuộc về người có công hay thân nhân của họ.

**FE nên đổi placeholder** cho đúng phạm vi mới, ví dụ:
_"Tìm theo tên, CCCD, SĐT của người có công hoặc thân nhân"_.

Hồ sơ khớp qua thân nhân trông sẽ "không liên quan" nếu chỉ nhìn cột họ tên. Cân nhắc hiển thị lý do
khớp, hoặc ít nhất giữ cột "Thân nhân" (`dependents_count`) để người dùng đoán được.

## 2. Thêm bộ lọc `type` — loại đối tượng

```
GET /api/beneficiaries?type=war_invalid
```

Value lấy từ `GET /api/beneficiary-enums` → `beneficiary_type` (12 nhóm theo Pháp lệnh
02/2020/UBTVQH14).

Một người có thể mang **nhiều** loại đối tượng (vừa thương binh vừa nạn nhân chất độc hóa học) —
lọc theo bất kỳ loại nào của họ đều khớp.

## 3. Bộ lọc đầy đủ hiện tại

| Tham số | Ghi chú |
|---|---|
| `search` | 6 cột, xem mục 1 |
| `status` | `pending` \| `active` \| `deceased` \| `moved_out` \| `suspended` |
| `type` | **mới** — loại đối tượng |
| `household_id` | hộ gia đình |
| `residential_area_id` | tổ dân phố / thôn (thêm 26/07, xem changelog riêng) |
| `from_date` / `to_date` | khoảng `created_at` |
| `sort_by` | `id` \| `full_name` \| `date_of_birth` \| `status` \| `created_at` \| `updated_at` |
| `sort_order` | `asc` \| `desc` |
| `limit` | `-1` = không phân trang |

## Việc FE cần làm

- [ ] Đổi placeholder ô tìm kiếm cho đúng phạm vi mới.
- [ ] Thêm dropdown "Loại đối tượng" vào thanh bộ lọc (nguồn: enum `beneficiary_type`).
- [ ] Truyền `type` sang cả `stats` và `export` để KPI và file xuất khớp bảng đang xem.
