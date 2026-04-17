<?php

namespace Tests\Unit\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\ContentBuilders\TaskCompletedContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskCompletedContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipient(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Manager',
            'email' => 'mgr@test.com',
            'phone' => '0901112233',
            'fcm_token' => 'fcm-mgr-xyz',
        ], $attrs));
    }

    private function makeItem(): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'Soạn công văn',
            'priority' => 'normal',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->addDays(2),
            'processing_status' => 'reported',
            'completion_percent' => 100,
        ]);
    }

    public function test_returns_null_for_non_item_notifiable(): void
    {
        $recipient = $this->makeRecipient();
        $other = User::factory()->create();

        $result = (new TaskCompletedContentBuilder)->build('sms', $recipient, $other);

        $this->assertNull($result);
    }

    public function test_sms_payload(): void
    {
        $item = $this->makeItem();
        $recipient = $this->makeRecipient();

        $payload = (new TaskCompletedContentBuilder)->build('sms', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['sms'], $payload->channels);
        $this->assertSame('0901112233', $payload->recipient->phone);
        $this->assertStringContainsString('Soan cong van', $payload->content);
    }

    public function test_sms_null_when_no_phone(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['phone' => null]);

        $this->assertNull((new TaskCompletedContentBuilder)->build('sms', $r, $item));
    }

    public function test_mail_payload_rendered_with_subject(): void
    {
        $item = $this->makeItem();
        $recipient = $this->makeRecipient();

        $payload = (new TaskCompletedContentBuilder)->build('mail', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['mail'], $payload->channels);
        $this->assertSame('mgr@test.com', $payload->recipient->email);
        $this->assertStringContainsString('Soạn công văn', $payload->subject);
        $this->assertStringContainsString('Soạn công văn', $payload->content);
    }

    public function test_mail_null_when_no_email(): void
    {
        $item = $this->makeItem();
        $r = new User(['name' => 'x', 'email' => null, 'phone' => '090', 'fcm_token' => 't']);

        $this->assertNull((new TaskCompletedContentBuilder)->build('mail', $r, $item));
    }

    public function test_zalo_payload_with_template_data(): void
    {
        $item = $this->makeItem();
        $recipient = $this->makeRecipient();

        $payload = (new TaskCompletedContentBuilder)->build('zalo', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['zalo'], $payload->channels);
        $this->assertSame('', $payload->content);
        $this->assertSame('Manager', $payload->context['customer_name']);
        $this->assertSame('Soạn công văn', $payload->context['task_name']);
        $this->assertSame('task_completed', $payload->context['event']);
    }

    public function test_zalo_null_when_no_phone(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['phone' => null]);

        $this->assertNull((new TaskCompletedContentBuilder)->build('zalo', $r, $item));
    }

    public function test_fcm_payload(): void
    {
        $item = $this->makeItem();
        $recipient = $this->makeRecipient();

        $payload = (new TaskCompletedContentBuilder)->build('fcm', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['fcm'], $payload->channels);
        $this->assertSame('fcm-mgr-xyz', $payload->recipient->fcmToken);
        $this->assertStringContainsString('Soạn công văn', $payload->content);
        $this->assertSame("/task-assignment-items/{$item->id}", $payload->context['url']);
        $this->assertSame('task_completed', $payload->context['type']);
    }

    public function test_fcm_null_when_no_token(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['fcm_token' => null]);

        $this->assertNull((new TaskCompletedContentBuilder)->build('fcm', $r, $item));
    }

    public function test_title_body_context(): void
    {
        $item = $this->makeItem();
        $recipient = $this->makeRecipient();
        $builder = new TaskCompletedContentBuilder;

        $this->assertSame('Công việc đã báo cáo hoàn thành', $builder->title($recipient, $item));
        $this->assertStringContainsString('Soạn công văn', $builder->shortBody($recipient, $item));
        $this->assertSame("/task-assignment-items/{$item->id}", $builder->inAppContext($recipient, $item)['url']);
    }
}
