# Contributing — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Quy trình làm việc, đặt tên, tạo PR cho dự án QLCV backend.

---

## Quy trình làm việc

```
1. Nhận task (Jira / Trello / issue)
2. Tạo branch từ main/develop
3. Implement + viết/cập nhật test
4. Cập nhật docs liên quan
5. Tạo PR → review → merge
```

---

## Đặt tên branch

```
{type}/{ticket-id}-{mo-ta-ngan}
```

| Type | Khi dùng |
|---|---|
| `feat` | Tính năng mới |
| `fix` | Sửa bug |
| `refactor` | Tái cấu trúc không thay đổi behavior |
| `docs` | Chỉ cập nhật tài liệu |
| `chore` | Cấu hình, dependencies |

Ví dụ: `feat/TA-123-them-export-bao-cao`, `fix/MT-45-deadline-reminder-sai-gio`

---

## Checklist trước khi tạo PR

**Code:**
- [ ] Test pass: `sail artisan test`
- [ ] Không có `dd()`, `dump()`, `var_dump()` còn sót
- [ ] Không có `console.log()` trong code PHP
- [ ] Namespace, đặt tên theo convention (xem [GETTING_STARTED.md](GETTING_STARTED.md) section 10)

**Database:**
- [ ] Migration có rollback (`down()` method)
- [ ] `DATABASE_DESIGN.md` → [database/ERD.md](../database/ERD.md) cập nhật nếu có schema mới

**API & Docs:**
- [ ] FormRequest có `messages()` tiếng Việt + `attributes()` + `bodyParameters()`
- [ ] Controller có PHPDoc đủ cho Scribe
- [ ] Sau thay đổi API: `sail artisan scribe:generate` và kiểm tra
- [ ] Nếu có API change ảnh hưởng FE: tạo file `docs/changelogs/YYYY-MM-DD-topic-fe.md`

**Permission:**
- [ ] Resource/action mới đã thêm vào `PermissionSeeder`
- [ ] LogActivity đã cập nhật `resourceLabel()` nếu có resource mới

**Multi-tenant:**
- [ ] Model nghiệp vụ có `HasOrganizationScope`
- [ ] `store`/`import` gán `organization_id` từ context, không nhận từ client
- [ ] Bulk/single ID action đã chặn cross-tenant

---

## Quy tắc commit message

```
{type}: {mô tả ngắn} [{ticket-id}]
```

Ví dụ:
```
feat: thêm export báo cáo tổng hợp [TA-123]
fix: sửa lệch giờ remind_at khi timezone UTC [MT-45]
docs: cập nhật ERD module Scheduling
```

---

## Khi thêm module mới

1. Tạo cấu trúc thư mục theo convention (xem `CLAUDE.md` mục 2)
2. Copy [modules/_TEMPLATE.md](../modules/_TEMPLATE.md) → `docs/modules/{TênModule}/README.md`
3. Điền đầy đủ thông tin vào template
4. Thêm DB schema vào `docs/database/`
5. Cập nhật `docs/README.md` bản đồ tài liệu nếu cần

---

## Review PR

Reviewer check:
1. Convention tuân thủ CLAUDE.md
2. Không có business logic trong Controller
3. Transaction bao đúng chỗ
4. Tenant isolation đúng
5. Test có cover case chính + edge case multi-tenant
