# Zalo OA — chiến lược refresh access_token

**Ngày:** 2026-05-06
**Context:** Note lại trạng thái hiện tại của cơ chế refresh + những gì có thể cải thiện sau.

## Hiện trạng — reactive lazy refresh

Code: [ZaloChannel.php](../../app/Modules/Core/../../app/Services/Notification/Channels/ZaloChannel.php) (function `send()` + `refreshAccessToken()`).

### Trigger
Refresh chỉ xảy ra khi gửi tin gặp lỗi token (`error: -124/-125/-216/1006` hoặc message chứa "access token"). Không có cron, không kiểm tra trước.

### Atomic protection
`Cache::lock('zalo:oa:refresh-token', 30)` + `block(15)` chống race vì refresh_token là **single-use** — 2 worker cùng refresh = 1 thắng, 1 mất token vĩnh viễn.

### Persistence
Sau refresh: `SettingService::update(['zalo_access_token' => ..., 'zalo_refresh_token' => ...])` → DB cập nhật, cache settings invalidate.

### Token lifetimes
- access_token: 1 giờ
- refresh_token: 3 tháng, single-use (refresh xong nhận token mới phải replace)

## Hệ quả thực tế

| Scenario | Behavior |
|---|---|
| Request đầu mỗi giờ (sau khi token 1h expire) | Chậm ~1-2s (1 send fail + 1 refresh + 1 send retry) |
| Request kế tiếp trong cùng giờ | Bình thường |
| Hệ thống không gửi tin > 3 tháng | refresh_token expire → admin phải re-paste tay |
| Zalo revoke token / app deactivate | Tin fail im lặng, không có alert |

## Chưa có (production-grade improvements)

Bỏ qua vì project nội bộ chưa cần, ghi lại để biết khi nào cần thêm:

1. **Cron proactive `php artisan zalo:refresh-token`** — chạy mỗi 50 phút → request đầu không bao giờ slow.
2. **Monitor refresh_token sắp hết** — setting `zalo_token_refreshed_at`, cron daily check > 60 ngày → alert.
3. **Alert khi refresh fail** — log + email/slack notification để admin biết kịp thời thay vì silent fail.
4. **Setting `zalo_token_refreshed_at`** — track timestamp lần refresh cuối, hữu ích cho monitor + UI hiển thị "Token cập nhật X giờ trước".

## Khi nào cần upgrade

- Production scaling, nhiều concurrent worker → có thể cân nhắc cron để giảm contention trên lock
- User phàn nàn request đầu mỗi giờ chậm → add (1)
- Có ticket "OA mất quyền gửi tin sau X tháng" → add (2) + (3)

## Trigger để upgrade

Khi notice 1 trong các signal:
- Log nhiều `Refresh token failed: ...` trong 1 ngày
- Admin báo gửi tin fail im lặng
- Production traffic > vài request/giây cho channel zalo

## Fallback: ZNS legacy channel (dormant)

`app/Services/Notification/Channels/ZaloZnsChannel.php` giữ logic ZNS cũ (gửi qua phone, template-based). **Không register** trong [NotificationServiceProvider.php](../../app/Providers/NotificationServiceProvider.php) — chỉ dùng nếu cần swap ngược.

Khi muốn swap về ZNS:
1. NotificationServiceProvider: `'zalo' => new ZaloChannel($settings)` → `'zalo' => new ZaloZnsChannel($settings)`
2. Điền 6 setting key ZNS: `zalo_server`, `zalo_username`, `zalo_password`, `zalo_sender`, `zalo_template_id`, `zalo_extra_params` — đã có sẵn trong DB (migration `2026_05_06_000200_restore_zalo_zns_settings.php` + SettingSeeder).
3. ContentBuilders không cần đổi — ZNS đọc `phone` từ Recipient, OA đọc `zaloId` từ Recipient. Hiện ContentBuilders set `phone:` cho channel zalo, nên swap về ZNS sẽ work ngay; ngược lại, OA cần ContentBuilders set `zaloId:` (chưa làm — pending integration).

Lý do giữ ZNS dormant:
- OA tier hiện tại (UBND phường) có thể bị Zalo hạn chế tin nhắn
- ZNS đã proven, gửi tin theo phone không cần user follow OA
- Phòng case OA Zalo deprecate v2.0 endpoint
