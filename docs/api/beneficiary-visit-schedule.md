# API Lịch viếng thăm (Beneficiary Visit Schedule) — ⚠️ ĐÃ GỠ BỎ

> Ngày tạo: 10:00:00 16/07/2026
> Cập nhật lần cuối: 09:55:00 26/07/2026 — đánh dấu đã gỡ bỏ.

> ## ⚠️ Chức năng này KHÔNG CÒN TỒN TẠI
>
> Lịch viếng thăm và toàn bộ hạ tầng nhắc lịch đã bị gỡ khi đơn giản hóa module ngày 25/07/2026.
> **Mọi endpoint mô tả bên dưới trả 404.**
>
> Tài liệu giữ lại làm tham chiếu lịch sử. FE còn module nào gọi các endpoint này thì phải gỡ.

Lịch viếng thăm/tặng quà người có công (Tết, 27/7, sinh nhật, tự do). Chỉ có **3 action**: `index`, `show`, `changeStatus` — **không có `store` qua API**, lịch được sinh tự động bởi Console Command (`beneficiary:generate-visit-schedules`, chạy cron hàng năm cho 27/7, chạy tay cho Tết vì âm lịch). Nhắc trước N ngày dùng lại hạ tầng Reminder chung (`ReminderScheduler`/`ProcessRemindersCommand`) — cấu hình "nhắc trước mấy ngày" qua [notification-config.md](notification-config.md) (`module_key=beneficiary`, base path `/api/beneficiary/notification-config`).

**Header bắt buộc:** `Authorization: Bearer {token}` và `X-Organization-Id: {organization_id}`.

**Base path:** `/api/beneficiary-visit-schedules`

---

## Danh sách lịch viếng thăm

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-visit-schedules` |
| **Query** | `assigned_to` (ID cán bộ phụ trách), `status` (pending \| done \| skipped), `from_date`/`to_date` (theo `scheduled_date`), `sort_by` (id \| scheduled_date \| status \| created_at), `sort_order`, `limit`. |
| **Response** | Paginated collection (`VisitScheduleResource`). |

---

## Chi tiết lịch viếng thăm

| | |
|---|---|
| **Method** | GET |
| **Path** | `/api/beneficiary-visit-schedules/{id}` |
| **Response** | `VisitScheduleResource` kèm `subject`, `assigned_to`. |

---

## Đổi trạng thái lịch viếng thăm

| | |
|---|---|
| **Method** | PATCH |
| **Path** | `/api/beneficiary-visit-schedules/{id}/status` |
| **Content-Type** | `multipart/form-data` (khi có ảnh xác nhận). |
| **Body** | `status` (required: `done` \| `skipped`), `note` (nullable, max 500), `evidence[]` (nullable, mảng file ảnh — chỉ áp dụng khi `status = done`; jpg/jpeg/png/gif/webp, mỗi file ≤ 10MB). |
| **Side-effect** | Ảnh lưu qua `MediaService` vào collection `visit_evidence`. Bất kể `done` hay `skipped`, các `reminders` đang `pending` của lịch này tự động bị huỷ (`ReminderScheduler::cancelPending()` qua `VisitScheduleObserver`) — không còn nhắc nữa. |
| **Response** | `VisitScheduleResource`. |

---

## Response mẫu (VisitScheduleResource)

```json
{
  "id": 8,
  "subject_type": "beneficiary",
  "subject_id": 22,
  "subject": { "id": 22, "name": "Trần Văn B" },
  "occasion": "war_invalids_day_27_7",
  "occasion_label": "Ngày Thương binh - Liệt sĩ 27/7",
  "scheduled_date": "27/07/2026",
  "status": "pending",
  "status_label": "Chờ thực hiện",
  "assigned_to": { "id": 1, "name": "Nguyễn Văn Hùng", "avatar": null },
  "note": null,
  "evidence": [],
  "created_at": "14:09:30 16/07/2026",
  "updated_at": "14:09:30 16/07/2026"
}
```

`evidence` là mảng `{ "id": ..., "url": ... }` từ `MediaService`, chỉ có nội dung sau khi `changeStatus` với `status = done` kèm file.
