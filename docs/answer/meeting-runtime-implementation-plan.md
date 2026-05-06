# Meeting Room Runtime — Implementation Plan

Plan triển khai phần còn thiếu của module phòng họp không giấy. Tham chiếu:
- Design doc: [meeting-runtime-design.md](./meeting-runtime-design.md)
- API spec: [docs/api/meeting-room-fe.md](../api/meeting-room-fe.md)
- Changelog gần nhất: [docs/changelogs/2026-05-06-meeting-room-runtime-fe.md](../changelogs/2026-05-06-meeting-room-runtime-fe.md)

> Cập nhật `2026-05-06` — sau khi rà gap analysis 8 tab phòng họp + screen index + setup pages + notification.

## Trạng thái hiện tại (snapshot)

✅ **Đã xong** (Stage 1-2 admin compose):
- Trang index admin `/meetings` (CRUD + bulk + status workflow + publish gửi giấy mời)
- Tab 1 Chương trình (CRUD + reorder)
- Tab 2 Tài liệu — phần CRUD docs (chưa có nút download endpoint, chưa có ghi chú cá nhân)
- Tab 3 Biểu quyết — vote-topics CRUD + reorder + 3 toggle (chưa có vote-responses, open/close)
- Tab 4 Participants CRUD (chair/op tách riêng, không có role field)

🚧 **Chưa làm** (theo gap analysis):
- Tab 1: THAO TÁC NHANH (Điểm Danh / Báo Vắng)
- Tab 2: Ghi chú cá nhân toàn phần + nút Download trỏ endpoint mới
- Tab 3: Vote-responses (đại biểu vote + aggregate stats) + open/close vote-topics
- Tab 4 Kết luận: toàn phần
- Tab 5 Thảo luận & Chất vấn: toàn phần
- Tab 6 Chủ trì: stats view
- Tab 7 Điều hành: ~80% (BE Sprint 1 chưa ship)
- Tab 8 Màn chiếu: toàn phần
- Trang tổng quan công khai: dùng endpoint `/api/meetings/public/stats` + auth-optional
- Auth flow `/me` sync `current_organization_id` + `available_organizations`
- 5 setup catalog pages (types, locations, document-types, attendee-groups, attendees)
- Notification config + logs cho module Meeting

❌ **BE chưa có** (Sprint 1):
- Middleware `meeting.role:operator|chair`
- Endpoints `attendance/checkin /approve /reject` + `discussion-registrations/start /complete` + `meetings/lock-attendance /delegate-operator`
- `MeetingResource.current_user_role` field
- Stats `meeting-attendances` mở rộng (`present/absent/late/pending`)
- Stats `meeting-discussion-registrations` phân `type`
- Migration `meetings.attendance_locked` boolean

---

## 📅 Sprint 1 — BE foundation + critical path biểu quyết (1 tuần)

**Goal**: demo end-to-end "tạo meeting → publish → đại biểu vote → đóng phiếu → kết quả".

### BE work (blocker cho Tab 7, một phần Tab 5)

| Task | Effort | Files |
|---|---|---|
| Migration `meetings.attendance_locked` boolean | 0.5h | `database/migrations/2026_05_xx_*` |
| Middleware `EnsureMeetingRole` + alias `meeting.role` | 1h | `app/Modules/Meeting/Middleware/EnsureMeetingRole.php`, `bootstrap/app.php` |
| `MeetingResource::current_user_role` field (compute) | 0.5h | `app/Modules/Meeting/Resources/MeetingResource.php` |
| `MeetingAttendanceService`: `checkin / approve / reject` | 2h | `app/Modules/Meeting/Services/MeetingAttendanceService.php` + Controller + Routes |
| `MeetingDiscussionRegistrationService`: `start / complete` (timestamp `called_at` / `completed_at`) | 1.5h | + 2 endpoint `PATCH /{id}/start \| /complete` |
| `MeetingService`: `lockAttendance / unlockAttendance / delegateOperator` | 1h | + 3 endpoint `PATCH /{id}/lock-attendance \| /unlock-attendance \| /delegate-operator` |
| Stats mở rộng — `meeting-attendances/stats` ra `present/absent/late/pending/excused` | 1h | `MeetingAttendanceService::stats` |
| Stats — `meeting-discussion-registrations/stats` phân `type` (discussion/question) | 0.5h | `MeetingDiscussionRegistrationService::stats` |
| Permissions seed cho 7 endpoint mới | 0.5h | `database/seeders/PermissionSeeder.php` |

**Tổng: ~10h BE**

### FE work song song

| Task | Effort | Note |
|---|---|---|
| **Tab 5** đăng ký thảo luận / chất vấn (CRUD đại biểu) | 1d | BE đủ ngay (auto-derive participant) |
| **Tab 3** vote-responses (vote modal + aggregate stats + ballot_mode logic) | 1.5d | BE đủ ngay |
| **Tab 3** open/close button (operator action) | 0.5h | BE đã có |
| **Tab 5** start/complete buttons (operator action) | chờ BE Sprint 1 ship | Wire sau |

**Tổng: ~3d FE**

### Deliverable
- End-to-end voting flow chạy được
- Đại biểu đăng ký thảo luận, owner sửa được nội dung
- Operator (sau khi BE ship) gọi start/complete

---

## 📅 Sprint 2 — Hoàn thiện 6 tab còn lại (1 tuần)

Phần lớn endpoint đã có hoặc Sprint 1 ship.

| Tab | BE | FE effort | Note |
|---|---|---|---|
| **Tab 7** Điều hành | ✅ Sprint 1 | 1.5d | Wire 5-6 nút action + bind `current_user_role` ẩn/hiện |
| **Tab 4** Kết luận (CRUD + form gắn agenda) | ✅ có sẵn | 1d | Phân 2 section theo `type` (minutes/report) nếu cần |
| **Tab 6** Chủ trì stats view | ✅ Sprint 1 stats | 0.5d | Read-only filter status |
| **Tab 2** Ghi chú cá nhân (CRUD + auto-derive) | ✅ có sẵn | 1d | + attachments nếu cần |
| **Tab 2** Đổi nút Tải sang `/download` endpoint | ✅ có sẵn | 1h | Update template `<a href>` |
| **Tab 1** Báo Vắng button (PATCH participant) | ✅ có sẵn | 0.5h | Wire vào THAO TÁC NHANH |
| **Tab 1** Điểm Danh button | ✅ Sprint 1 | 0.5h | Wire `POST /attendances/checkin` |
| **Tab 8** Màn chiếu (compose từ 4 endpoint) | ✅ có sẵn | 1d | Polling 3s + slide priority logic |

**Tổng: ~5d FE, 0 BE**

---

## 📅 Sprint 3 — Trang công khai + auth flow (3-4 ngày)

| Task | BE | FE effort |
|---|---|---|
| Trang tổng quan dùng `/api/meetings/public/stats` + `/api/meetings/public` (auth-optional union) | ✅ | 1d |
| `/api/user` (`/me`) F5 sync `localStorage.orgId` + render org switcher | ✅ | 1d |
| Trang detail công khai `/api/meetings/public/{id}` (citizen UX) | ✅ | 0.5d |
| Modal documents trỏ download endpoint mới | ✅ | 0.5h |

**Tổng: ~2.5d FE**

---

## 📅 Sprint 4 — 5 Catalog setup pages (1 tuần)

Pattern giống Post categories (đã làm rồi). 5 page CRUD + 1 import.

| Resource | BE | FE effort | Note |
|---|---|---|---|
| Meeting Types | ✅ | 0.5d | Index + CRUD + bulk |
| Meeting Locations | ✅ | 0.5d | + address field |
| Meeting Document Types | ✅ | 0.5d | |
| Meeting Attendee Groups | ✅ | 0.5d | |
| Meeting Attendees + Import Excel | ✅ | 1d | + dropdown user picker |

**Tổng: ~3d FE** (parallel-able nếu component generic).

---

## 📅 Sprint 5 — Notification config + logs Meeting (1-2 ngày)

Clone 2 page TaskAssignment, đổi `moduleKey="meeting"` + base URL.

| Task | BE | FE effort |
|---|---|---|
| Page `/meetings/notification-config` (event-configs + schedules) | ✅ có sẵn | 0.5d |
| Page `/meetings/notification-logs` (logs + stats + export) | ✅ có sẵn | 0.5d |
| Menu items + CASL ability check | ✅ | 0.5h |

**Tổng: ~1d FE** nếu component generic, **~2d** nếu phải duplicate.

---

## 🗓 Tổng quan timeline

| Sprint | Thời lượng | BE work | FE work | Dependencies |
|---|---|---|---|---|
| **1** | 1 tuần | 10h | 3d | (start ngay) |
| **2** | 1 tuần | 0 | 5d | Sprint 1 BE ship |
| **3** | 3-4 ngày | 0 | 2.5d | (independent) |
| **4** | 1 tuần | 0 | 3d | (independent) |
| **5** | 1-2 ngày | 0 | 1d | (independent) |

### Tổng thời lượng

- **Solo (1 BE + 1 FE)**: ~3-4 tuần wall-clock
- **Parallel (1 BE + 2-3 FE)**: ~2 tuần (Sprint 3/4/5 chạy song song với Sprint 2)

---

## 🚦 3 lựa chọn ưu tiên

### Option A — Bám critical path (demo biểu quyết)
```
Sprint 1 → Sprint 2 → Sprint 3
```
~2.5 tuần. Defer catalog + notification cho phase sau. Trang admin compose dùng seeder data để demo.

### Option B — Đầy đủ production-ready
```
Sprint 1 → Sprint 2 → Sprint 4 → Sprint 3 → Sprint 5
```
~3-4 tuần. Catalog ưu tiên trước trang công khai vì cần dropdown data thực tế khi tạo meeting.

### Option C — Parallel teams
- **Team 1**: Sprint 1 → 2 (runtime tabs, BE + FE)
- **Team 2**: Sprint 4 + 5 (catalog + notification, FE only)
- **Team 3**: Sprint 3 (trang công khai, FE only)

→ Rút ngắn còn ~2 tuần nếu đủ resource.

---

## 📋 Ngoài plan — defer (chưa cần ngay)

Các tính năng chưa có spec rõ, defer cho đến khi nghiệp vụ làm rõ:

- **Tab 7 THAO TÁC NHANH**: "Tạm Dừng Cuộc Họp", "Quản Trị Điều Hành", "Xuất Báo Cáo Nhanh" — chưa có endpoint, chưa có UX flow
- **Tab 1**: "Uỷ Quyền Tham Gia" — proxy attendee, design chưa rõ
- **Phase 2 real-time**: Laravel Reverb broadcast event (thay polling) — chỉ làm khi UX feedback yêu cầu
- **QR check-in**: `POST /meeting-attendances/qr-checkin` — Phase 2 Sprint 1+
- **Composite projector endpoint**: `GET /meetings/{id}/projector-state` — Phase 2 nếu polling 4 endpoint quá tốn

---

## ✅ Definition of Done (mỗi sprint)

- [ ] BE: lint pass, migrate sạch, permission seed cập nhật, doc API/Scribe regenerate
- [ ] FE: tab/page render đúng theo screenshot, polling intervals đúng, CASL gate cho action button
- [ ] Test happy path + 1-2 edge case (vd: vote khi topic closed, sửa note của người khác, ...)
- [ ] Update `docs/api/meeting-room-fe.md` nếu có endpoint thay đổi
- [ ] Commit theo convention `feat(meeting): ...` + changelog FE-aware nếu breaking

---

## 🔗 Tài liệu liên quan

- [meeting-runtime-design.md](./meeting-runtime-design.md) — Thiết kế tổng thể (state machine, role mapping, real-time)
- [meeting-room-fe.md](../api/meeting-room-fe.md) — API tham chiếu cho FE (theo screen/tab)
- [meeting-runtime.md](../api/meeting-runtime.md) — API spec đầy đủ (theo resource)
- [2026-05-06-meeting-room-runtime-fe.md](../changelogs/2026-05-06-meeting-room-runtime-fe.md) — Changelog FE
- Spec gốc: [giai-phap-phong-hop-khong-giay-don-gian-chinh-thuc.md](../../giai-phap-phong-hop-khong-giay-don-gian-chinh-thuc.md)
