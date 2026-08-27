# Docs — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 16:50:31 25/08/2026

Tổng quan toàn bộ tài liệu dự án. Đọc file này trước để biết nên đọc tiếp gì.

---

## Bản đồ tài liệu

```
docs/
├── README.md                          ← Bạn đang đọc — index tổng thể
│
├── guide/                             ← DÀNH CHO DEV (onboarding & quy trình)
│   ├── GETTING_STARTED.md             ← Bắt đầu từ đây nếu là dev mới
│   ├── CONTRIBUTING.md                ← Quy trình làm việc, tạo PR, đặt tên branch
│   └── TROUBLESHOOTING.md             ← Lỗi thường gặp và cách xử lý
│
├── system/                            ← KIẾN TRÚC HỆ THỐNG (tham chiếu nhanh)
│   ├── ARCHITECTURE.md                ← Tech stack, patterns, cấu trúc tổng thể
│   ├── DOMAIN_GLOSSARY.md             ← Thuật ngữ nghiệp vụ ↔ tên class/bảng
│   ├── AUTH_TENANT.md                 ← Multi-tenant, auth flow, permission model
│   └── INFRASTRUCTURE.md             ← Queue, Redis, Reverb, Horizon, deployment
│
├── database/                          ← THIẾT KẾ CSDL
│   ├── ERD.md                         ← ERD tổng thể + quy tắc đặt tên bảng
│   ├── Core.md                        ← Schema module Core
│   ├── Meeting.md                     ← Schema module Meeting
│   ├── TaskAssignment.md              ← Schema module TaskAssignment
│   └── Scheduling.md                  ← Schema module Scheduling
│
├── modules/                           ← PHÂN TÍCH MÃ NGUỒN TỪNG MODULE
│   ├── _TEMPLATE.md                   ← Template — copy khi viết docs module mới
│   └── {TênModule}/                   ← Tạo khi có module mới
│
├── decisions/                         ← QUYẾT ĐỊNH KIẾN TRÚC (ADR)
│   └── _TEMPLATE.md                   ← Template ADR
│
├── api/                               ← CHI TIẾT API ENDPOINTS (+ sso.md)
├── changelogs/                        ← CHANGELOG CHO FE (format YYYY-MM-DD-topic-fe)
├── answer/                            ← PHÂN TÍCH, GIẢI PHÁP & HƯỚNG DẪN CHUYÊN SÂU
└── superpowers/                       ← SPECS + PLANS CHO FEATURE LỚN
    ├── plans/
    └── specs/
```

---

## Đọc theo mục đích

| Mục đích | Đọc |
|---|---|
| Dev mới, chưa biết gì về project | [guide/GETTING_STARTED.md](guide/GETTING_STARTED.md) |
| Muốn biết tech stack + kiến trúc | [system/ARCHITECTURE.md](system/ARCHITECTURE.md) |
| Cần hiểu multi-tenant, auth, permission | [system/AUTH_TENANT.md](system/AUTH_TENANT.md) |
| Không biết tên class/table của khái niệm nghiệp vụ | [system/DOMAIN_GLOSSARY.md](system/DOMAIN_GLOSSARY.md) |
| Xem schema CSDL | [database/ERD.md](database/ERD.md) |
| Thêm module mới, cần docs template | [modules/_TEMPLATE.md](modules/_TEMPLATE.md) |
| Tìm hiểu 1 module cụ thể | `modules/{TênModule}/README.md` |
| Gặp lỗi không hiểu tại sao | [guide/TROUBLESHOOTING.md](guide/TROUBLESHOOTING.md) |
| Cần quy trình tạo PR, đặt tên branch | [guide/CONTRIBUTING.md](guide/CONTRIBUTING.md) |
| Xem API list | `api/` hoặc chạy `sail artisan scribe:generate` |
| BE vừa đổi API, cần migrate FE | `changelogs/` — tìm file `YYYY-MM-DD-topic-fe` |
| Luồng notification | [guides/notification-flow-behavior.md](guides/notification-flow-behavior.md) |
| Ghi lại quyết định kiến trúc | [decisions/_TEMPLATE.md](decisions/_TEMPLATE.md) |
| Hướng dẫn người dùng thao tác phân hệ Quản lý công việc | [answer/huong-dan-qlcv-chung_165031_25082026.md](answer/huong-dan-qlcv-chung_165031_25082026.md) |

---

## Quy ước tên file tài liệu

- File phân tích / giải pháp (`answer/`, `spec/`): `ten-chu-de_HHmmss_DDMMYYYY.md`
- File docs module (`modules/`): `README.md`, `models.md`, `services.md`, `events.md`
- File ADR (`decisions/`): `ADR-NNN-ten-quyet-dinh.md`
- File changelog (`changelogs/`): `YYYY-MM-DD-topic-fe.md` hoặc `.txt`

Mọi file tài liệu sinh ra phải có header:
```markdown
> Ngày tạo: HH:mm:ss DD/MM/YYYY  
> Cập nhật lần cuối: HH:mm:ss DD/MM/YYYY
```
