<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
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
        $this->assertSame('Recipient missing field for channel', $d->error_message);
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
