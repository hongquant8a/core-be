# Changelog FE — Bỏ field `status` khỏi `meeting_vote_topics`

**Ngày**: 2026-05-07
**Tác động**: FE phải đọc field `phase` mới (BE derive sẵn) thay cho `status`. Filter param `?status=` BE vẫn nhận như cũ.

## Lý do

Trước đây `meeting_vote_topics.status` lưu enum `draft | opened | closed`, đồng thời với `opened_at` + `closed_at` — dư thừa, dễ lệch state. Refactor: **bỏ hẳn field `status`**, derive từ 2 timestamp + `duration_minutes`:

| Phase | Điều kiện (BE compute) |
|---|---|
| `draft` | `opened_at` IS NULL |
| `opened` | `opened_at` IS NOT NULL **AND** `closed_at` IS NULL **AND** (`duration_minutes` NULL **HOẶC** `opened_at + duration_minutes > now()`) |
| `closed` | `closed_at` IS NOT NULL **HOẶC** `opened_at + duration_minutes <= now()` (auto-expired by timeout) |

→ **Timeout cũng coi là closed**: nếu operator quên bấm `/close` mà đã quá `duration_minutes` → BE coi như đã đóng, **chặn vote/sửa phiếu**.

> **Vẫn không có auto-close DB-side**: BE không update `closed_at` khi timeout — chỉ dùng phép so sánh runtime để derive. Operator vẫn nên bấm `/close` để có timestamp chính thức.

## Breaking changes

### 1. Response: bỏ `status`, thêm `phase` + `expires_at_iso`

**Trước**:
```json
{
  "id": 1,
  "title": "...",
  "status": "opened",
  "opened_at": "10:15:00 23/02/2026",
  "closed_at": null,
  "duration_minutes": 5,
  ...
}
```

**Sau**:
```json
{
  "id": 1,
  "title": "...",
  "phase": "opened",
  "opened_at": "10:15:00 23/02/2026",
  "closed_at": null,
  "duration_minutes": 5,
  "expires_at_iso": "2026-02-23T10:20:00+07:00",
  ...
}
```

→ **FE đọc `topic.phase` trực tiếp**, không tự derive nữa. BE đã enforce timeout.

→ **Countdown dùng `expires_at_iso`** (ISO 8601, anchored absolute time, không drift theo clock skew):
```js
const expireMs = new Date(topic.expires_at_iso).getTime()
const remainingSec = Math.max(0, Math.floor((expireMs - Date.now()) / 1000))
```

> Field `expires_at_iso` = `null` nếu chưa mở hoặc không set `duration_minutes` (vô hạn cho đến khi operator bấm `/close`).

### 2. Body Store/Update không còn nhận `status`

**Trước**:
```json
POST /api/meeting-vote-topics
{ "title": "...", "vote_type": "...", "ballot_mode": "...", "status": "draft" }
```

**Sau**:
```json
POST /api/meeting-vote-topics
{ "title": "...", "vote_type": "...", "ballot_mode": "..." }
```

→ Không gửi `status` nữa. Truyền cũng vô tác dụng (BE strip khỏi validated). Lifecycle chuyển trạng thái qua 2 endpoint:
- `PATCH /meeting-vote-topics/{id}/open` — set `opened_at = now()` + clear `closed_at`. Body optional `description`, `duration_minutes`.
- `PATCH /meeting-vote-topics/{id}/close` — set `closed_at = now()`.

## Không thay đổi (BE giữ tương thích)

### Filter `?status=opened|closed|draft`

BE tự derive query, FE gửi y nguyên:

```
GET /api/meeting-vote-topics?meeting_id=X&status=opened
```

→ BE chuyển sang `WHERE opened_at IS NOT NULL AND closed_at IS NULL`. Compatible với code FE hiện tại.

### Stats endpoint giữ nguyên format

```
GET /api/meeting-vote-topics/stats?meeting_id=X
→ { "total": ..., "draft": ..., "opened": ..., "closed": ... }
```

BE compute từ opened_at/closed_at. FE không cần đổi.

### Logic chặn vote/sửa phiếu (đã enforce thêm timeout)

- Vote chỉ chấp nhận khi `topic.phase === 'opened'` (opened_at NOT NULL + closed_at NULL + chưa hết duration_minutes).
- Sửa phiếu chỉ chấp nhận khi `topic.phase !== 'closed'`.
- **Timeout cũng block**: nếu đại biểu submit vote sau khi `now > opened_at + duration_minutes` → BE trả 422 "Chương trình biểu quyết chưa mở hoặc đã đóng".
- Error message trả về unchanged.

→ FE nên disable nút "Bỏ phiếu" khi countdown hết giờ (dùng `expires_at_iso`), nhưng không cần thiết vì BE block. Nếu user submit muộn → catch 422 và hiển thị error message.

### Phân biệt popup vote vs panel kết quả

Trên Tab 3 đại biểu có 2 thứ tách biệt khi nhận `vote-topic.opened`:

| UI element | Có hiện không? | Phụ thuộc flag |
|---|---|---|
| **Popup input bỏ phiếu** | LUÔN bật | Không (đại biểu cần vote) |
| **Panel kết quả tổng hợp** (count live + final stats) | Tùy flag | `show_result_on_personal_device` |

Tương tự Tab 8 màn chiếu: panel kết quả tổng hợp phụ thuộc `show_result_on_projector`. Cả 2 flag là setup tĩnh từ lúc tạo vote topic, không toggle giữa chừng.

### Đóng modal popup — 2 case

Cả 2 đều phải đóng popup biểu quyết tại FE:

1. **Manual close** (operator bấm `/close`): nhận WS event `vote-topic.closed` → đóng modal + fetch stats nếu `show_result_on_personal_device`.
2. **Timeout** (`now > opened_at + duration_minutes`): **KHÔNG có WS event** vì BE không track timeout proactively. FE phải `setTimeout` local từ lúc mở popup, anchored vào `expires_at_iso`.

```js
function openVoteModal(topic) {
  modal.show(topic)

  // Auto-close khi timeout
  if (topic.expires_at_iso) {
    const ms = new Date(topic.expires_at_iso).getTime() - Date.now()
    const timer = setTimeout(() => modal.close('timeout'), Math.max(0, ms))
    modal.onClose(() => clearTimeout(timer))
  }
}

// Listen WS event
channel.listen('.vote-topic.closed', (e) => modal.close('manual_close'))
```

## WebSocket events

`vote-topic.opened` payload thêm `phase` + `expires_at_iso`, bỏ `status`:

```js
// Trước
{ id, status: 'opened', opened_at, duration_minutes, ... }

// Sau
{
  id,
  meeting_id,
  title,
  description,
  duration_minutes,
  vote_type,
  ballot_mode,
  show_result_on_projector,
  show_result_on_personal_device,
  phase: 'opened',                              // BE derive
  opened_at: '2026-02-23T10:15:00+07:00',       // ISO 8601
  expires_at_iso: '2026-02-23T10:20:00+07:00',  // dùng cho countdown FE
}
```

`vote-topic.closed` payload:
```js
{
  id,
  meeting_id,
  show_result_on_projector,
  show_result_on_personal_device,
  phase: 'closed',
  closed_at: '2026-02-23T10:25:00+07:00',
}
```

## Việc FE cần làm

1. **Tìm và thay**: mọi nơi đọc `topic.status` → đổi sang `topic.phase` (BE derive sẵn, đã include timeout).
2. **Countdown UI**: dùng `topic.expires_at_iso` (ISO 8601) làm anchor — `new Date(expires_at_iso).getTime() - Date.now()`.
3. **Bỏ status khỏi form**: form tạo/sửa vote topic không còn field "Trạng thái" — UI tự ẩn vì BE không nhận.
4. **Filter giữ nguyên**: query `?status=opened|closed|draft` không sửa, BE tự convert.
5. **WS handler**: thay `event.status` → `event.phase`.
6. **Vote submit**: nếu countdown hết giờ thì FE đóng popup; nếu user kịp submit muộn (network race) → BE trả 422, FE hiển thị error.

## Migration BE đã chạy

- `2026_05_07_230000_drop_status_from_meeting_vote_topics.php` — drop column.
- Down rollback: re-create column `status` default 'draft'. Note: rollback xong sẽ cần re-derive value từ opened_at/closed_at nếu muốn data đúng.
