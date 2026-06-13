# Gate biểu quyết & phát biểu theo điểm danh

**Ngày:** 2026-06-13
**Áp dụng:** Meeting module

---

## 1. Quy tắc

- **Source of truth là list đại biểu (participant)**: chỉ ai có trong danh sách đại biểu mới được bỏ phiếu / đăng ký phát biểu.
- **Biểu quyết**: luôn gate bởi điểm danh — phải có attendance `status = present`.
- **Đăng ký phát biểu / chất vấn**: không gate bởi điểm danh. Chỉ cần là đại biểu.
- **Không có ngoại lệ cho role**: Chủ trì (chair) chỉ vote/phát biểu được nếu cũng là đại biểu. Thư ký (operator), Admin, Super Admin không được nếu không có trong list đại biểu.

---

## 2. Meeting resource — field `is_attendance_confirmed`

`GET /api/meetings/{id}` — response có thêm field:

```json
{
  "id": 1,
  "current_user_meeting_role": "participant",
  "is_attendance_confirmed": false,
  ...
}
```

| Field | Ý nghĩa |
|---|---|
| `is_attendance_confirmed` | `true` = đại biểu đã được xác nhận điểm danh (attendance present). `false` = chưa được xác nhận hoặc không phải đại biểu |

---

### 2.1 Discussion registration list — mỗi item cũng có `is_attendance_confirmed`

`GET /api/meetings/{meeting}/discussion-registrations` — mỗi item có field:

```json
{
  "id": 5,
  "participant_name": "Trần Văn Đại Biểu 1",
  "is_attendance_confirmed": false,
  ...
}
```

| Field | Ý nghĩa |
|---|---|
| `is_attendance_confirmed` | `true` nếu đại biểu đăng ký này đã được xác nhận điểm danh |

**FE cần làm:** Dùng `is_attendance_confirmed` trên từng item để show/hide badge trạng thái điểm danh của người đăng ký (operator dùng để biết ai đã điểm danh, ai chưa).

---

## 3. Channel auth — response

Khi subscribe `meeting.{meetingId}`, response trả về object thay vì `true` như trước:

```json
{
  "user_id": 23,
  "participant_id": 15,
  "is_attendance_confirmed": false
}
```

| Field | Ý nghĩa |
|---|---|
| `user_id` | ID user hiện tại |
| `participant_id` | ID bản ghi `meeting_participants` (null nếu là chair/operator/admin không có participant entry) |
| `is_attendance_confirmed` | `true` nếu đại biểu đã được xác nhận điểm danh |

**FE cần làm:**
- Lưu `is_attendance_confirmed` vào local state.
- Khi nhận event `vote-topic.opened`: check `is_attendance_confirmed`. Nếu `false` → **không hiển thị popup** biểu quyết.

---

## 4. Event `attendance.approved` — payload

Khi operator duyệt điểm danh:

```json
{
  "id": 42,
  "meeting_id": 1,
  "meeting_participant_id": 15,
  "user_id": 23,
  "status": "present",
  "is_attendance_confirmed": true
}
```

**FE cần làm:**
```js
Echo.private(`meeting.${meetingId}`)
  .listen('.attendance.approved', (payload) => {
    if (payload.user_id === currentUserId) {
      isAttendanceConfirmed = true // update local state → hiện nút vote + cho phép mở popup
    }
  })
```

---

## 5. REST enforce (chỉ biểu quyết)

`POST /api/meeting-vote-responses` — luôn gate điểm danh.

```http
HTTP 422 Unprocessable Content
```
```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "meeting_vote_topic_id": ["Bạn chưa được xác nhận điểm danh — không thể bỏ phiếu."]
  }
}
```

Đăng ký phát biểu/chất vấn không bị gate — chỉ cần là participant.

---

## 6. Luồng hoạt động

```
1. Đại biểu vào meeting → GET /api/meetings/{id}
   → nhận is_attendance_confirmed: false → UI ẩn nút "Bỏ phiếu"
   → nút "Đăng ký phát biểu" vẫn hiện (không gate)

2. Đại biểu subscribe WS meeting.{id}
   → nhận { is_attendance_confirmed: false } → lưu local state

3. Đại biểu đăng ký phát biểu bất kỳ lúc nào (chỉ cần là participant)
   → POST discussion-registrations → 201 OK

4. Operator duyệt điểm danh cho đại biểu này
   → broadcast attendance.approved { user_id: 23, is_attendance_confirmed: true }
   → FE check user_id === currentUserId → set isAttendanceConfirmed = true
   → UI hiện nút "Bỏ phiếu"

5. Operator mở biểu quyết → broadcast vote-topic.opened
   → FE check isAttendanceConfirmed:
     - true  → mở popup biểu quyết
     - false → bỏ qua, không hiện popup

6. Đại biểu bấm "Bỏ phiếu" → POST /api/meeting-vote-responses
   - Nếu bypass FE gọi trực tiếp API khi chưa điểm danh → BE trả 422
```

---

## 7. Tóm tắt cho FE

| Việc cần làm | Chỗ implement |
|---|---|
| Đọc `is_attendance_confirmed` từ `GET /api/meetings/{id}`, show/hide nút biểu quyết | Component render |
| Lấy `is_attendance_confirmed` từ channel auth response, lưu local | Lúc subscribe `meeting.{id}` |
| Nghe `attendance.approved`, cập nhật `is_attendance_confirmed` khi `user_id` khớp | Echo listener |
| Trước khi mở popup `vote-topic.opened`, check `is_attendance_confirmed` | Event handler `vote-topic.opened` |
| Nút "Đăng ký phát biểu" luôn hiện nếu là participant (không gate điểm danh) | Component render |
| Đọc `is_attendance_confirmed` trên từng item trong list discussion-registrations | List render (badge trạng thái điểm danh) |
