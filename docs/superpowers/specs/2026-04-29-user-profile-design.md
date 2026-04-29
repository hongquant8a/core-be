# User Profile — Design

**Date:** 2026-04-29
**Scope:** Tách thông tin cá nhân phụ (phone, giới tính, ngày sinh, CCCD, địa chỉ) ra bảng `user_profiles` hasOne với `users`, để trang "Thông tin cá nhân" có nơi lưu mở rộng mà không phình `users`.

## Goal

`users` chỉ giữ những trường định danh + auth (name, email, user_name, password, status, FK quan hệ tổ chức). Mọi profile field cá nhân sống ở `user_profiles`. Mỗi user có đúng 1 profile, auto-create khi tạo user.

## Non-goals

- Không thêm field nào ngoài 6 field FE đề xuất (phone, gender, birth_date, citizen_id, permanent_address, temporary_address). Không gợi ý ethnicity/religion/marital_status...
- Avatar **giữ nguyên** trên `User` model (Spatie media collection `avatars`). Không di chuyển.
- `fcm_token` giữ trên `users` (technical token, không phải profile).
- Không refactor SMS channel — backward-compat qua accessor đảm bảo `$user->phone` vẫn work.

## Data model

### Bảng `user_profiles` (mới)

```php
Schema::create('user_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
    $table->string('phone', 20)->nullable()->index();
    $table->enum('gender', ['male', 'female', 'other'])->nullable();
    $table->date('birth_date')->nullable();
    $table->string('citizen_id', 20)->nullable()->unique();
    $table->text('permanent_address')->nullable();
    $table->text('temporary_address')->nullable();
    $table->timestamps();
});
```

- `user_id` UNIQUE + cascadeOnDelete → 1-1 strict, user xoá kéo theo profile.
- `phone` index để query "tìm user theo SĐT" nếu sau này cần.
- `citizen_id` UNIQUE — không cho 2 user trùng CCCD (data integrity).

### Migration data: move `users.phone` → `user_profiles.phone`

```php
public function up(): void
{
    DB::statement("
        INSERT INTO user_profiles (user_id, phone, created_at, updated_at)
        SELECT id, phone, NOW(), NOW() FROM users
        WHERE id NOT IN (SELECT user_id FROM user_profiles)
    ");
    Schema::table('users', fn (Blueprint $t) => $t->dropColumn('phone'));
}

public function down(): void
{
    Schema::table('users', fn (Blueprint $t) => $t->string('phone', 20)->nullable());
    DB::statement("UPDATE users u JOIN user_profiles p ON p.user_id=u.id SET u.phone=p.phone");
}
```

Migration `up` chia 2 bước:
1. Insert profile row cho user chưa có (kèm copy phone hiện hữu)
2. Drop column `users.phone`

`down` reverse: re-create column → copy phone từ profile về users.

## Models

### `App\Modules\Core\Models\UserProfile` (mới)

```php
class UserProfile extends Model
{
    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id', 'phone', 'gender', 'birth_date', 'citizen_id',
        'permanent_address', 'temporary_address',
    ];

    protected $casts = ['birth_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### `User` model — bổ sung relation + BC accessor

```php
public function profile(): HasOne
{
    return $this->hasOne(UserProfile::class);
}

/**
 * BC: code cũ dùng $user->phone vẫn work, đọc qua profile.
 * Khi sửa code dần thay bằng $user->profile->phone explicit.
 */
public function getPhoneAttribute(): ?string
{
    return $this->profile?->phone;
}
```

Không setter — code muốn ghi `phone` phải qua profile explicit để tránh mơ hồ.

## Auto-create profile

Observer pattern:

`App\Modules\Core\Observers\UserObserver`:

```php
public function created(User $user): void
{
    UserProfile::firstOrCreate(['user_id' => $user->id]);
}
```

Đăng ký ở `AppServiceProvider::boot()`. Mỗi user mới luôn có 1 row profile (rỗng).

## API

| Method | Path | Permission | Mục đích |
|--------|------|-----------|----------|
| `GET` | `/api/users/{user}/profile` | `users.show` | Lấy profile (`firstOrCreate` để legacy users không có profile cũng work) |
| `PUT` | `/api/users/{user}/profile` | `users.update` | Cập nhật profile |

Reuse permission có sẵn — không tạo permission mới. Authorization "user chỉ edit profile chính mình" theo cùng pattern với `users.update` hiện tại (admin update được tất cả, user thường chỉ update được mình).

### Request body (PUT)

Tất cả nullable, partial update (chỉ field nào gửi mới update):

```json
{
  "phone": "0901234567",
  "gender": "male",
  "birth_date": "1990-05-15",
  "citizen_id": "201234567890",
  "permanent_address": "123 ABC, Q.1, TP.HCM",
  "temporary_address": "456 DEF, Q.3, TP.HCM"
}
```

### Validation rules

```php
'phone' => ['nullable', 'string', 'max:20'],
'gender' => ['nullable', 'in:male,female,other'],
'birth_date' => ['nullable', 'date', 'before:today'],
'citizen_id' => [
    'nullable',
    'string',
    'max:20',
    Rule::unique('user_profiles', 'citizen_id')->ignore($this->route('user')->id, 'user_id'),
],
'permanent_address' => ['nullable', 'string', 'max:500'],
'temporary_address' => ['nullable', 'string', 'max:500'],
```

`citizen_id` unique-ignore-self: cho phép user PUT lại CCCD của chính mình (no-op idempotent), reject nếu CCCD đó đang ở user khác.

### Response shape

```json
{
  "success": true,
  "message": "Cập nhật thông tin cá nhân thành công!",
  "data": {
    "id": 12,
    "user_id": 5,
    "phone": "0901234567",
    "gender": "male",
    "birth_date": "1990-05-15",
    "citizen_id": "201234567890",
    "permanent_address": "...",
    "temporary_address": "...",
    "updated_at": "2026-04-29T10:00:00+07:00"
  }
}
```

## Files

| Path | Action |
|------|--------|
| `database/migrations/2026_04_29_000000_create_user_profiles_table.php` | Create |
| `database/migrations/2026_04_29_000001_migrate_phone_to_user_profiles.php` | Create |
| `app/Modules/Core/Models/UserProfile.php` | Create |
| `app/Modules/Core/Models/User.php` | Modify (add `profile()` + accessor) |
| `app/Modules/Core/Observers/UserObserver.php` | Create |
| `app/Providers/AppServiceProvider.php` | Modify (register observer) |
| `app/Modules/Core/Services/UserProfileService.php` | Create |
| `app/Modules/Core/UserProfileController.php` | Create |
| `app/Modules/Core/Requests/UpdateUserProfileRequest.php` | Create |
| `app/Modules/Core/Resources/UserProfileResource.php` | Create |
| `app/Modules/Core/Routes/user.php` | Modify (add 2 routes) |
| `tests/Feature/Core/UserProfileTest.php` | Create (10 tests) |

## Tests (10)

| # | Test | Verify |
|---|------|--------|
| 1 | `test_profile_auto_created_when_user_created` | Tạo user → `UserProfile` row tự xuất hiện (Observer fire) |
| 2 | `test_show_returns_existing_profile` | GET trả đúng shape |
| 3 | `test_show_creates_profile_if_missing` | User legacy chưa có profile → endpoint `firstOrCreate` tạo on-demand |
| 4 | `test_update_persists_all_fields` | PUT đầy đủ → DB cập nhật |
| 5 | `test_update_partial_does_not_clear_other_fields` | PUT chỉ `phone` → các field khác giữ nguyên |
| 6 | `test_update_validates_birth_date_before_today` | birth_date tương lai → 422 |
| 7 | `test_update_validates_gender_enum` | gender invalid → 422 |
| 8 | `test_update_rejects_duplicate_citizen_id` | CCCD đã có ở user khác → 422 unique |
| 9 | `test_update_allows_same_citizen_id_for_self` | User tự update CCCD trùng của mình → OK |
| 10 | `test_user_phone_accessor_reads_from_profile` | `$user->phone` accessor return profile.phone |

## Risks

- **Migration drop column on production**: nếu deploy script không pause cron + queue trước, có window vài giây mà SMS channel đọc `$user->phone` (cũ) lúc column đã drop → exception. Mitigation: deploy theo thứ tự `optimize:clear` → migrate → `queue:restart`.
- **Spatie media** không bị ảnh hưởng vì avatar giữ nguyên trên User.
- **Test `test_phone_migration_preserved_existing_data`** không khả thi với `RefreshDatabase` (chạy migration từ đầu, không có data cũ). Verify thủ công khi deploy: `SELECT user_id, phone FROM user_profiles WHERE phone IS NOT NULL` so với backup `users.phone` trước migration.

## Out of scope

- FE changelog (sẽ viết sau khi BE merge).
- Indexing thêm (vd composite index `phone` + `organization_id` cho search) — chỉ thêm khi có query thực sự cần.
- Soft-delete profile — không cần, cascadeOnDelete đủ.
