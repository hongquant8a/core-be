# Changelog – Settings / Firebase FCM

## 2026-04-21 – Thêm cấu hình Firebase Web SDK

BE là single source of truth cho Firebase config. Admin đổi project qua Settings UI → FE tự hoạt động sau reload, **không cần rebuild**.

---

## 1. `GET /api/settings/public` – thêm group `notification`

Response bổ sung 7 field public dưới key `"notification"`:

```json
{
  "success": true,
  "data": {
    "notification": {
      "firebase_api_key": "AIzaSyB...",
      "firebase_auth_domain": "qlcv-danang.firebaseapp.com",
      "firebase_project_id": "qlcv-danang",
      "firebase_storage_bucket": "qlcv-danang.appspot.com",
      "firebase_messaging_sender_id": "123456789012",
      "firebase_app_id": "1:123456789012:web:abc123def456",
      "firebase_vapid_key": "BE7x..."
    }
  }
}
```

**Giá trị có thể là `null`** nếu admin chưa cấu hình.

**KHÔNG xuất hiện** ở endpoint public: `fcm_enabled`, `firebase_service_account`, `firebase_private_vapid_key` (private).

---

## 2. `GET /api/settings` + `PUT /api/settings` (admin)

Key mới khả dụng trong payload update:

| Key | Public | Type | Nguồn lấy |
|---|---|---|---|
| `firebase_api_key` | ✅ | string | Firebase Console → Project Settings → Web apps → SDK setup → `apiKey` |
| `firebase_auth_domain` | ✅ | string | `{project_id}.firebaseapp.com` hoặc SDK config |
| `firebase_project_id` | ✅ | string | SDK config / service account JSON `project_id` |
| `firebase_storage_bucket` | ✅ | string | `{project_id}.appspot.com` hoặc SDK config |
| `firebase_messaging_sender_id` | ✅ | string | SDK config `messagingSenderId` |
| `firebase_app_id` | ✅ | string | Firebase Console → Web app → App ID |
| `firebase_vapid_key` | ✅ | string | Cloud Messaging → Web configuration → Web Push certificates |
| `fcm_enabled` | ❌ | boolean | (đã có sẵn) |
| `firebase_service_account` | ❌ | json | (đã có sẵn, BE Admin SDK) |
| `firebase_private_vapid_key` | ❌ | string | Private VAPID (tuỳ chọn) |

**PUT body** (chỉ gửi key cần update):
```json
{
  "firebase_api_key": "AIzaSyB...",
  "firebase_project_id": "qlcv-danang"
}
```

Validation dùng type-based generic rules (string/json/boolean). Sai format sẽ lộ khi khởi tạo Firebase SDK chứ không 422 — FE nên có guard tự skip init nếu thiếu field.

---

## 3. FE action items

1. `useAppSettings` thêm getter đọc `data.notification.*` — map ra `firebaseConfig` + `firebaseVapidKey`.
2. `composables/useFcmToken.js` thay `import.meta.env.VITE_FIREBASE_*` → đọc từ `useAppSettings().firebaseConfig`.
3. `initializeApp()` chỉ chạy sau khi `loadSettings()` resolve. Guard: nếu bất kỳ field nào null → skip init, không crash.
4. Gỡ 7 secret `VITE_FIREBASE_*` khỏi GitHub Actions `deploy.yml`.
5. `.env.example` gỡ phần Firebase (chỉ giữ `VITE_API_BASE_URL`).
6. Panel Settings "Cấu hình Firebase FCM": thêm 7 input mới (API Key, Auth Domain, Project ID, Storage Bucket, Sender ID, App ID, VAPID Key) — gửi qua `PUT /api/settings`.

---

## 4. Deploy

BE chạy seed để đẩy 7 field mới vào DB (idempotent, giữ nguyên value hiện có):
```bash
php artisan db:seed --class=FirebaseSettingSeeder
```

Sau đó admin paste config qua UI — không cần restart BE, không cần rebuild FE.
