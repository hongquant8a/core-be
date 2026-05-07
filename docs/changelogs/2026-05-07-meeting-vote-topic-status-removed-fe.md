# Changelog FE — Bỏ field `status` khỏi `meeting_vote_topics`

**Ngày**: 2026-05-07
**Tác động**: FE phải đổi cách derive trạng thái biểu quyết. Filter param `?status=` BE vẫn nhận như cũ.

## Lý do

Trước đây `meeting_vote_topics.status` lưu enum `draft | opened | closed`, đồng thời với `opened_at` + `closed_at` — dư thừa thông tin, dễ lệch state khi update không đồng bộ. Refactor: **bỏ hẳn field `status`**, derive từ 2 timestamp:

| Phase | Điều kiện |
|---|---|
| `draft` | `opened_at` IS NULL |
| `opened` | `opened_at` IS NOT NULL **AND** `closed_at` IS NULL |
| `closed` | `closed_at` IS NOT NULL |

`duration_minutes` vẫn giữ — chỉ dùng cho FE đếm ngược UI popup. **BE không auto-close khi hết duration** — operator phải bấm `/close` manual.

## Breaking changes

### 1. Response không còn field `status`

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
  "opened_at": "10:15:00 23/02/2026",
  "closed_at": null,
  "duration_minutes": 5,
  ...
}
```

→ **FE phải tự derive**:
```js
function getVoteTopicPhase(topic) {
  if (topic.closed_at) return 'closed'
  if (topic.opened_at) return 'opened'
  return 'draft'
}
```

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

### Logic chặn vote/sửa phiếu

- Vote chỉ chấp nhận khi topic `opened` (opened_at NOT NULL + closed_at NULL).
- Sửa phiếu chỉ chấp nhận khi topic chưa `closed` (closed_at NULL).
- Error message trả về unchanged.

## WebSocket events

`vote-topic.opened` + `vote-topic.closed` payload **không còn `status`** (cùng lý do):

```js
// Trước
{ id, status: 'opened', opened_at, ... }

// Sau
{ id, opened_at, ... }   // FE derive phase từ opened_at + closed_at
```

## Việc FE cần làm

1. **Tìm và thay**: mọi nơi đọc `topic.status` → đổi sang derived function (snippet ở trên).
2. **Bỏ status khỏi form**: form tạo/sửa vote topic không còn field "Trạng thái" — UI tự ẩn vì BE không nhận.
3. **Filter giữ nguyên**: query `?status=opened` không sửa.
4. **WS handler**: nếu code có check `event.status` → đổi sang check `event.opened_at` / `event.closed_at`.

## Migration BE đã chạy

- `2026_05_07_230000_drop_status_from_meeting_vote_topics.php` — drop column.
- Down rollback: re-create column `status` default 'draft'. Note: rollback xong sẽ cần re-derive value từ opened_at/closed_at nếu muốn data đúng.
