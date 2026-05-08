# WebSocket — Phòng họp không giấy (Reverb)

Tài liệu hướng dẫn FE implement WebSocket cho realtime trong phòng họp. BE đã setup Laravel Reverb (2026-05-07), 11 event broadcast trên private channel `meeting.{id}`.

> **Lý do dùng WS thay polling**: realtime <1s cho popup biểu quyết, count vote live, list điểm danh chờ duyệt, highlight chương trình, ... Polling 3s vẫn để được làm fallback nếu FE chưa kịp tích hợp Echo, **không bị xoá** — REST endpoints `GET /meetings/{id}` etc. tồn tại song song.

---

## 1. Setup BE (đã xong)

- `composer require laravel/reverb` đã cài.
- `.env` đã có 6 biến: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`.
- `BROADCAST_CONNECTION=reverb`.
- `routes/channels.php` định nghĩa private channel `meeting.{meetingId}`.
- 11 event class trong `app/Modules/Meeting/Events/`.
- `broadcast(...)` calls trong các service runtime (vote, attendance, discussion, highlight, end-early).

### Khởi động Reverb worker

```bash
php artisan reverb:start
```

Worker chạy mặc định ở `0.0.0.0:8080`. Production: chạy như supervisor process (giống queue worker), reverse-proxy qua Nginx + TLS:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Khi đó `.env` prod set `REVERB_HOST=phonghop.example.com`, `REVERB_PORT=443`, `REVERB_SCHEME=https`.

---

## 2. Setup FE

### 2.1 Cài package

```bash
npm install --save laravel-echo pusher-js
```

### 2.2 Khởi tạo Echo (Vue/React)

`src/lib/echo.js`:

```js
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

export const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  wssPort: import.meta.env.VITE_REVERB_PORT,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: '/api/broadcasting/auth',           // BE prefix 'api' + middleware ['api', 'auth:sanctum']
  auth: {
    headers: {
      Authorization: `Bearer ${getToken()}`,
      Accept: 'application/json',
      'X-Organization-Id': getOrgId(),              // optional, consistent với REST khác
    },
  },
})
```

> **Auth flow**: FE Echo gọi `POST /api/broadcasting/auth` với body `{ socket_id, channel_name }`. Middleware `auth:sanctum` xác thực Bearer token → callback `meeting.{meetingId}` trong [routes/channels.php](../../routes/channels.php) check user có là chair/op/participant của meeting → return `true` (200 + auth signature) hoặc `false` (403).

### 2.3 Biến môi trường FE

`.env` (Vite):

```
VITE_REVERB_APP_KEY=<lấy từ BE .env REVERB_APP_KEY>
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

> Production: chỉnh `VITE_REVERB_HOST` thành domain Nginx, port 443, scheme `https`.

### 2.4 Auth endpoint cho private channel

- Route: `POST /api/broadcasting/auth` (prefix `api`).
- Middleware: `api` + `auth:sanctum` — FE phải gửi `Authorization: Bearer {token}`. **Không** dùng cookie/CSRF/web session.
- Register: trong [bootstrap/app.php](../../bootstrap/app.php) qua `->withBroadcasting('routes/channels.php', ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']])`.
- Callback: [routes/channels.php](../../routes/channels.php) — xác thực user là chair / operator / participant của meeting (FK match qua `meeting_attendees.user_id` + `meeting_participants`). Spatie role check **không** áp dụng (xem comment trong file để biết lý do).

---

## 3. Channel + 11 Events

**Channel**: `private-meeting.{meetingId}` (Echo dùng tên `meeting.{meetingId}`, prefix `private-` được Echo tự thêm).

| # | Event name | Trigger | Payload |
|---|---|---|---|
| 1 | `vote-topic.opened` | Operator bấm `/open` vote topic | `id, meeting_id, meeting_agenda_id, title, description, duration_minutes, vote_type, ballot_mode, show_result_on_projector, show_result_on_personal_device, phase='opened', opened_at, expires_at_iso` |
| 2 | `vote-response.added` | Đại biểu bỏ phiếu / đổi phiếu | `meeting_vote_topic_id, option, previous_option, voted_at` (anonymized — không có participant info). `previous_option`=null nếu vote lần đầu, string nếu đổi ý. **BE skip broadcast nếu spam cùng option** (no-op, không cộng dồn counter). |
| 3 | `vote-topic.closed` | Operator bấm `/close` | `id, meeting_id, show_result_on_projector, show_result_on_personal_device, phase='closed', closed_at` |
| 4 | `meeting.agenda-highlighted` | Operator highlight chương trình | `meeting_id, current_meeting_agenda_id` (null = bỏ highlight) |
| 5 | `meeting.discussion-highlighted` | Operator highlight phát biểu | `meeting_id, current_meeting_discussion_registration_id` |
| 6 | `discussion-registration.created` | Đại biểu đăng ký phát biểu/chất vấn | `id, meeting_id, meeting_agenda_id, meeting_participant_id, participant_name, type, content, media_id, status, sort_order` |
| 7 | `discussion-registration.completed` | Operator đánh dấu xong | `id, meeting_id, type, status, completed_at` |
| 8 | `attendance.checked-in` | Đại biểu submit điểm danh / báo vắng | `id, meeting_id, meeting_participant_id, participant_name, status, checked_in_at` |
| 9 | `attendance.approved` | Operator approve điểm danh | `id, meeting_id, meeting_participant_id, status` |
| 10 | `attendance.rejected` | Operator reject điểm danh | `id, meeting_id, meeting_participant_id, status` |
| 11 | `meeting.ended-early` | Operator bấm `/end-early` | `meeting_id, end_time` |

> **Convention tên event**: dot-notation `resource.action`. Khi listen ở Echo phải prefix với `.` để Laravel gọi đúng event raw name (không namespace App\\Events\\...).

### ⚠️ Đóng popup biểu quyết — 2 case

| Case | Trigger | Có WS event? | FE handle |
|---|---|---|---|
| **A. Manual close** | Operator bấm `PATCH /meeting-vote-topics/{id}/close` | ✅ `vote-topic.closed` | Listen event → đóng popup |
| **B. Timeout** | `now > opened_at + duration_minutes` | ❌ Không có event | FE `setTimeout(closeVotePopup, expires_at_iso - now)` ngay khi mở popup |

**Lý do không có event timeout**: BE derive `phase='closed'` runtime khi có request, không có background worker bắn event. Khi đại biểu submit vote sau timeout → BE trả 422 (đã chặn). FE phải đóng popup local trước khi user kịp submit muộn.

**Pattern đề xuất** cho mọi popup vote:
```js
function showVotePopup(topic) {
  openModal(topic)

  // 1. Auto-close khi timeout (case B)
  if (topic.expires_at_iso) {
    const ms = new Date(topic.expires_at_iso).getTime() - Date.now()
    const timer = setTimeout(() => closeModal(topic.id, 'timeout'), Math.max(0, ms))
    onModalClose(() => clearTimeout(timer))
  }

  // 2. Auto-close khi nhận event manual close (case A) — handled ở Echo listener riêng
}
```

### Anonymized payload cho `vote-response.added`

Theo spec line 166 + Commit A — broadcast trên channel chung không leak danh tính. Phía FE:

- **Đại biểu / projector**: dùng event để **tăng counter +1** trên UI (option đã có trong payload).
- **Chair/operator** muốn list per-person → fetch REST `GET /meeting-vote-responses?meeting_vote_topic_id=X` (BE đã enforce role gate).

---

## 4. FE implementation theo tab

### 4.1 Subscribe + cleanup khi enter/leave meeting

```js
import { echo } from '@/lib/echo'

// Khi user vào meeting detail
const channel = echo.private(`meeting.${meetingId}`)

// Khi component unmount / user rời meeting
echo.leave(`meeting.${meetingId}`)
```

### 4.2 Tab 1 — Chương trình (đại biểu)

```js
channel
  .listen('.attendance.checked-in', (e) => {
    // Cập nhật badge "Đã điểm danh" cho đại biểu khác (FE muốn show tổng)
    store.upsertAttendance(e)
  })
  .listen('.attendance.approved', (e) => store.upsertAttendance(e))
  .listen('.attendance.rejected', (e) => store.upsertAttendance(e))
```

### 4.3 Tab 3 — Biểu quyết (đại biểu)

**Cấu trúc popup vote** (gộp 2 phần trong cùng modal):
- **Section input bỏ phiếu** → **luôn bật** khi nhận `vote-topic.opened` để đại biểu chọn option. Không phụ thuộc flag nào.
- **Section kết quả tổng hợp** (count live + final stats) → toggle trong cùng popup theo `show_result_on_personal_device=true`. Spec line 145, 285, 559-561: cờ này chỉ control hiển thị **kết quả tổng hợp**, không liên quan đến input vote.

```js
channel
  .listen('.vote-topic.opened', (e) => {
    // POPUP gộp 2 phần:
    //  1. Input bỏ phiếu (LUÔN có) — đại biểu chọn option
    //  2. Section "Kết quả tổng hợp" trong cùng popup — chỉ hiện nếu show_result_on_personal_device=true
    showVotePopup(e, {
      showLiveResult: e.show_result_on_personal_device,  // toggle section kết quả trong popup
    })

    // Countdown anchored absolute time. Hết giờ → FE TỰ ĐÓNG popup (BE không bắn timeout event).
    if (e.expires_at_iso) {
      const remainingMs = new Date(e.expires_at_iso).getTime() - Date.now()
      setTimeout(() => closeVotePopup(e.id, 'timeout'), Math.max(0, remainingMs))
    }
  })
  .listen('.vote-topic.closed', (e) => {
    // Đóng phần input vote (đại biểu không còn vote được).
    // Section kết quả tổng hợp giữ lại trong popup nếu flag=true (hiện final stats),
    // hoặc ẩn cùng popup tùy UX FE.
    closeVotePopup(e.id, 'manual_close')
    if (e.show_result_on_personal_device) {
      fetchStats(e.id)  // GET /meeting-vote-responses/stats — BE 403 nếu flag=false
    }
  })
  .listen('.vote-response.added', (e) => {
    // Nếu đại biểu đổi ý (previous_option != null) → giảm counter cũ trước khi tăng cái mới.
    if (e.previous_option) {
      store.decrementVoteCount(e.meeting_vote_topic_id, e.previous_option)
    }
    store.incrementVoteCount(e.meeting_vote_topic_id, e.option)
    // BE skip broadcast nếu user spam cùng option → counter không bị cộng dồn.
  })
```

### 4.4 Tab 5 — Thảo luận & Chất vấn

```js
channel
  .listen('.discussion-registration.created', (e) => {
    store.upsertRegistration(e)  // append vào list realtime
  })
  .listen('.discussion-registration.completed', (e) => {
    store.markCompleted(e.id, e.completed_at)
  })
```

### 4.5 Tab 6 — Chủ trì (dashboard)

```js
// Recompute stats khi có event vì BE chỉ trả model, không trả stats trong payload.
// Có 2 cách:
//   (a) Fetch lại /stats endpoint khi có event (đơn giản, tốn 1 request mỗi event)
//   (b) Tự increment counter local từ payload (rẻ hơn, code phức tạp)
const refetchStats = debounce(() => {
  api.get('/meeting-attendances/stats?meeting_id=' + meetingId)
  api.get('/meeting-discussion-registrations/stats?meeting_id=' + meetingId)
}, 300)

channel
  .listen('.attendance.checked-in', refetchStats)
  .listen('.attendance.approved', refetchStats)
  .listen('.attendance.rejected', refetchStats)
  .listen('.discussion-registration.created', refetchStats)
  .listen('.discussion-registration.completed', refetchStats)
```

### 4.6 Tab 7 — Điều hành (operator)

```js
channel
  // List "đang chờ duyệt" cập nhật mỗi khi đại biểu submit
  .listen('.attendance.checked-in', (e) => {
    if (e.status === 'pending') store.addToPendingApproval(e)
    else store.removeFromPendingApproval(e.id)
  })
  // Khi operator (chính mình hoặc khác device) approve/reject — refetch list
  .listen('.attendance.approved', (e) => store.removeFromPendingApproval(e.id))
  .listen('.attendance.rejected', (e) => store.removeFromPendingApproval(e.id))
  // Đăng ký phát biểu mới hiện ngay trên Tab điều hành
  .listen('.discussion-registration.created', (e) => store.addRegistration(e))
  .listen('.discussion-registration.completed', (e) => store.markCompleted(e.id))
  // Vote count live cho operator dashboard. Đổi ý: dec cái cũ + inc cái mới.
  .listen('.vote-response.added', (e) => {
    if (e.previous_option) store.decrementVoteCount(e.meeting_vote_topic_id, e.previous_option)
    store.incrementVoteCount(e.meeting_vote_topic_id, e.option)
  })
```

### 4.7 Tab 8 — Màn chiếu (Projector)

**Cấu trúc slide vote trên Tab 8** (gộp 2 phần — cùng pattern Tab 3):
- **Section thông báo biểu quyết** (title + mô tả + countdown) → **luôn bật** khi nhận `vote-topic.opened` để mọi người trong phòng biết "đang biểu quyết X". Không phụ thuộc flag.
- **Section kết quả tổng hợp** (count live + final stats) → toggle trong cùng slide theo `show_result_on_projector=true`. Cờ này **chỉ control hiển thị kết quả tổng hợp**, không liên quan section thông báo.

```js
channel
  .listen('.meeting.agenda-highlighted', (e) => {
    store.setCurrentAgenda(e.current_meeting_agenda_id)
  })
  .listen('.meeting.discussion-highlighted', (e) => {
    store.setCurrentDiscussion(e.current_meeting_discussion_registration_id)
  })
  .listen('.vote-topic.opened', (e) => {
    // SLIDE VOTE gộp 2 section:
    //  1. Section thông báo (title + countdown) — LUÔN có
    //  2. Section kết quả tổng hợp — chỉ hiện nếu show_result_on_projector=true
    showVoteSlide(e, {
      showLiveResult: e.show_result_on_projector,
    })

    // Auto-ẩn slide khi hết duration (case timeout — không có WS event timeout riêng).
    if (e.expires_at_iso) {
      const ms = new Date(e.expires_at_iso).getTime() - Date.now()
      setTimeout(() => closeVoteSlide(e.id, 'timeout'), Math.max(0, ms))
    }
  })
  .listen('.vote-topic.closed', (e) => {
    // Operator bấm /close manual — ẩn slide ngay (hoặc giữ final stats nếu flag=true,
    // tùy UX FE).
    closeVoteSlide(e.id, 'manual_close')
  })
  .listen('.vote-response.added', (e) => {
    // Đổi ý: dec cái cũ + inc cái mới. Spam cùng option BE đã skip broadcast.
    if (e.previous_option) store.decrementVoteCount(e.meeting_vote_topic_id, e.previous_option)
    store.incrementVoteCount(e.meeting_vote_topic_id, e.option)
  })
  .listen('.meeting.ended-early', () => {
    showFinishedSlide()
  })
```

**Tóm tắt logic Tab 8 cho biểu quyết**:

| Trigger | Section thông báo | Section kết quả tổng hợp |
|---|---|---|
| `vote-topic.opened` | **Hiện** (luôn) | Hiện nếu `show_result_on_projector=true` |
| `vote-response.added` | (không thay đổi) | +1 counter (chỉ ảnh hưởng nếu section đang hiện) |
| Hết `expires_at_iso` (timeout) | **Ẩn** (FE setTimeout) | Ẩn theo (hoặc giữ final stats — FE chọn) |
| `vote-topic.closed` | **Ẩn** | Tùy UX (giữ final stats hay ẩn cùng) |

> **Lưu ý**: 2 flag `show_result_on_projector` + `show_result_on_personal_device` là setup tĩnh từ lúc tạo vote topic, **không toggle giữa chừng**. Operator muốn giữ bí mật kết quả → phải set flag = false từ đầu.

### 4.8 Mọi tab — End meeting

```js
channel.listen('.meeting.ended-early', (e) => {
  // FE phase derive sẽ tự thấy now > end_time → 'finished'. Reload meeting hoặc patch local state.
  store.patchMeeting({ end_time: e.end_time })
})
```

---

## 5. Echo + REST coexistence

WS không thay REST. FE pattern khuyến nghị:

1. **Mount component** → fetch REST `GET /meetings/{id}` để có initial state đầy đủ.
2. **Subscribe** Echo channel ngay sau khi REST trả → listen 11 events để patch state realtime.
3. **Unmount** → `echo.leave(...)` để cleanup connection.

Polling 3s ở các tab dashboard có thể giữ lại làm safety net — nếu WS drop thì poll vẫn fetch state mới. Khi WS hoạt động ổn → giảm interval polling lên 30s hoặc bỏ hẳn.

---

## 6. Troubleshooting

| Triệu chứng | Nguyên nhân | Fix |
|---|---|---|
| `403 Forbidden` khi subscribe | User không phải chair/op/participant của meeting | Check `routes/channels.php` callback; verify FE gửi đúng `Authorization` header |
| Echo không kết nối | Reverb worker chưa chạy | `php artisan reverb:start` |
| Event broadcast nhưng FE không nhận | Sai tên event ở Echo `.listen('eventName')` | Phải prefix `.` (vd `.vote-topic.opened`) — Laravel quy ước này để dùng raw name thay vì class FQN |
| `BROADCAST_CONNECTION=null` | `.env` chưa set | Set `BROADCAST_CONNECTION=reverb` + `php artisan config:clear` |
| Worker chạy nhưng client không connect | Firewall block port 8080 | Mở port hoặc reverse-proxy qua Nginx |
| FE production load HTTPS nhưng WS HTTP | Mixed content | `wss://` qua Nginx + `forceTLS: true` ở Echo config |

---

## 7. Sequence diagram tóm tắt

```
[Đại biểu A]                  [BE]                        [Reverb]                [Đại biểu B]   [Operator]   [Projector]
     │                          │                            │                          │              │            │
     │ POST /vote-responses     │                            │                          │              │            │
     ├─────────────────────────▶│                            │                          │              │            │
     │                          │ DB write                   │                          │              │            │
     │                          │ broadcast(VoteResponseAdded)                          │              │            │
     │                          ├───────────────────────────▶│                          │              │            │
     │                          │                            │ event 'vote-response.added' (anonymized)            │
     │                          │                            ├─────────────────────────▶│             │            │
     │                          │                            ├──────────────────────────────────────▶ │            │
     │                          │                            ├─────────────────────────────────────────────────────▶│
     │                          │                            │                          │              │            │
     │ 200 OK (response data)   │                            │                          │              │            │
     │◀─────────────────────────┤                            │                          │              │            │
     │                          │                            │                          │              │            │
     │                                                                                  │              │            │
     │                                  (FE nhận event → +1 count UI)                   │              │            │
```

`->toOthers()` trong code BE đảm bảo người vừa POST không nhận lại chính event của mình (tránh double-count counter). Nếu vẫn muốn người POST cũng nhận → bỏ `->toOthers()` ở `broadcast()`.

---

## 8. Phase tiếp theo (defer)

- **Channel theo role**: hiện 1 channel cho mọi user. Nếu cần payload khác nhau theo role (ví dụ chair nhận đầy đủ identity, đại biểu chỉ nhận anonymized), tách 2 channel `meeting.{id}.public` + `meeting.{id}.privileged` — broadcast 2 event class song song.
- **Presence channel**: nếu cần biết "ai đang online trong meeting", dùng `Broadcast::presence(...)` thay vì `private(...)`.
- **Persistence/replay**: WS event không lưu lại — disconnect/reconnect mất event. Solution: FE refetch REST khi reconnect, hoặc dùng Soketi với event store.
