<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\NotificationDelivery;
use App\Modules\Core\Models\User;
use App\Services\Notification\Contracts\ContentBuilder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use App\Services\Notification\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use InteractsWithNotifications;
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        setPermissionsTeamId($this->resolveTestOrganization()->id);
    }

    private function fakeBuilder(string $title = 'T', string $body = 'B', array $ctx = []): ContentBuilder
    {
        $m = Mockery::mock(ContentBuilder::class);
        $m->shouldReceive('title')->andReturn($title);
        $m->shouldReceive('shortBody')->andReturn($body);
        $m->shouldReceive('inAppContext')->andReturn($ctx);

        return $m;
    }

    public function test_creates_notification_row_with_builder_content(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        $n = app(NotificationDispatcher::class)->dispatch(
            eventKey: 'document_issued',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms'],
            builder: $this->fakeBuilder('Hello', 'World', ['url' => '/x']),
        );

        $this->assertSame($user->id, $n->user_id);
        $this->assertSame('document_issued', $n->event_key);
        $this->assertSame('Hello', $n->title);
        $this->assertSame('World', $n->body);
        $this->assertSame(['url' => '/x'], $n->context);
        $this->assertSame(User::class, $n->notifiable_type);
        $this->assertSame($notifiable->id, $n->notifiable_id);
    }

    public function test_creates_one_delivery_per_channel(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        app(NotificationDispatcher::class)->dispatch(
            eventKey: 'document_issued',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms', 'mail', 'fcm'],
            builder: $this->fakeBuilder(),
        );

        $this->assertSame(3, NotificationDelivery::count());
        $channels = NotificationDelivery::pluck('channel')->sort()->values()->all();
        $this->assertSame(['fcm', 'mail', 'sms'], $channels);
        $this->assertSame(3, NotificationDelivery::where('status', 'pending')->count());
    }

    public function test_dispatches_one_job_per_delivery_on_notifications_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        app(NotificationDispatcher::class)->dispatch(
            eventKey: 'test',
            recipient: $user,
            notifiable: $notifiable,
            channels: ['sms', 'mail'],
            builder: $this->fakeBuilder(),
        );

        Queue::assertPushed(SendDeliveryJob::class, 2);
        Queue::assertPushedOn('notifications', SendDeliveryJob::class);
    }

    public function test_empty_channels_creates_notification_without_deliveries(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $notifiable = User::factory()->create();

        $n = app(NotificationDispatcher::class)->dispatch(
            eventKey: 'test',
            recipient: $user,
            notifiable: $notifiable,
            channels: [],
            builder: $this->fakeBuilder(),
        );

        $this->assertSame(1, Notification::count());
        $this->assertSame(0, NotificationDelivery::count());
        Queue::assertNothingPushed();
    }
}
