# Phòng họp không giấy — thiết kế runtime

Tài liệu chốt thiết kế cho **luồng vận hành cuộc họp tại runtime** (đã publish, đang/sắp diễn ra). Các sprint sau implement từng tab phải bám sát doc này. Các quyết định trong đây đã thống nhất với chủ dự án ngày `2026-05-06`.

> Phạm vi: 8 tab UI trong "phòng họp" (Chương trình / Tài liệu / Biểu quyết / Kết luận / Thảo luận & Chất vấn / Chủ trì / Điều hành / Màn chiếu). Không bao gồm phần "soạn thảo cuộc họp" (đã có ở doc [meeting-runtime.md](../api/meeting-runtime.md) — khái niệm "runtime" trong file đó hiểu theo nghĩa "module nâng cao", không phải "tại thời điểm chạy").

---

## 1. Triết lý chung

- **Status đơn giản, label phái sinh ở FE**: Backend giữ tối thiểu `meetings.status = draft|published|cancelled`. Phase "đang diễn ra / đã kết thúc" được FE derive từ `start_time / end_time vs now()` — tránh đồng bộ state runtime giữa BE-FE.
- **Agenda chạy theo time, không có status field**: Chỉ 1 agenda "đang diễn ra" 1 thời điểm, derived từ `agenda.start_time/end_time`. Không cần endpoint điều khiển per-agenda.
- **Authorization 2 lớp**:
    - Spatie permission: ai có quyền **soạn meeting** (compose) — admin/secretary
    - Custom middleware `meeting.role:{operator|chair}` apply trên **action endpoint runtime** — kiểm tra current user có là chair/operator của meeting cụ thể không
- **Real-time = Polling phase 1**: FE poll mỗi 3-5s ở Tab 7 + Tab 8. Phase 2 chuyển sang Laravel Reverb (broadcast event) khi UX cần — code BE chỉ cần thêm `broadcast(...)`, FE thay polling bằng Echo subscribe.

---

## 2. Vai trò trong cuộc họp

3 vai trò scope **trong 1 cuộc họp** (≠ permission Spatie scope toàn org):

| Vai trò | Identify qua | Tab thấy được |
|---|---|---|
| `chair` (Chủ trì) | `meetings.chairperson_meeting_attendee_id → attendee.user_id == auth()->id()` | 1, 2, 3, 4, 5, 6, 8 |
| `operator` (Thư ký/Điều hành) | `meetings.operator_meeting_attendee_id → attendee.user_id == auth()->id()` | 1-8 (đầy đủ) |
| `participant` (Đại biểu) | Có row `meeting_participants` link tới user | 1, 2, 3, 4 (read), 5 (đăng ký), 8 |
| `none` | Không thuộc 3 nhóm trên | Block toàn bộ runtime tabs (Spatie permission `meetings.show` vẫn cho xem ngoài context "phòng họp") |

### MeetingResource thêm field `current_user_role`

```json
{
  "id": 1,
  "title": "...",
  "current_user_role": "chair",   // chair | operator | participant | null
  ...
}
```

→ FE bind tab visibility theo field này. **Không cần CASL** cho phần runtime.

---

## 2.x. Document/Meeting visibility theo participation (đã ship 2026-05-06)

**Quy tắc**: ai thấy gì khi xem meeting / docs.

| User | Index meeting (`/api/meetings/public`) | Show meeting | Documents trong meeting |
|---|---|---|---|
| **Guest** (không token) | Chỉ public + published | Chỉ public + published, 404 nếu khác | Chỉ `is_public=true` |
| **Auth không-tham-gia** | Public + meeting họ là chair/op/participant (union) | Tương tự — meeting riêng tư của người khác = 404 | Chỉ `is_public=true` |
| **Auth là chair/op/participant** | Như trên | Truy cập được kể cả meeting `is_public=false` | **Tất cả doc** (kể cả `is_public=false`) |
| **Admin scope** (`/api/meetings`) | Mọi meeting org (cần permission) | — | Vẫn theo participation: admin không tham gia → chỉ thấy doc public (chống leak) |

Implementation: trait [`HasDocumentVisibility`](../../app/Modules/Meeting/Concerns/HasDocumentVisibility.php) có `shouldSeeAllDocs()` — apply ở 5 service methods (publicIndex / publicShow / show của Meeting + publicIndex / publicShow / index của Document).

→ FE chỉ cần 1 endpoint `/api/meetings/public` cho trang index chung — auto behavior khác nhau theo token.

---

## 3. Action endpoints mới

Tổng cộng **~7 endpoint** + **1 middleware**. Áp dụng từ Sprint 1 trở đi.

### 3.1 Middleware `meeting.role`

```php
// app/Modules/Meeting/Middleware/EnsureMeetingRole.php
Route::middleware('meeting.role:operator')
    ->patch('/meetings/{meeting}/lock-attendance', ...);
```

Logic:
1. Resolve `$meeting` từ route binding
2. Resolve `auth()->id()` 
3. Check theo role yêu cầu:
    - `operator` → `meeting.operator.user_id == auth()->id()`
    - `chair` → `meeting.chairperson.user_id == auth()->id()`
    - `operator|chair` → 1 trong 2
4. Fail → `403 Forbidden`

> Chỉ apply trên action endpoint runtime (~7 routes). Không blanket toàn module.

### 3.2 Endpoint Tab 1 — Đại biểu self-actions

| Endpoint | Middleware | Action |
|---|---|---|
| `POST /api/meeting-attendances/checkin` | auth (no role check) | Đại biểu tự điểm danh — body `{meeting_id, note?}`. Service tìm participant của user → tạo `MeetingAttendance(status=pending, checkin_method=manual)`. Idempotent (đã pending/present → 200 OK no-op). |

> Báo Vắng = `PATCH /api/meeting-participants/{id}` với `response_status=declined + absence_reason` (đã có).
> Uỷ Quyền = chưa thiết kế phase này, skip.

### 3.3 Endpoint Tab 7 — Operator điều khiển

| Endpoint | Middleware | Action |
|---|---|---|
| `PATCH /api/meeting-attendances/{id}/approve` | `meeting.role:operator` | Set status=`present`, gán `checked_in_at=now()`, `checked_in_by=auth user` |
| `PATCH /api/meeting-attendances/{id}/reject` | `meeting.role:operator` | Set status=`absent` |
| `PATCH /api/meeting-discussion-registrations/{id}/start` | `meeting.role:operator` | Set status=`called` (tương đương "đang thảo luận"), `called_at=now()` |
| `PATCH /api/meeting-discussion-registrations/{id}/complete` | `meeting.role:operator` | Set status=`completed`, `completed_at=now()` |
| `PATCH /api/meetings/{id}/lock-attendance` | `meeting.role:operator` | Set `attendance_locked=true` |
| `PATCH /api/meetings/{id}/unlock-attendance` | `meeting.role:operator` | Set `attendance_locked=false` |

> "Tạm Dừng / Quản Trị Điều Hành / Xuất Báo Cáo Nhanh" trong screenshot — defer, chưa thiết kế.

### 3.4 Endpoint Tab 7 — Operator delegate

| Endpoint | Middleware | Action |
|---|---|---|
| `PATCH /api/meetings/{id}/delegate-operator` | `meeting.role:operator\|chair` | Body `{meeting_attendee_id}` — đổi `meetings.operator_meeting_attendee_id`. Sau khi delegate, user hiện tại mất quyền operator (nếu không phải chair) — FE phải refresh meeting context |

### 3.5 QR checkin (defer Phase 2)

`POST /api/meeting-attendances/qr-checkin` — body `{token}` (token từ QR encode `{meeting_id, expires_at}`). Phase 2.

---

## 4. Schema migration cần

Tối thiểu — **chỉ 1 migration**:

```php
// 2026_05_XX_xxx_alter_meetings_add_attendance_locked.php
Schema::table('meetings', function (Blueprint $table) {
    $table->boolean('attendance_locked')->default(false)->after('published_at');
});
```

**Không cần** thêm:
- ❌ `meetings.runtime_state` — derived FE
- ❌ `meeting_agendas.status` — derived FE
- ❌ `MeetingAttendanceStatusEnum.pending` — đã có sẵn
- ❌ `MeetingDiscussionStatusEnum.speaking` — dùng `called` (đã có) hiểu là "đang phát biểu"

---

## 5. Tab → Endpoint matrix (FE reference)

| Tab | Endpoint dùng | Real-time? |
|---|---|---|
| **1. Chương trình** | `GET /meetings/{id}` (kèm `current_user_role`) + `GET /meeting-agendas?meeting_id=X` + `POST /meeting-attendances/checkin` (đại biểu) + `PATCH /meeting-participants/{id}` (báo vắng) | poll 5s |
| **2. Tài liệu** | `GET /meeting-documents?meeting_id=X` + `meeting-personal-notes.*` | static |
| **3. Biểu quyết** | `GET /meeting-vote-topics?meeting_id=X` + `POST /meeting-vote-responses` + `GET /meeting-vote-responses/stats?meeting_vote_topic_id=X` | poll 3s khi có topic `opened` |
| ~~**4. Kết luận**~~ | bỏ — merge vào Tab 2 Tài liệu (filter `meeting_document_type_id` = "Tài liệu kết luận cuộc họp") | static |
| **5. Thảo luận & Chất vấn** | `GET /meeting-discussion-registrations?meeting_id=X` + `POST` (đại biểu đăng ký, type=discussion\|question) | poll 5s |
| **6. Chủ trì** | `GET /meeting-discussion-registrations/stats?meeting_id=X` + filter theo status | poll 5s |
| **7. Điều hành** | Tất cả endpoint của Tab 6 + 7 mới ở §3.3/3.4 + `PATCH /meeting-vote-topics/{id}/open\|close` (đã có) | poll 3s |
| **8. Màn chiếu** | `GET /meetings/{id}` + agenda hiện tại + topic vote opened (FE compose từ data đã load) | poll 3s |

---

## 6. Phase 2 — Real-time bằng Reverb

Khi UX feedback yêu cầu live (vd: vote count chạy realtime trên màn chiếu):

1. `composer require laravel/reverb`
2. `php artisan reverb:install` → tạo config + 1 worker process
3. Tạo events:
    - `MeetingAgendaStarted` (FE màn chiếu update slide)
    - `MeetingVoteResponseAdded` (FE màn chiếu update count)
    - `MeetingDiscussionStarted` (FE Tab 5/7 highlight người phát biểu)
4. Service emit `broadcast(new XxxEvent($meeting))` thay vì để FE poll
5. FE: `composer require laravel-echo` + subscribe channel `meeting.{id}` (private channel — auth qua sanctum token)

Code BE chỉ thêm `broadcast(...)` calls, **không động đến** logic API. Đảm bảo Phase 1 polling code không lock-in tech.

---

## 7. Checklist implement (theo sprint)

### Sprint 1 — Foundation runtime
- [ ] Migration: `meetings.attendance_locked` boolean
- [ ] Middleware: `EnsureMeetingRole` + register vào `bootstrap/app.php`
- [ ] Policy `MeetingPolicy` (optional cho Gate-based check) — nếu middleware đủ thì skip
- [ ] `MeetingResource` thêm `current_user_role` (compute trong toArray)
- [ ] Endpoint: 7 endpoint ở §3.3/3.4
- [ ] Service `MeetingAttendanceService::checkin($meeting, $user)` + `approve($attendance)` + `reject($attendance)`
- [ ] Service `MeetingDiscussionRegistrationService::start($registration)` + `complete($registration)`
- [ ] Service `MeetingService::lockAttendance / unlockAttendance / delegateOperator`
- [ ] PermissionSeeder: thêm permission cho 7 endpoint mới (vd `meetings.lockAttendance`, `meeting-attendances.approve`, ...) — Spatie quyền chung; middleware `meeting.role` là layer thứ 2

### Sprint 2 — Self-actions ownership
- [ ] `MeetingAttendanceService::checkin` scope theo participant của auth user (không cho checkin hộ)
- [ ] `MeetingDiscussionRegistrationService::store` chỉ cho phép `meeting_participant_id` thuộc auth user (trừ operator/chair)
- [ ] `MeetingPersonalNoteService` scope theo `auth()->id()` ở mọi query

### Sprint 3 — UI integration
- [ ] FE Tab 1-6 hoàn thiện
- [ ] FE Tab 7 (Điều hành) — bind theo middleware response
- [ ] FE Tab 8 (Màn chiếu) — compose state từ existing endpoint

### Sprint 4 (optional) — Reverb
- [ ] Setup Reverb worker
- [ ] Broadcast events từ service
- [ ] FE Echo subscribe

---

## 8. Câu hỏi defer (chưa cần trả lời)

- "Tạm dừng cuộc họp" có ý nghĩa nghiệp vụ gì? Cần lưu thời gian tạm dừng?
- "Uỷ Quyền Tham Gia" — proxy attendee là gì? Đại biểu nhờ người khác đi thay?
- "Quản Trị Điều Hành" — sub-menu gì?
- "Xuất Báo Cáo Nhanh" — format gì? PDF biên bản tự động?

Khi có spec rõ → bổ sung doc này.

---

## Tham khảo

- API doc hiện hành: [docs/api/meeting-runtime.md](../api/meeting-runtime.md)
- Database: [docs/DATABASE_DESIGN.md](../DATABASE_DESIGN.md)
- Permission seed: `database/seeders/PermissionSeeder.php`
