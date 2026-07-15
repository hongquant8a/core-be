# Troubleshooting — QLCV Backend

> Ngày tạo: 00:00:00 28/06/2026  
> Cập nhật lần cuối: 00:00:00 28/06/2026

Tổng hợp các lỗi thường gặp và cách xử lý nhanh. Khi gặp vấn đề lạ, check danh sách này trước khi đào source.

---

## Checklist đầu tiên khi có lỗi

```
1. Migration đã chạy chưa?     → sail artisan migrate:status
2. Permission seeded?           → sail artisan db:seed --class=PermissionSeeder
3. Queue worker đang chạy?      → Reminder/notification cần worker. composer dev chạy sẵn.
4. Schedule worker đang chạy?   → Cron reminder cần schedule:work.
5. Header X-Organization-Id?    → Hơn 80% lỗi 403/422 là do thiếu/sai header này.
```

---

## Lỗi thường gặp

### 1. 422 — "Vui lòng gửi header X-Organization-Id"
**Nguyên nhân:** Thiếu header `X-Organization-Id` trong request.  
**Không phải bug** — đây là invariant của hệ thống multi-tenant.  
**Fix:** FE/test thêm header `X-Organization-Id: {org_id}` vào mọi request nghiệp vụ.

---

### 2. 403 — Permission fail trong test dù đã assign role
**Nguyên nhân:** Sai thứ tự `seed → setPermissionsTeamId → assignRole`.

**Sai:**
```php
$user->assignRole('Super Admin');       // assign vào default org của seeder
$this->seed(PermissionSeeder::class);   // seed xong, team context reset
setPermissionsTeamId($org->id);         // quá muộn
```

**Đúng:**
```php
$this->seed(PermissionSeeder::class);   // seed trước
setPermissionsTeamId($org->id);         // set team SAU seed
$user->assignRole('Super Admin');       // assign vào đúng org
```

---

### 3. Query model trả empty trong test dù đã tạo dữ liệu
**Nguyên nhân:** `HasOrganizationScope` global scope lọc theo `getPermissionsTeamId()`. Nếu test dùng nhiều org, query org B trong khi context đang set về org A → empty.

**Fix:**
```php
setPermissionsTeamId($orgB->id);   // switch trước khi query
$items = ModelX::all();             // lúc này query đúng org B
```

---

### 4. `remind_at` lệch 7 tiếng
**Nguyên nhân:** Timezone bị set về UTC ở đâu đó (CI env, test setUp).

**Fix:** Đảm bảo `APP_TIMEZONE=Asia/Ho_Chi_Minh` trong `.env` và CI config.

**Kiểm tra:** Chạy `tests/Feature/Notification/ProcessRemindersTimezoneTest.php` — nếu xanh thì TZ ổn.

---

### 5. Test xanh local nhưng đỏ CI
**Nguyên nhân thường gặp:** Timezone CI set về UTC.  
**Fix:** Set `APP_TIMEZONE=Asia/Ho_Chi_Minh` trong CI environment variables.

---

### 6. Route `bulk-delete` trả 405 Method Not Allowed
**Nguyên nhân:** Route đang định nghĩa là `POST` nhưng client gửi `DELETE`.

**Fix:** Route phải là `DELETE`:
```php
Route::delete('/bulk-delete', [Controller::class, 'bulkDestroy']);
```
Client gửi body JSON `{"ids":[...]}` qua DELETE — Laravel parse bình thường.

---

### 7. Scribe generate thiếu endpoint hoặc hiển thị "requires authentication" sai
**Nguyên nhân A:** Thiếu `@unauthenticated` trên endpoint public.  
**Nguyên nhân B:** FormRequest thiếu `bodyParameters()` / `queryParameters()`.

**Fix:**
```php
/**
 * @unauthenticated
 */
public function publicOptions() { ... }
```
Sau khi fix: `sail artisan scribe:generate` → kiểm tra `.scribe/endpoints/*.yaml`.

---

### 8. Dashboard stats lệch nhau vài đơn vị giữa 2 lần refresh
**Nguyên nhân:** Middleware `log.activity` tự log mỗi request. Giữa 2 lần gọi stats API, bảng `log_activities` tăng thêm các row mới.  
**Không phải bug** — là behavior đã thiết kế.

---

### 9. `HasOrganizationScope` "ăn mất" data khi seeding / import hàng loạt
**Nguyên nhân:** Global scope đang active, lọc theo org hiện tại.

**Fix khi cần bypass:**
```php
Model::withoutGlobalScope('organization')->create([...]);
// hoặc
Model::withoutGlobalScopes()->where(...)->get();
```

---

### 10. Notification không gửi dù không có lỗi
**Checklist:**
1. Queue worker có đang chạy? (`composer dev` hoặc `sail artisan queue:listen`)
2. `notification_deliveries` có row mới không? (kiểm tra DB)
3. `failed_jobs` có row nào không? (`sail artisan queue:failed`)
4. Channel config của org có bật không? (`notification_schedules` table)

---

## Khi vấn đề phức tạp hơn

Nếu checklist trên không giải quyết được:
1. Đọc [guide/GETTING_STARTED.md](GETTING_STARTED.md) — đặc biệt section 6 (multi-tenant), 7 (middleware pipeline), 9 (notification engine).
2. Kiểm tra `storage/logs/laravel.log`.
3. Thêm `dd()` / `Log::info()` vào Service để trace luồng.
4. Ghi lại vấn đề vào `docs/answer/` với timestamp để tham khảo sau.
