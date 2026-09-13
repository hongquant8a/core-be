# Thời hạn công việc và hạn gia hạn là trường chỉ có ngày

> Ngày tạo: 13/09/2026

## Thay đổi ở backend

`start_at`, `end_at` của công việc và `requested_end_at`, `current_end_at` của yêu cầu gia hạn **chỉ có ngày**. Cột vẫn là `datetime`, nhưng backend tự chuẩn hoá mọi giá trị nhận vào:

| Trường | Gửi lên dạng gì cũng được | Luôn lưu và trả về |
|---|---|---|
| `start_at` | `2026-10-15`, `2026-10-15 17:00:00`… | `00:00:00 15/10/2026` |
| `end_at` | như trên | `23:59:59 15/10/2026` |
| `requested_end_at` | như trên | `23:59:59 15/10/2026` |

**Nên gửi chỉ ngày (`Y-m-d`).** Phần giờ gửi kèm sẽ bị bỏ.

Không phải thay đổi phá vỡ: client đang gửi kèm giờ vẫn chạy, chỉ là giờ không còn được giữ.

## Việc frontend phải làm

- **Không gắn giờ** khi gửi. Web và miniapp trước đây gắn cứng `17:00:00` cho hạn gia hạn — đã bỏ.
- **Luôn hiển thị chỉ ngày.** API vẫn trả kèm giờ (`23:59:59 15/10/2026`), nên phải cắt về ngày trước khi hiện:
  - web: `formatDateOnly` trong `@core/utils/formatters`
  - miniapp: `formatDateDisplay` trong `utils/api`

Đã sửa các chỗ đang in thẳng chuỗi có giờ: panel và dòng thời gian gia hạn, chip thời hạn ở đầu dòng thời gian, tooltip trạng thái trên danh sách công việc (web); mục gia hạn và dòng thời gian (miniapp).

## Dữ liệu cũ

Migration `2026_09_13_000000_normalize_task_assignment_dates_to_whole_days` chuẩn hoá giờ của dữ liệu đã có. Migration **không tự chạy** — chạy riêng khi được yêu cầu:

```bash
sail artisan migrate --path=database/migrations/2026_09_13_000000_normalize_task_assignment_dates_to_whole_days.php
```

Nếu đã bật nhắc hạn trước khi chạy, cần dựng lại lịch nhắc cho các công việc bị đổi giờ.
