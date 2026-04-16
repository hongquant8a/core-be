# Changelog FE — Toggle bật/tắt channel thông báo

**Ngày:** 2026-04-16

Thêm toggle bật/tắt riêng cho mỗi kênh notification (SMS, Email, Zalo, FCM).

---

## Settings mới

| Group | Key | Type | Default | Label |
|-------|-----|------|---------|-------|
| `sms` | `sms_enabled` | boolean | `0` | Bật SMS |
| `email` | `email_enabled` | boolean | `0` | Bật Email |
| `zalo` | `zalo_enabled` | boolean | `0` | Bật Zalo |
| `api` | `fcm_enabled` | boolean | `0` | Bật FCM |

> **Quan trọng:** Channel disabled → mọi yêu cầu gửi sẽ bị từ chối với error `"<Channel> is disabled"`, kể cả khi đã có config đầy đủ. Admin BẮT BUỘC bật toggle này mới gửi được.

## Setting đã đổi tên

- `api_firebase_enabled` → `fcm_enabled` (đã xóa key cũ trong DB)

## UI yêu cầu

Mỗi nhóm setting (SMS, Email, Zalo, API) thêm 1 toggle switch ở đầu (key `*_enabled`), tách biệt rõ với các field config khác:
- Toggle off → có thể hiển thị badge "Đã tắt" hoặc nhãn cảnh báo
- Vẫn cho admin nhập config kể cả khi tắt (tách bạch giữa "có config" và "được phép gửi")
