<?php

namespace Tests\Unit\Services\Notification\Channels;

use App\Modules\Core\Services\SettingService;
use App\Services\Notification\Channels\MailChannel;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use Mockery;
use Tests\TestCase;

class MailChannelTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private function makeSettings(
        ?string $host = 'smtp.test',
        ?string $username = 'u',
        ?string $password = 'p',
        ?string $port = '587',
        ?string $encryption = 'tls',
        ?string $senderAddress = 'noreply@test.com',
        ?string $senderName = 'Test',
    ): SettingService {
        $m = Mockery::mock(SettingService::class);
        $m->shouldReceive('getByKey')->with('email_smtp_host')->andReturn($host === null ? null : ['value' => $host]);
        $m->shouldReceive('getByKey')->with('email_smtp_username')->andReturn($username === null ? null : ['value' => $username]);
        $m->shouldReceive('getByKey')->with('email_smtp_password')->andReturn($password === null ? null : ['value' => $password]);
        $m->shouldReceive('getByKey')->with('email_smtp_port')->andReturn($port === null ? null : ['value' => $port]);
        $m->shouldReceive('getByKey')->with('email_smtp_encryption')->andReturn($encryption === null ? null : ['value' => $encryption]);
        $m->shouldReceive('getByKey')->with('email_sender_address')->andReturn($senderAddress === null ? null : ['value' => $senderAddress]);
        $m->shouldReceive('getByKey')->with('email_sender_name')->andReturn($senderName === null ? null : ['value' => $senderName]);

        return $m;
    }

    private function send(MailChannel $ch, ?string $email, string $content = 'hello', ?string $subject = null): SendResult
    {
        $recipient = new Recipient(email: $email, name: 'Tuan');
        $payload = new NotificationPayload(['mail'], $recipient, $content, subject: $subject);

        return $ch->send($recipient, $payload);
    }

    public function test_key_returns_mail(): void
    {
        $ch = new MailChannel($this->makeSettings());
        $this->assertSame('mail', $ch->key());
    }

    public function test_returns_failure_when_host_missing(): void
    {
        $ch = new MailChannel($this->makeSettings(host: null));
        $r = $this->send($ch, 'a@b.c');
        $this->assertFalse($r->success);
        $this->assertSame('Mail not configured', $r->error);
    }

    public function test_returns_failure_when_username_missing(): void
    {
        $ch = new MailChannel($this->makeSettings(username: null));
        $r = $this->send($ch, 'a@b.c');
        $this->assertFalse($r->success);
        $this->assertSame('Mail not configured', $r->error);
    }

    public function test_returns_failure_when_password_missing(): void
    {
        $ch = new MailChannel($this->makeSettings(password: null));
        $r = $this->send($ch, 'a@b.c');
        $this->assertFalse($r->success);
        $this->assertSame('Mail not configured', $r->error);
    }

    public function test_returns_failure_when_sender_address_missing(): void
    {
        $ch = new MailChannel($this->makeSettings(senderAddress: null));
        $r = $this->send($ch, 'a@b.c');
        $this->assertFalse($r->success);
        $this->assertSame('Missing sender email address', $r->error);
    }

    public function test_returns_failure_when_recipient_email_missing(): void
    {
        $ch = new MailChannel($this->makeSettings());
        $r = $this->send($ch, null);
        $this->assertFalse($r->success);
        $this->assertSame('Missing recipient email', $r->error);
    }

    public function test_catches_smtp_error_and_returns_failure(): void
    {
        // smtp.test doesn't exist → SMTP connection fails → caught → SendResult failure
        $ch = new MailChannel($this->makeSettings());
        $r = $this->send($ch, 'recipient@test.com', 'Hello', 'Subject');

        $this->assertFalse($r->success);
        $this->assertSame('mail', $r->channel);
        $this->assertStringContainsString('Mail error:', $r->error);
    }
}
