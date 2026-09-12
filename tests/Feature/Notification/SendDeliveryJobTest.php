<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Modules\Core\Models\UserProfile;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use App\Services\Notification\DTOs\Recipient;
use App\Services\Notification\DTOs\SendResult;
use App\Services\Notification\Jobs\SendDeliveryJob;
use App\Services\Notification\NotificationService;
use App\Services\Notification\Services\ContentBuilderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendDeliveryJobTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use RefreshDatabase;

    private function makeDelivery(string $channel = 'sms', string $status = 'pending'): NotificationDelivery
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'event_key' => 'test_event',
        ]);

        return NotificationDelivery::factory()->create([
            'notification_id' => $notification->id,
            'channel' => $channel,
            'status' => $status,
        ]);
    }

    public function test_noop_when_delivery_already_sent(): void
    {
        $delivery = $this->makeDelivery(status: 'sent');

        $registry = new ContentBuilderRegistry;
        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldNotReceive('send');

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_marks_skipped_when_builder_returns_null(): void
    {
        $delivery = $this->makeDelivery();
        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(null);

        $registry = new ContentBuilderRegistry;
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldNotReceive('send');

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $d = $delivery->fresh();
        $this->assertSame('skipped', $d->status);
        $this->assertSame('Người nhận thiếu thông tin liên hệ cho kênh gửi này.', $d->error_message);
    }

    public function test_marks_sent_on_success(): void
    {
        $delivery = $this->makeDelivery();
        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(
            new NotificationPayload(['sms'], new Recipient(phone: '0905112233'), 'hi')
        );
        $registry = new ContentBuilderRegistry;
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()
            ->andReturn([new SendResult('sms', true, 'msg-42')]);

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $d = $delivery->fresh();
        $this->assertSame('sent', $d->status);
        $this->assertSame('msg-42', $d->message_id);
        $this->assertNotNull($d->sent_at);
        $this->assertNull($d->error_message);
    }

    public function test_permanent_telegram_error_unlinks_chat_id(): void
    {
        $delivery = $this->makeDelivery(channel: 'telegram');
        $user = $delivery->notification->user;
        UserProfile::firstOrCreate(['user_id' => $user->id])->update([
            'telegram_chat_id' => '123456',
            'telegram_linked_at' => now(),
        ]);

        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(
            new NotificationPayload(['telegram'], new Recipient(telegramChatId: '123456'), 'hi')
        );
        $registry = new ContentBuilderRegistry;
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()->andReturn([
            new SendResult('telegram', false, error: 'Forbidden: bot was blocked by the user', permanent: true),
        ]);

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $this->assertSame('failed', $delivery->fresh()->status);
        $this->assertNull(UserProfile::where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_temporary_telegram_error_keeps_chat_id(): void
    {
        $delivery = $this->makeDelivery(channel: 'telegram');
        $user = $delivery->notification->user;
        UserProfile::firstOrCreate(['user_id' => $user->id])->update(['telegram_chat_id' => '123456']);

        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(
            new NotificationPayload(['telegram'], new Recipient(telegramChatId: '123456'), 'hi')
        );
        $registry = new ContentBuilderRegistry;
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')->once()->andReturn([
            new SendResult('telegram', false, error: "Bad Request: can't parse entities"),
        ]);

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        // Tin nhắn dựng hỏng không được phép làm mất liên kết của người dùng.
        $this->assertSame('123456', UserProfile::where('user_id', $user->id)->value('telegram_chat_id'));
    }

    public function test_marks_failed_on_provider_error(): void
    {
        $delivery = $this->makeDelivery();
        $builder = Mockery::mock(ContentBuilder::class);
        $builder->shouldReceive('build')->andReturn(
            new NotificationPayload(['sms'], new Recipient(phone: '0905112233'), 'hi')
        );
        $registry = new ContentBuilderRegistry;
        $registry->register('test_event', $builder);

        $svc = Mockery::mock(NotificationService::class);
        $svc->shouldReceive('send')
            ->andReturn([new SendResult('sms', false, error: 'provider down')]);

        (new SendDeliveryJob($delivery->id))->handle($registry, $svc);

        $d = $delivery->fresh();
        $this->assertSame('failed', $d->status);
        $this->assertSame('provider down', $d->error_message);
        $this->assertNull($d->sent_at);
    }
}
