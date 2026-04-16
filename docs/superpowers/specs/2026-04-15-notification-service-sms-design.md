# Notification Service + SMS Channel — Design Spec

**Ngày:** 2026-04-15
**Phạm vi:** Xây dựng abstraction Notification cross-channel + implement channel SMS đầu tiên (SOAP webservice của PSC). Tạo stub `MailChannel` + `ZaloChannel` để khung mở rộng hoàn chỉnh — implement thật sau.

## 1. Mục tiêu

- Cung cấp 1 entrypoint thống nhất `NotificationService::send()` để các queue job / business code khác gọi gửi thông báo.
- Tách bạch giữa **dispatcher** (chọn channel, log, error handling) và **channel implementation** (chi tiết giao thức từng kênh).
- Config kênh đọc từ DB qua `SettingService` hiện có (không thêm `.env` mới).
- Singleton-safe để chạy trong queue worker.

## 2. Phạm vi (lần này)

**In-scope:**
- Channel interface + dispatcher service.
- DTO: `Recipient`, `NotificationPayload`, `SendResult`.
- `SmsChannel` — gọi SOAP đến `http://49.156.52.24:5993/SmsService.asmx` (config từ DB settings).
- `SmsClient` — wrapper SOAP, là test seam (mock được).
- `MailChannel` stub + `ZaloChannel` stub — implement interface, return `SendResult(success: false, error: 'Not implemented yet')`. Có sẵn trong registry.
- Logging qua Laravel `Log` facade với channel riêng `notification` (file-based — bảng `log_activities` hiện tại HTTP-oriented, không phù hợp).
- Service provider: bind `NotificationService` là singleton duy nhất, init toàn bộ channel bên trong closure.
- Console command `php artisan sms:test {phone?}` — gửi SMS test dùng `sms_test_phone` (default) hoặc phone arg.
- Unit test cho dispatcher (fake channel) và `SmsChannel` (fake SmsClient).

**Out-of-scope:**
- Implement thật `MailChannel`, `ZaloChannel` (chỉ stub).
- Retry / fallback channel / multi-recipient batch / template engine.
- Bảng `notification_logs` riêng — log file là đủ giai đoạn này.
- UI quản trị (đã có trang Settings sẵn cho key `sms_*`).
- Job queue gọi notification — đó là module business khác.

## 3. Vị trí mã nguồn

Notification là cross-cutting concern, không thuộc business module nào. Đặt ở `app/Services/Notification/` (folder mới — hiện chưa có `app/Services/`).

```
app/Services/Notification/
├── Contracts/
│   └── NotificationChannel.php
├── DTOs/
│   ├── Recipient.php
│   ├── NotificationPayload.php
│   └── SendResult.php
├── Channels/
│   ├── SmsChannel.php
│   ├── MailChannel.php          (stub)
│   └── ZaloChannel.php          (stub)
├── SmsClient.php
├── NotificationService.php
└── NotificationException.php

app/Providers/NotificationServiceProvider.php
app/Console/Commands/TestSmsCommand.php
```

Đăng ký provider trong `bootstrap/providers.php` (Laravel 11+ style).

## 4. Contracts & DTOs

### 4.1. `NotificationChannel` (interface)

```php
interface NotificationChannel
{
    public function send(Recipient $recipient, NotificationPayload $payload): SendResult;
    public function key(): string; // 'sms', 'mail', 'zalo'
}
```

### 4.2. `Recipient` (readonly DTO)

```php
final readonly class Recipient
{
    public function __construct(
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $zaloId = null,
        public ?string $name = null,
    ) {}
}
```

Caller tự rút field từ User model (notification service không phụ thuộc User).

### 4.3. `NotificationPayload` (readonly DTO)

```php
final readonly class NotificationPayload
{
    public function __construct(
        public array $channels,        // ['sms'] hoặc ['sms', 'mail']
        public Recipient $recipient,
        public string $content,        // nội dung gốc, channel tự normalize
        public ?string $subject = null, // dùng cho mail sau này
        public array $context = [],    // metadata: task_id, user_id… để log
    ) {}
}
```

### 4.4. `SendResult` (readonly DTO)

```php
final readonly class SendResult
{
    public function __construct(
        public string $channel,
        public bool $success,
        public ?string $messageId = null,
        public ?string $error = null,
    ) {}
}
```

## 5. `NotificationService` (singleton dispatcher)

**Trách nhiệm:**
1. Lookup channel từ registry theo key trong `$payload->channels`.
2. Gọi `$channel->send()` cho từng channel, gom `SendResult[]`.
3. Log mỗi attempt qua `Log::channel('notification')` (`info('notification.sent', [...])` hoặc `warning('notification.failed', [...])`, context = {channel, recipient, content_preview, business_context, error?}).
4. Bắt exception từ channel → bọc thành `SendResult` failed (KHÔNG bubble exception lên caller, để 1 channel hỏng không chặn channel khác).

**Public API:**
```php
public function send(NotificationPayload $payload): array; // SendResult[]
```

**Constructor:**
```php
public function __construct(
    private array $channels,                  // ['sms' => SmsChannel instance, ...] - resolved bởi provider
    private LoggerInterface $logger,          // Psr\Log\LoggerInterface — bind tới Log::channel('notification')
) {}
```

Channel được resolve **eager** trong provider (qua container), không lazy `app()->make()` trong service — dễ test, rõ dependency.

## 6. `SmsChannel`

**Constructor — load config 1 lần khi tạo instance:**
```php
public function __construct(SmsClient $client, SettingService $settings)
{
    $this->client = $client;
    $this->server   = $settings->getByKey('sms_server')['value']   ?? null;
    $this->username = $settings->getByKey('sms_username')['value'] ?? null;
    $this->password = $settings->getByKey('sms_password')['value'] ?? null;
}
```

Channel instance được tạo bên trong `NotificationService` singleton (qua provider closure) → constructor channel chạy 1 lần per app lifecycle, không đọc settings mỗi `send()`. Không cần bind từng channel là singleton riêng.

**Tradeoff & cache invalidation:**
- Web request: app instance ngắn, mỗi request rebind singleton → config luôn fresh.
- Queue worker: process dài hạn → khi admin update setting `sms_*`, worker đang chạy vẫn dùng config cũ cho đến lúc restart. Acceptable: deploy/restart worker là quy trình chuẩn khi đổi credentials. Document trong README.

**Logic `send()`:**
1. Nếu `$this->server`/`$this->username`/`$this->password` thiếu → return `SendResult(success: false, error: 'SMS not configured')`. KHÔNG throw.
2. Chuẩn hóa phone: `0xxx` → `84xxx`. Validate regex `/^84\d{9,10}$/` — fail return SendResult error.
3. Chuẩn hóa content:
   - Strip dấu: `Str::ascii($content)`.
   - Auto-prefix `Thong bao: ` nếu content KHÔNG có prefix `Thong bao:` và KHÔNG có suffix `Tran trong !` (case-insensitive). PSC yêu cầu 1 trong 2.
4. Gọi `$this->client->sendSms($this->server, $this->username, $this->password, $phone, $content)` → trả `['result' => long, 'message' => string]`.
5. Mapping `result`: theo doc PSC `result >= 0` thì success, `< 0` thì fail. **Note:** doc không nêu rõ mã, verify khi test integration với PSC qua `sms:test` command.

**Trả `SendResult` với:**
- `channel: 'sms'`
- `messageId`: `(string) $result['result']` nếu success
- `error`: `$result['message']` nếu fail

## 7. `SmsClient` (test seam)

```php
class SmsClient
{
    public function sendSms(string $url, string $user, string $pass, string $phone, string $content): array;
}
```

Implement bằng PHP `SoapClient` built-in. Gọi method `sendSMS` namespace `http://tempuri.org/`.

Trong test → swap bằng `FakeSmsClient` (anonymous class hoặc Mockery) trả về response định sẵn.

## 8. `NotificationServiceProvider`

```php
public function register(): void
{
    // Chỉ NotificationService là singleton. Toàn bộ channel được khởi tạo
    // bên trong closure → instance channel sống cùng singleton, không cần
    // bind từng channel riêng.
    $this->app->singleton(NotificationService::class, function ($app) {
        $settings  = $app->make(SettingService::class);
        $smsClient = $app->make(SmsClient::class);

        return new NotificationService(
            channels: [
                'sms'  => new SmsChannel($smsClient, $settings),
                'mail' => new MailChannel(),
                'zalo' => new ZaloChannel(),
            ],
            logger: Log::channel('notification'),
        );
    });
}
```

Thêm channel mới sau này = thêm 1 dòng vào `channels` map + tạo class implement interface. **Không** auto-discovery / config-based registry — YAGNI.

Thay stub bằng implement thật sau này = chỉ sửa nội dung class `MailChannel` / `ZaloChannel`, không động đến provider.

## 9. Logging strategy

Dùng Laravel `Log` facade với channel `notification` (cấu hình trong `config/logging.php`):

```php
// config/logging.php → channels
'notification' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/notification.log'),
    'level'  => 'info',
    'days'   => 14,
],
```

`NotificationService` log mỗi attempt:
- Success: `info('notification.sent', ['channel' => ..., 'recipient' => [...], 'content_preview' => substr(0,100), 'context' => ..., 'message_id' => ...])`
- Fail: `warning('notification.failed', [...,  'error' => ...])`

KHÔNG log password/credentials. Mask phone — không cần ở giai đoạn này.

Lý do không dùng `log_activities` table: bảng đó là HTTP request log (route, method_type, status_code đều NOT NULL), không thiết kế cho system event. Khi cần báo cáo notification chi tiết → tạo bảng riêng (xem Future).

## 10. Error handling

| Scenario | Behavior |
|----------|----------|
| Channel key không tồn tại trong registry | `SendResult(success: false, error: 'Unknown channel: xxx')` + log failed |
| Channel chưa configured (thiếu setting) | `SendResult(success: false, error: '...')` + log failed |
| Recipient field rỗng (vd SMS không có phone) | `SendResult(success: false, error: 'Missing phone')` + log failed |
| SOAP exception / network error | Bắt trong `SmsChannel`, return `SendResult` failed + log failed |
| Tất cả SendResult fail → caller tự quyết định retry (notification service KHÔNG retry) |

## 11. Test command (`sms:test`)

PSC không có endpoint sandbox riêng. Để verify integration:

```bash
php artisan sms:test                  # gửi đến sms_test_phone trong settings
php artisan sms:test 0905112233       # override phone
php artisan sms:test --content="Custom message"
```

Command:
- Đọc `sms_test_phone` từ `SettingService` nếu không có arg.
- Gọi `app(NotificationService::class)->send(...)` với channel `['sms']`.
- In ra `SendResult` (success/error/messageId).
- Exit code 0 nếu success, 1 nếu fail — dễ dùng trong CI/healthcheck script.

## 12. Testing

**Unit tests (PHPUnit, đặt ở `tests/Unit/Services/Notification/`):**
- `NotificationServiceTest`:
  - Dispatch đến đúng channel theo key.
  - Channel không tồn tại → return failed result, không throw.
  - Multiple channels → trả array đúng size.
  - Channel throw exception → wrap thành failed result, log failed, không bubble.
  - Mỗi send() phải log đúng 1 lần per channel.
- `SmsChannelTest` (dùng FakeSmsClient + fake SettingService):
  - Thiếu config → fail result.
  - Phone `0905…` được chuẩn hóa thành `84905…`.
  - Phone invalid → fail result.
  - Content thiếu prefix/suffix → auto-prefix `Thong bao: `.
  - Content có dấu → strip ASCII.
  - Result >= 0 → success, < 0 → fail với error message từ response.
  - SoapClient throw → fail result, không throw.

**Không test integration thật với PSC** — tránh gửi SMS không cố ý. Test integration manual qua tinker khi deploy.

## 13. Migration / data

Không cần migration. Settings keys `sms_server`, `sms_username`, `sms_password`, `sms_test_phone` đã có trong `SettingSeeder` ([database/seeders/SettingSeeder.php:61-64](../../../database/seeders/SettingSeeder.php#L61-L64)).

Admin sẽ vào trang Settings nhập:
- `sms_server`: `http://49.156.52.24:5993/SmsService.asmx`
- `sms_username`: `noivucamle`
- `sms_password`: `b95vezo1ft075cz`

## 14. Acceptance criteria

- [ ] `app(NotificationService::class)->send(...)` gửi SMS thành công khi config đúng (verify manual với `sms_test_phone`).
- [ ] Tất cả unit test pass.
- [ ] Sai config / sai phone / SOAP fail → KHÔNG throw exception ra caller, trả `SendResult` failed và có log entry.
- [ ] Thêm channel mới (Mail/Zalo sau này) chỉ cần: implement interface + thêm 1 dòng registry — không phải sửa `NotificationService`.
- [ ] Singleton: `app(NotificationService::class) === app(NotificationService::class)`.

## 15. Future (không làm lần này)

- Implement thật `MailChannel` (Laravel Mail wrapper, dùng settings `email_*`).
- Implement thật `ZaloChannel` (template-based, settings `zalo_*` đã có).
- Multi-recipient batch send.
- Retry với exponential backoff (queue-level, không phải notification service).
- Bảng `notification_logs` riêng nếu nhu cầu báo cáo notification phức tạp hơn.
- Auto reload config khi setting đổi (event listener invalidate channel singleton).
