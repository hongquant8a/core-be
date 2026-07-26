# Quan hệ thân nhân: tách Vợ/Chồng, bổ sung Cháu và Anh/Chị/Em

> Ngày tạo: 10:20:00 26/07/2026
> Cập nhật lần cuối: 10:20:00 26/07/2026

## ⚠️ BREAKING — `spouse` không còn tồn tại

`DependentRelationshipEnum` từ **6** giá trị lên **11**:

| Value | Nhãn | Thay đổi |
|---|---|---|
| `wife` | Vợ | **mới** (tách từ `spouse`) |
| `husband` | Chồng | **mới** (tách từ `spouse`) |
| `child` | Con | giữ nguyên |
| `grandchild` | Cháu | **mới** |
| `father` | Cha | giữ nguyên |
| `mother` | Mẹ | giữ nguyên |
| `older_brother` | Anh | **mới** |
| `older_sister` | Chị | **mới** |
| `younger_sibling` | Em | **mới** |
| `foster_parent` | Người nuôi dưỡng | giữ nguyên |
| `guardian` | Người giám hộ | giữ nguyên |

`spouse` (Vợ/Chồng) **đã bỏ** — gửi lên trả **422** tại `relationship_type`.

## Ảnh hưởng

Mọi chỗ nhận `relationship_type`:

- `POST /api/beneficiary-dependents/{id}/relations`
- Mảng `dependents[]` trong `POST` / `PUT /api/beneficiaries`

Và mọi chỗ trả về `relationship_type` / `relationship_type_label`:

- `GET /api/beneficiaries/{id}` → `data.dependents[]`
- `GET /api/beneficiary-dependents/{id}` → `data.relations[]`
- Export "Thân nhân" (`Tên (Quan hệ)`) và "Người có công liên kết"
- Thống kê `by_relationship` — giờ có **11** nhóm thay vì 6

## Dữ liệu cũ

Migration `2026_07_26_100000_split_spouse_relationship_type` chuyển đổi theo **giới tính thân nhân**:

| Thân nhân | `spouse` chuyển thành |
|---|---|
| `gender = male` | `husband` |
| `gender = female` hoặc `other` | `wife` |

Chọn `wife` làm mặc định cho `other` vì người có công phần lớn là nam giới nên vợ là trường hợp áp
đảo. Dòng nào sai cán bộ sửa lại bình thường qua CRUD.

## Việc FE cần làm

- [ ] **Bỏ mọi danh sách quan hệ hardcode**, đọc từ `GET /api/beneficiary-enums` → `dependent_relationship`.
      `BeneficiaryDependentLinkDrawer.vue` đang hardcode 6 giá trị (có `spouse`) — sửa trước tiên,
      nếu không cán bộ chọn "Vợ/Chồng" sẽ nhận 422.
- [ ] Cập nhật file lang: bỏ khóa `dependent.relationship.spouse`, thêm `wife`, `husband`,
      `grandchild`, `older_brother`, `older_sister`, `younger_sibling` — hoặc bỏ hẳn và dùng `label`
      từ API.
- [ ] Biểu đồ `by_relationship` giờ có 11 cột — kiểm tra bảng màu và chiều rộng còn hợp lý.
- [ ] Dropdown 11 mục dài hơn trước — cân nhắc nhóm theo bậc (vợ/chồng · con/cháu · cha/mẹ ·
      anh/chị/em · khác) nếu UI chật.
