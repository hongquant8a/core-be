# Notification Service + SMS Channel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a singleton `NotificationService` with channel abstraction; implement working `SmsChannel` (PSC SOAP) + stub `MailChannel`/`ZaloChannel`; provide `php artisan sms:test` for manual verification.

**Architecture:** `NotificationService` is a singleton bound in a service provider. The provider builds it once with all channels instantiated inside a closure. Each channel implements `NotificationChannel` interface and is responsible for its own protocol + config (loaded once in constructor from `SettingService`). Errors from channels are caught and converted to `SendResult` failures; they never bubble up.

**Tech Stack:** Laravel 12, PHP 8.2+, PHPUnit 11, Mockery, native PHP `SoapClient`, Laravel `Log` facade with custom channel.

**Spec:** [docs/superpowers/specs/2026-04-15-notification-service-sms-design.md](../specs/2026-04-15-notification-service-sms-design.md)

---

## File Structure

**Created:**
- `app/Services/Notification/Contracts/NotificationChannel.php` — channel interface
- `app/Services/Notification/DTOs/Recipient.php` — recipient data
- `app/Services/Notification/DTOs/NotificationPayload.php` — payload data
- `app/Services/Notification/DTOs/SendResult.php` — result data
- `app/Services/Notification/NotificationException.php` — generic exception (currently unused, reserved for future)
- `app/Services/Notification/NotificationService.php` — dispatcher
- `app/Services/Notification/SmsClient.php` — SOAP wrapper (test seam)
- `app/Services/Notification/Channels/SmsChannel.php` — SMS channel impl
- `app/Services/Notification/Channels/MailChannel.php` — stub
- `app/Services/Notification/Channels/ZaloChannel.php` — stub
- `app/Providers/NotificationServiceProvider.php` — DI binding
- `app/Console/Commands/TestSmsCommand.php` — `sms:test` command
- `tests/Unit/Services/Notification/NotificationServiceTest.php`
- `tests/Unit/Services/Notification/Channels/SmsChannelTest.php`

**Modified:**
- `bootstrap/providers.php` — register `NotificationServiceProvider`
- `config/logging.php` — add `notification` log channel

---

## Task 1: Create DTO — `Recipient`

**Files:**
- Create: `app/Services/Notification/DTOs/Recipient.php`
- Test: `tests/Unit/Services/Notification/RecipientTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\Recipient;
use Tests\TestCase;

class RecipientTest extends TestCase
{
    public function test_constructs_with_all_nullable_fields_defaulting_to_null(): void
    {
        $r = new Recipient();
        $this->assertNull($r->phone);
        $this->assertNull($r->email);
        $this->assertNull($r->zaloId);
        $this->assertNull($r->name);
    }

    public function test_stores_provided_values(): void
    {
        $r = new Recipient(phone: '0905112233', email: 'a@b.c', zaloId: 'z1', name: 'Tuan');
        $this->assertSame('0905112233', $r->phone);
        $this->assertSame('a@b.c', $r->email);
        $this->assertSame('z1', $r->zaloId);
        $this->assertSame('Tuan', $r->name);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RecipientTest`
Expected: FAIL — class `App\Services\Notification\DTOs\Recipient` not found.

- [ ] **Step 3: Implement `Recipient`**

```php
<?php

namespace App\Services\Notification\DTOs;

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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RecipientTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Notification/DTOs/Recipient.php tests/Unit/Services/Notification/RecipientTest.php
git commit -m "feat(notification): add Recipient DTO"
```

---

## Task 2: Create DTO — `SendResult`

**Files:**
- Create: `app/Services/Notification/DTOs/SendResult.php`
- Test: `tests/Unit/Services/Notification/SendResultTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\SendResult;
use Tests\TestCase;

class SendResultTest extends TestCase
{
    public function test_success_result(): void
    {
        $r = new SendResult(channel: 'sms', success: true, messageId: '42');
        $this->assertSame('sms', $r->channel);
        $this->assertTrue($r->success);
        $this->assertSame('42', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_result(): void
    {
        $r = new SendResult(channel: 'sms', success: false, error: 'boom');
        $this->assertFalse($r->success);
        $this->assertNull($r->messageId);
        $this->assertSame('boom', $r->error);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SendResultTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `SendResult`**

```php
<?php

namespace App\Services\Notification\DTOs;

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

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=SendResultTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Notification/DTOs/SendResult.php tests/Unit/Services/Notification/SendResultTest.php
git commit -m "feat(notification): add SendResult DTO"
```

---

## Task 3: Create DTO — `NotificationPayload`

**Files:**
- Create: `app/Services/Notification/DTOs/NotificationPayload.php`
- Test: `tests/Unit/Services/Notification/NotificationPayloadTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Tests\TestCase;

class NotificationPayloadTest extends TestCase
{
    public function test_constructs_with_required_and_optional_fields(): void
    {
        $recipient = new Recipient(phone: '0905112233');
        $p = new NotificationPayload(
            channels: ['sms', 'mail'],
            recipient: $recipient,
            content: 'hello',
            subject: 'subj',
            context: ['task_id' => 7],
        );

        $this->assertSame(['sms', 'mail'], $p->channels);
        $this->assertSame($recipient, $p->recipient);
        $this->assertSame('hello', $p->content);
        $this->assertSame('subj', $p->subject);
        $this->assertSame(['task_id' => 7], $p->context);
    }

    public function test_subject_and_context_default(): void
    {
        $p = new NotificationPayload(['sms'], new Recipient(phone: '0905112233'), 'x');
        $this->assertNull($p->subject);
        $this->assertSame([], $p->context);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NotificationPayloadTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `NotificationPayload`**

```php
<?php

namespace App\Services\Notification\DTOs;

final readonly class NotificationPayload
{
    public function __construct(
        public array $channels,
        public Recipient $recipient,
        public string $content,
        public ?string $subject = null,
        public array $context = [],
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=NotificationPayloadTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Notification/DTOs/NotificationPayload.php tests/Unit/Services/Notification/NotificationPayloadTest.php
git commit -m "feat(notification): add NotificationPayload DTO"
```

---

## Task 4: Create `NotificationChannel` interface + `NotificationException`

**Files:**
- Create: `app/Services/Notification/Contracts/NotificationChannel.php`
- Create: `app/Services/Notification/NotificationException.php`

These are simple interface/exception declarations — no test needed (no behavior). Skip TDD here.

- [ ] **Step 1: Create the interface**

```php
<?php

namespace App\Services\Notification\Contracts;

use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;

interface NotificationChannel
{
    /**
     * Send a notification. MUST return a SendResult — never throw.
     */
    public function send(Recipient $recipient, NotificationPayload $payload): SendResult;

    /**
     * Channel registry key (e.g. 'sms', 'mail', 'zalo').
     */
    public function key(): string;
}
```

- [ ] **Step 2: Create the exception**

```php
<?php

namespace App\Services\Notification;

use RuntimeException;

class NotificationException extends RuntimeException {}
```

- [ ] **Step 3: Verify autoload + syntax**

Run: `php -l app/Services/Notification/Contracts/NotificationChannel.php`
Run: `php -l app/Services/Notification/NotificationException.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add app/Services/Notification/Contracts/NotificationChannel.php app/Services/Notification/NotificationException.php
git commit -m "feat(notification): add NotificationChannel contract and exception"
```

---

## Task 5: Implement `SmsClient` (test seam)

**Files:**
- Create: `app/Services/Notification/SmsClient.php`

`SmsClient` wraps PHP's native `SoapClient`. It's tiny — a single method that delegates to SOAP. Real SOAP behavior is not unit-tested (we'd be testing PHP). Tests for `SmsChannel` (Task 7) will mock this class.

- [ ] **Step 1: Implement `SmsClient`**

```php
<?php

namespace App\Services\Notification;

use SoapClient;
use SoapFault;

class SmsClient
{
    /**
     * Call PSC sendSMS endpoint.
     *
     * @return array{result: int, message: string}
     *
     * @throws SoapFault on transport failure (caller is expected to catch).
     */
    public function sendSms(string $url, string $user, string $pass, string $phone, string $content): array
    {
        $client = new SoapClient(null, [
            'location' => $url,
            'uri'      => 'http://tempuri.org/',
            'trace'    => false,
            'exceptions' => true,
        ]);

        $response = $client->__soapCall('sendSMS', [
            'userID'   => $user,
            'password' => $pass,
            'phoneNo'  => $phone,
            'content'  => $content,
        ]);

        // Response shape: { sendSMSResult: { result: long, message: string } }
        $result = is_object($response) ? (array) $response : $response;
        $inner  = $result['sendSMSResult'] ?? $result;
        $inner  = is_object($inner) ? (array) $inner : $inner;

        return [
            'result'  => (int) ($inner['result'] ?? -999),
            'message' => (string) ($inner['message'] ?? ''),
        ];
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Services/Notification/SmsClient.php`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Services/Notification/SmsClient.php
git commit -m "feat(notification): add SmsClient SOAP wrapper"
```

---

## Task 6: Implement stub channels (`MailChannel`, `ZaloChannel`)

**Files:**
- Create: `app/Services/Notification/Channels/MailChannel.php`
- Create: `app/Services/Notification/Channels/ZaloChannel.php`
- Test: `tests/Unit/Services/Notification/Channels/StubChannelsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\ZaloChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use Tests\TestCase;

class StubChannelsTest extends TestCase
{
    public function test_mail_channel_returns_not_implemented(): void
    {
        $ch = new MailChannel();
        $this->assertSame('mail', $ch->key());

        $result = $ch->send(
            new Recipient(email: 'a@b.c'),
            new NotificationPayload(['mail'], new Recipient(email: 'a@b.c'), 'hi'),
        );

        $this->assertSame('mail', $result->channel);
        $this->assertFalse($result->success);
        $this->assertSame('Not implemented yet', $result->error);
    }

    public function test_zalo_channel_returns_not_implemented(): void
    {
        $ch = new ZaloChannel();
        $this->assertSame('zalo', $ch->key());

        $result = $ch->send(
            new Recipient(zaloId: 'z1'),
            new NotificationPayload(['zalo'], new Recipient(zaloId: 'z1'), 'hi'),
        );

        $this->assertSame('zalo', $result->channel);
        $this->assertFalse($result->success);
        $this->assertSame('Not implemented yet', $result->error);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=StubChannelsTest`
Expected: FAIL — classes not found.

- [ ] **Step 3: Implement `MailChannel`**

```php
<?php

namespace App\Services\Notification\Channels;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;

class MailChannel implements NotificationChannel
{
    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        return new SendResult(channel: 'mail', success: false, error: 'Not implemented yet');
    }

    public function key(): string
    {
        return 'mail';
    }
}
```

- [ ] **Step 4: Implement `ZaloChannel`**

```php
<?php

namespace App\Services\Notification\Channels;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;

class ZaloChannel implements NotificationChannel
{
    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        return new SendResult(channel: 'zalo', success: false, error: 'Not implemented yet');
    }

    public function key(): string
    {
        return 'zalo';
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=StubChannelsTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Notification/Channels/MailChannel.php app/Services/Notification/Channels/ZaloChannel.php tests/Unit/Services/Notification/Channels/StubChannelsTest.php
git commit -m "feat(notification): add stub Mail and Zalo channels"
```

---

## Task 7: Implement `SmsChannel`

**Files:**
- Create: `app/Services/Notification/Channels/SmsChannel.php`
- Test: `tests/Unit/Services/Notification/Channels/SmsChannelTest.php`

This is the most complex unit. Build incrementally — write all tests first, then the implementation. Tests use a `FakeSmsClient` (anonymous subclass) and a Mockery mock of `SettingService`.

- [ ] **Step 1: Write all failing tests for `SmsChannel`**

```php
<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\SmsClient;
use Mockery;
use SoapFault;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSettings(?string $server, ?string $user, ?string $pass): SettingService
    {
        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('sms_server')->andReturn($server === null ? null : ['value' => $server]);
        $m->shouldReceive('getByKey')->with('sms_username')->andReturn($user === null ? null : ['value' => $user]);
        $m->shouldReceive('getByKey')->with('sms_password')->andReturn($pass === null ? null : ['value' => $pass]);

        return $m;
    }

    private function fakeClient(callable $sendImpl): SmsClient
    {
        return new class($sendImpl) extends SmsClient
        {
            public function __construct(private $impl) {}

            public function sendSms(string $url, string $user, string $pass, string $phone, string $content): array
            {
                return ($this->impl)($url, $user, $pass, $phone, $content);
            }
        };
    }

    private function send(SmsChannel $ch, ?string $phone, string $content): \App\Services\Notification\DTOs\SendResult
    {
        $recipient = new Recipient(phone: $phone);
        $payload   = new NotificationPayload(['sms'], $recipient, $content);

        return $ch->send($recipient, $payload);
    }

    public function test_key_returns_sms(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $this->assertSame('sms', $ch->key());
    }

    public function test_returns_failure_when_server_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => 'should not call']),
            $this->makeSettings(null, 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('SMS not configured', $r->error);
    }

    public function test_returns_failure_when_username_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', null, 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('SMS not configured', $r->error);
    }

    public function test_returns_failure_when_password_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', null),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('SMS not configured', $r->error);
    }

    public function test_returns_failure_when_phone_missing(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, null, 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('Missing phone', $r->error);
    }

    public function test_returns_failure_when_phone_invalid(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => '']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '12345', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('Invalid phone format', $r->error);
    }

    public function test_normalizes_phone_with_leading_zero_to_84_prefix(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Thong bao: hi');
        $this->assertSame('84905112233', $captured[3]);
    }

    public function test_passes_through_phone_already_starting_with_84(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '84905112233', 'Thong bao: hi');
        $this->assertSame('84905112233', $captured[3]);
    }

    public function test_strips_vietnamese_diacritics_from_content(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Thong bao: Xin chào bạn');
        $this->assertStringNotContainsString('à', $captured[4]);
        $this->assertStringContainsString('Xin chao ban', $captured[4]);
    }

    public function test_auto_prefixes_thong_bao_when_neither_marker_present(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Hello');
        $this->assertSame('Thong bao: Hello', $captured[4]);
    }

    public function test_does_not_prefix_when_thong_bao_already_present(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Thong bao: existing');
        $this->assertSame('Thong bao: existing', $captured[4]);
    }

    public function test_does_not_prefix_when_tran_trong_suffix_present(): void
    {
        $captured = [];
        $ch = new SmsChannel(
            $this->fakeClient(function (...$args) use (&$captured) {
                $captured = $args;

                return ['result' => 1, 'message' => 'ok'];
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );

        $this->send($ch, '0905112233', 'Hello. Tran trong !');
        $this->assertSame('Hello. Tran trong !', $captured[4]);
    }

    public function test_success_when_result_is_zero_or_positive(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => 0, 'message' => 'ok']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertTrue($r->success);
        $this->assertSame('0', $r->messageId);
        $this->assertNull($r->error);
    }

    public function test_failure_when_result_is_negative(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(fn () => ['result' => -3, 'message' => 'invalid phone']),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertSame('invalid phone', $r->error);
    }

    public function test_catches_soap_fault_and_returns_failure(): void
    {
        $ch = new SmsChannel(
            $this->fakeClient(function () {
                throw new SoapFault('Server', 'connection refused');
            }),
            $this->makeSettings('http://x', 'u', 'p'),
        );
        $r = $this->send($ch, '0905112233', 'hi');
        $this->assertFalse($r->success);
        $this->assertStringContainsString('connection refused', $r->error);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=SmsChannelTest`
Expected: FAIL — `App\Services\Notification\Channels\SmsChannel` not found.

- [ ] **Step 3: Implement `SmsChannel`**

```php
<?php

namespace App\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\SmsClient;
use Illuminate\Support\Str;
use Throwable;

class SmsChannel implements NotificationChannel
{
    private ?string $server;
    private ?string $username;
    private ?string $password;

    public function __construct(private SmsClient $client, SettingService $settings)
    {
        $this->server   = $settings->getByKey('sms_server')['value']   ?? null;
        $this->username = $settings->getByKey('sms_username')['value'] ?? null;
        $this->password = $settings->getByKey('sms_password')['value'] ?? null;
    }

    public function key(): string
    {
        return 'sms';
    }

    public function send(Recipient $recipient, NotificationPayload $payload): SendResult
    {
        if (! $this->server || ! $this->username || ! $this->password) {
            return $this->fail('SMS not configured');
        }

        if (! $recipient->phone) {
            return $this->fail('Missing phone');
        }

        $phone = $this->normalizePhone($recipient->phone);
        if (! preg_match('/^84\d{9,10}$/', $phone)) {
            return $this->fail('Invalid phone format');
        }

        $content = $this->normalizeContent($payload->content);

        try {
            $resp = $this->client->sendSms($this->server, $this->username, $this->password, $phone, $content);
        } catch (Throwable $e) {
            return $this->fail('SOAP error: '.$e->getMessage());
        }

        $code = $resp['result'] ?? -999;
        if ($code >= 0) {
            return new SendResult(channel: 'sms', success: true, messageId: (string) $code);
        }

        return $this->fail($resp['message'] ?? 'SMS send failed');
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if (str_starts_with($phone, '0')) {
            return '84'.substr($phone, 1);
        }

        return $phone;
    }

    private function normalizeContent(string $content): string
    {
        $ascii = Str::ascii($content);
        $hasPrefix = stripos($ascii, 'thong bao:') === 0;
        $hasSuffix = stripos($ascii, 'tran trong !') !== false;

        if (! $hasPrefix && ! $hasSuffix) {
            return 'Thong bao: '.$ascii;
        }

        return $ascii;
    }

    private function fail(string $error): SendResult
    {
        return new SendResult(channel: 'sms', success: false, error: $error);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SmsChannelTest`
Expected: PASS (all 14 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Notification/Channels/SmsChannel.php tests/Unit/Services/Notification/Channels/SmsChannelTest.php
git commit -m "feat(notification): implement SmsChannel with PSC SOAP integration"
```

---

## Task 8: Implement `NotificationService` (dispatcher)

**Files:**
- Create: `app/Services/Notification/NotificationService.php`
- Test: `tests/Unit/Services/Notification/NotificationServiceTest.php`

- [ ] **Step 1: Write all failing tests for `NotificationService`**

```php
<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\NotificationService;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeChannel(string $key, callable $impl): NotificationChannel
    {
        return new class($key, $impl) implements NotificationChannel
        {
            public function __construct(private string $k, private $impl) {}

            public function send(Recipient $r, NotificationPayload $p): SendResult
            {
                return ($this->impl)($r, $p);
            }

            public function key(): string
            {
                return $this->k;
            }
        };
    }

    private function nullLogger(): LoggerInterface
    {
        $m = Mockery::mock(LoggerInterface::class);
        $m->shouldReceive('info')->byDefault();
        $m->shouldReceive('warning')->byDefault();

        return $m;
    }

    private function payload(array $channels, string $content = 'hi'): NotificationPayload
    {
        return new NotificationPayload($channels, new Recipient(phone: '0905112233'), $content);
    }

    public function test_dispatches_to_correct_channel_by_key(): void
    {
        $called = null;
        $svc = new NotificationService(
            channels: [
                'sms' => $this->fakeChannel('sms', function () use (&$called) {
                    $called = 'sms';

                    return new SendResult('sms', true, '1');
                }),
            ],
            logger: $this->nullLogger(),
        );

        $results = $svc->send($this->payload(['sms']));
        $this->assertSame('sms', $called);
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->success);
    }

    public function test_returns_failure_for_unknown_channel_without_throwing(): void
    {
        $svc = new NotificationService(channels: [], logger: $this->nullLogger());
        $results = $svc->send($this->payload(['ghost']));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->success);
        $this->assertSame('ghost', $results[0]->channel);
        $this->assertSame('Unknown channel: ghost', $results[0]->error);
    }

    public function test_dispatches_to_multiple_channels_in_order(): void
    {
        $svc = new NotificationService(
            channels: [
                'sms'  => $this->fakeChannel('sms',  fn () => new SendResult('sms', true, '1')),
                'mail' => $this->fakeChannel('mail', fn () => new SendResult('mail', false, error: 'nope')),
            ],
            logger: $this->nullLogger(),
        );

        $results = $svc->send($this->payload(['sms', 'mail']));
        $this->assertCount(2, $results);
        $this->assertSame('sms', $results[0]->channel);
        $this->assertTrue($results[0]->success);
        $this->assertSame('mail', $results[1]->channel);
        $this->assertFalse($results[1]->success);
    }

    public function test_wraps_channel_exception_into_failure_result(): void
    {
        $svc = new NotificationService(
            channels: [
                'sms' => $this->fakeChannel('sms', function () {
                    throw new RuntimeException('boom');
                }),
            ],
            logger: $this->nullLogger(),
        );

        $results = $svc->send($this->payload(['sms']));
        $this->assertFalse($results[0]->success);
        $this->assertSame('sms', $results[0]->channel);
        $this->assertStringContainsString('boom', $results[0]->error);
    }

    public function test_logs_info_on_success(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with(
            'notification.sent',
            Mockery::on(fn ($ctx) => $ctx['channel'] === 'sms' && $ctx['message_id'] === '1'),
        );
        $logger->shouldReceive('warning')->never();

        $svc = new NotificationService(
            channels: ['sms' => $this->fakeChannel('sms', fn () => new SendResult('sms', true, '1'))],
            logger: $logger,
        );
        $svc->send($this->payload(['sms']));
    }

    public function test_logs_warning_on_failure(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('warning')->once()->with(
            'notification.failed',
            Mockery::on(fn ($ctx) => $ctx['channel'] === 'sms' && $ctx['error'] === 'nope'),
        );
        $logger->shouldReceive('info')->never();

        $svc = new NotificationService(
            channels: ['sms' => $this->fakeChannel('sms', fn () => new SendResult('sms', false, error: 'nope'))],
            logger: $logger,
        );
        $svc->send($this->payload(['sms']));
    }

    public function test_log_context_includes_recipient_content_preview_and_business_context(): void
    {
        $longContent = str_repeat('a', 200);
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')->once()->with(
            'notification.sent',
            Mockery::on(function ($ctx) {
                return $ctx['recipient']['phone'] === '0905112233'
                    && strlen($ctx['content_preview']) === 100
                    && $ctx['business_context'] === ['task_id' => 9];
            }),
        );

        $svc = new NotificationService(
            channels: ['sms' => $this->fakeChannel('sms', fn () => new SendResult('sms', true, '1'))],
            logger: $logger,
        );

        $payload = new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: '0905112233'),
            content: $longContent,
            context: ['task_id' => 9],
        );
        $svc->send($payload);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=NotificationServiceTest`
Expected: FAIL — `NotificationService` not found.

- [ ] **Step 3: Implement `NotificationService`**

```php
<?php

namespace App\Services\Notification;

use App\Services\Notification\Contracts\NotificationChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\SendResult;
use Psr\Log\LoggerInterface;
use Throwable;

class NotificationService
{
    /**
     * @param  array<string, NotificationChannel>  $channels  keyed by channel key (e.g. 'sms')
     */
    public function __construct(
        private array $channels,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return SendResult[]  one result per channel in $payload->channels, in order.
     */
    public function send(NotificationPayload $payload): array
    {
        $results = [];

        foreach ($payload->channels as $key) {
            $results[] = $this->sendOne($key, $payload);
        }

        return $results;
    }

    private function sendOne(string $key, NotificationPayload $payload): SendResult
    {
        if (! isset($this->channels[$key])) {
            $result = new SendResult(channel: $key, success: false, error: "Unknown channel: {$key}");
            $this->log($result, $payload);

            return $result;
        }

        try {
            $result = $this->channels[$key]->send($payload->recipient, $payload);
        } catch (Throwable $e) {
            $result = new SendResult(channel: $key, success: false, error: 'Channel exception: '.$e->getMessage());
        }

        $this->log($result, $payload);

        return $result;
    }

    private function log(SendResult $result, NotificationPayload $payload): void
    {
        $context = [
            'channel' => $result->channel,
            'recipient' => [
                'phone'    => $payload->recipient->phone,
                'email'    => $payload->recipient->email,
                'zalo_id'  => $payload->recipient->zaloId,
                'name'     => $payload->recipient->name,
            ],
            'content_preview'  => substr($payload->content, 0, 100),
            'business_context' => $payload->context,
        ];

        if ($result->success) {
            $context['message_id'] = $result->messageId;
            $this->logger->info('notification.sent', $context);
        } else {
            $context['error'] = $result->error;
            $this->logger->warning('notification.failed', $context);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=NotificationServiceTest`
Expected: PASS (all 7 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Notification/NotificationService.php tests/Unit/Services/Notification/NotificationServiceTest.php
git commit -m "feat(notification): implement NotificationService dispatcher with logging"
```

---

## Task 9: Add `notification` log channel

**Files:**
- Modify: `config/logging.php`

- [ ] **Step 1: Read current `config/logging.php` to find the `channels` array**

Run: `php artisan tinker --execute='echo config_path("logging.php");'`
Then read the file. Find the `'channels' => [` section.

- [ ] **Step 2: Add the `notification` channel inside `channels`**

Insert a new entry (place it alphabetically near other daily channels, typically after `'single'` or `'daily'`):

```php
'notification' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/notification.log'),
    'level'  => 'info',
    'days'   => 14,
    'replace_placeholders' => true,
],
```

- [ ] **Step 3: Verify the channel exists**

Run: `php artisan tinker --execute='\Illuminate\Support\Facades\Log::channel("notification")->info("plan-task-9 smoke test");'`
Expected: no exception. Then check `storage/logs/notification-YYYY-MM-DD.log` exists and contains `plan-task-9 smoke test`.

- [ ] **Step 4: Commit**

```bash
git add config/logging.php
git commit -m "feat(notification): add notification log channel"
```

---

## Task 10: Implement `NotificationServiceProvider`

**Files:**
- Create: `app/Providers/NotificationServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/Services/Notification/NotificationServiceProviderTest.php`

- [ ] **Step 1: Write the failing test (singleton + channel registry verification)**

```php
<?php

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\NotificationService;
use Tests\TestCase;

class NotificationServiceProviderTest extends TestCase
{
    public function test_notification_service_is_singleton(): void
    {
        $a = app(NotificationService::class);
        $b = app(NotificationService::class);
        $this->assertSame($a, $b);
    }

    public function test_unknown_channel_returns_failure(): void
    {
        $svc = app(NotificationService::class);
        $results = $svc->send(new \App\Services\Notification\DTOs\NotificationPayload(
            channels: ['nonexistent'],
            recipient: new \App\Services\Notification\DTOs\Recipient(phone: '0905112233'),
            content: 'hi',
        ));
        $this->assertFalse($results[0]->success);
        $this->assertSame('Unknown channel: nonexistent', $results[0]->error);
    }

    public function test_mail_and_zalo_channels_registered_as_stubs(): void
    {
        $svc = app(NotificationService::class);
        $payload = new \App\Services\Notification\DTOs\NotificationPayload(
            channels: ['mail', 'zalo'],
            recipient: new \App\Services\Notification\DTOs\Recipient(phone: '0905112233', email: 'a@b.c', zaloId: 'z'),
            content: 'hi',
        );
        $results = $svc->send($payload);
        $this->assertCount(2, $results);
        $this->assertSame('Not implemented yet', $results[0]->error);
        $this->assertSame('Not implemented yet', $results[1]->error);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=NotificationServiceProviderTest`
Expected: FAIL — `NotificationService` not bound (or singleton mismatch).

- [ ] **Step 3: Implement `NotificationServiceProvider`**

```php
<?php

namespace App\Providers;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\Channels\SmsChannel;
use App\Services\Notification\Channels\ZaloChannel;
use App\Services\Notification\NotificationService;
use App\Services\Notification\SmsClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
}
```

- [ ] **Step 4: Register the provider in `bootstrap/providers.php`**

Open `bootstrap/providers.php`. Add `App\Providers\NotificationServiceProvider::class` to the array. After the change the file should look like:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\NotificationServiceProvider::class,
];
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=NotificationServiceProviderTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Providers/NotificationServiceProvider.php bootstrap/providers.php tests/Unit/Services/Notification/NotificationServiceProviderTest.php
git commit -m "feat(notification): register NotificationServiceProvider as singleton"
```

---

## Task 11: Implement `sms:test` console command

**Files:**
- Create: `app/Console/Commands/TestSmsCommand.php`
- Test: `tests/Feature/Console/TestSmsCommandTest.php`

Laravel auto-discovers commands in `app/Console/Commands/` (Laravel 11+ default). No registration needed.

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Console;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\NotificationService;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Mockery;
use Tests\TestCase;

class TestSmsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_uses_sms_test_phone_from_settings_when_no_arg(): void
    {
        $settings = Mockery::mock(SettingService::class);
        $settings->shouldReceive('getByKey')->with('sms_test_phone')->andReturn(['value' => '0905999888']);
        $this->app->instance(SettingService::class, $settings);

        $captured = null;
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()->andReturnUsing(function (NotificationPayload $p) use (&$captured) {
            $captured = $p;

            return [new SendResult('sms', true, 'msg-1')];
        });
        $this->app->instance(NotificationService::class, $svc);

        $this->artisan('sms:test')
            ->expectsOutputToContain('msg-1')
            ->assertExitCode(0);

        $this->assertSame('0905999888', $captured->recipient->phone);
        $this->assertSame(['sms'], $captured->channels);
    }

    public function test_uses_phone_argument_when_provided(): void
    {
        $captured = null;
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()->andReturnUsing(function (NotificationPayload $p) use (&$captured) {
            $captured = $p;

            return [new SendResult('sms', true, 'msg-2')];
        });
        $this->app->instance(NotificationService::class, $svc);

        $this->artisan('sms:test', ['phone' => '0901234567'])
            ->assertExitCode(0);

        $this->assertSame('0901234567', $captured->recipient->phone);
    }

    public function test_uses_custom_content_when_option_provided(): void
    {
        $captured = null;
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()->andReturnUsing(function (NotificationPayload $p) use (&$captured) {
            $captured = $p;

            return [new SendResult('sms', true, 'msg-3')];
        });
        $this->app->instance(NotificationService::class, $svc);

        $this->artisan('sms:test', ['phone' => '0901234567', '--content' => 'Custom test'])
            ->assertExitCode(0);

        $this->assertSame('Custom test', $captured->content);
    }

    public function test_exits_with_code_1_on_failure(): void
    {
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->andReturn([new SendResult('sms', false, error: 'service down')]);
        $this->app->instance(NotificationService::class, $svc);

        $this->artisan('sms:test', ['phone' => '0901234567'])
            ->expectsOutputToContain('service down')
            ->assertExitCode(1);
    }

    public function test_exits_with_code_1_when_no_phone_available(): void
    {
        $settings = Mockery::mock(SettingService::class);
        $settings->shouldReceive('getByKey')->with('sms_test_phone')->andReturn(null);
        $this->app->instance(SettingService::class, $settings);

        $this->artisan('sms:test')
            ->expectsOutputToContain('No phone')
            ->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=TestSmsCommandTest`
Expected: FAIL — command `sms:test` not found.

- [ ] **Step 3: Implement `TestSmsCommand`**

```php
<?php

namespace App\Console\Commands;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\NotificationService;
use Illuminate\Console\Command;

class TestSmsCommand extends Command
{
    protected $signature = 'sms:test {phone? : Override recipient phone (default: setting sms_test_phone)} {--content= : Custom content (default: built-in test message)}';

    protected $description = 'Send a test SMS via NotificationService to verify PSC integration';

    public function handle(NotificationService $notifier, SettingService $settings): int
    {
        $phone = $this->argument('phone') ?: ($settings->getByKey('sms_test_phone')['value'] ?? null);
        if (! $phone) {
            $this->error('No phone provided and sms_test_phone setting is empty.');

            return self::FAILURE;
        }

        $content = $this->option('content') ?: 'Thong bao: SMS test from system at '.now()->toDateTimeString();

        $payload = new NotificationPayload(
            channels: ['sms'],
            recipient: new Recipient(phone: $phone),
            content: $content,
        );

        $results = $notifier->send($payload);
        $result = $results[0];

        if ($result->success) {
            $this->info("OK — message_id={$result->messageId}");

            return self::SUCCESS;
        }

        $this->error("FAIL — {$result->error}");

        return self::FAILURE;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TestSmsCommandTest`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/TestSmsCommand.php tests/Feature/Console/TestSmsCommandTest.php
git commit -m "feat(notification): add sms:test artisan command"
```

---

## Task 12: Full test suite + final verification

- [ ] **Step 1: Run the full notification test suite**

Run: `php artisan test --filter=Notification`
Expected: All notification tests pass (Recipient, SendResult, NotificationPayload, StubChannels, SmsChannel, NotificationService, NotificationServiceProvider, TestSmsCommand).

- [ ] **Step 2: Run the entire test suite to catch regressions**

Run: `php artisan test`
Expected: Pre-existing tests still pass. Note: if any pre-existing test was already failing before this work, it should still fail with the same error (record this; do NOT fix it as part of this plan).

- [ ] **Step 3: Verify autoload / static analysis**

Run: `composer dump-autoload`
Run: `vendor/bin/pint --test app/Services/Notification app/Providers/NotificationServiceProvider.php app/Console/Commands/TestSmsCommand.php`
Expected: dump completes; pint reports no fixable issues. If pint reports issues, run without `--test` to apply, then commit:

```bash
vendor/bin/pint app/Services/Notification app/Providers/NotificationServiceProvider.php app/Console/Commands/TestSmsCommand.php
git add -u
git commit -m "style(notification): apply pint formatting"
```

- [ ] **Step 4: Confirm singleton from artisan tinker**

Run: `php artisan tinker --execute='var_dump(app(\App\Services\Notification\NotificationService::class) === app(\App\Services\Notification\NotificationService::class));'`
Expected: `bool(true)`.

- [ ] **Step 5: Manual integration test (optional, requires real PSC credentials in DB)**

Only if admin has populated `sms_server`, `sms_username`, `sms_password`, `sms_test_phone` via the Settings UI:

Run: `php artisan sms:test`
Expected: `OK — message_id=<n>` and a real SMS arrives at the test phone. If `FAIL` appears, capture the error string — that's the actual PSC response and may inform the success/failure threshold (currently `result >= 0 = success`).

If real PSC behavior contradicts the assumed `result >= 0` mapping, file a follow-up to adjust `SmsChannel::send()` step 5 of the spec — do NOT silently change it without explicit user approval.

---

## Acceptance Criteria (from spec §14)

- [x] All unit tests pass (Tasks 1, 2, 3, 6, 7, 8, 10, 11).
- [x] Sai config / sai phone / SOAP fail → `SendResult` failed, no exception bubbles (Task 7 tests + Task 8 test `test_wraps_channel_exception_into_failure_result`).
- [x] Adding a new channel = implement interface + 1 line in registry, no `NotificationService` change (verified by structure: `NotificationService` only iterates `$payload->channels` against `$this->channels` map).
- [x] Singleton verified (Task 10 test `test_notification_service_is_singleton` + Task 12 step 4).
- [ ] `app(NotificationService::class)->send(...)` gửi SMS thành công với config thật — manual verification only (Task 12 step 5).
