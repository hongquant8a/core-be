<?php

namespace Tests\Unit\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentDocument;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\ContentBuilders\DocumentIssuedContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentIssuedContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipient(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Tuấn',
            'email' => 'tuan@test.com',
            'phone' => '0905112233',
            'fcm_token' => 'fcm-token-xyz',
        ], $attrs));
    }

    private function makeItemWithDocument(): TaskAssignmentItem
    {
        $doc = TaskAssignmentDocument::factory()->create([
            'name' => 'Kế hoạch quý 2',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        return TaskAssignmentItem::factory()->create([
            'task_assignment_document_id' => $doc->id,
            'name' => 'Rà soát báo cáo',
            'priority' => 'normal',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->addDays(2)->setTime(17, 0),
            'processing_status' => 'todo',
            'completion_percent' => 0,
        ]);
    }

    public function test_returns_null_for_non_item_notifiable(): void
    {
        $recipient = $this->makeRecipient();
        $other = User::factory()->create();

        $result = (new DocumentIssuedContentBuilder())->build('sms', $recipient, $other);

        $this->assertNull($result);
    }

    public function test_returns_null_for_unknown_channel(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient();

        $result = (new DocumentIssuedContentBuilder())->build('unknown', $recipient, $item);

        $this->assertNull($result);
    }

    public function test_sms_payload_includes_task_name_and_deadline(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient();

        $payload = (new DocumentIssuedContentBuilder())->build('sms', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['sms'], $payload->channels);
        $this->assertSame('0905112233', $payload->recipient->phone);
        $this->assertStringContainsString('Ra soat bao cao', $payload->content); // ASCII stripped
        $this->assertStringContainsString('Tran trong', $payload->content);
    }

    public function test_sms_returns_null_when_recipient_phone_missing(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient(['phone' => null]);

        $payload = (new DocumentIssuedContentBuilder())->build('sms', $recipient, $item);

        $this->assertNull($payload);
    }

    public function test_mail_payload_renders_html_with_subject(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient();

        $payload = (new DocumentIssuedContentBuilder())->build('mail', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['mail'], $payload->channels);
        $this->assertSame('tuan@test.com', $payload->recipient->email);
        $this->assertSame('Giao việc mới: Rà soát báo cáo', $payload->subject);
        $this->assertStringContainsString('Rà soát báo cáo', $payload->content);
        $this->assertStringContainsString('Kế hoạch quý 2', $payload->content);
    }

    public function test_mail_returns_null_when_recipient_email_missing(): void
    {
        $item = $this->makeItemWithDocument();

        // Use an unpersisted User to bypass the NOT NULL DB constraint on email
        $recipient = new User([
            'name' => 'Tuấn',
            'email' => null,
            'phone' => '0905112233',
            'fcm_token' => 'fcm-token-xyz',
        ]);

        $payload = (new DocumentIssuedContentBuilder())->build('mail', $recipient, $item);

        $this->assertNull($payload);
    }

    public function test_zalo_payload_maps_context_to_template_data(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient();

        $payload = (new DocumentIssuedContentBuilder())->build('zalo', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['zalo'], $payload->channels);
        $this->assertSame('0905112233', $payload->recipient->phone);
        $this->assertSame('', $payload->content); // zalo uses template_data, not free text
        $this->assertSame('Tuấn', $payload->context['customer_name']);
        $this->assertSame('Rà soát báo cáo', $payload->context['task_name']);
        $this->assertSame('Kế hoạch quý 2', $payload->context['document_name']);
    }

    public function test_zalo_returns_null_when_recipient_phone_missing(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient(['phone' => null]);

        $payload = (new DocumentIssuedContentBuilder())->build('zalo', $recipient, $item);

        $this->assertNull($payload);
    }

    public function test_fcm_payload_includes_token_and_data(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient();

        $payload = (new DocumentIssuedContentBuilder())->build('fcm', $recipient, $item);

        $this->assertInstanceOf(NotificationPayload::class, $payload);
        $this->assertSame(['fcm'], $payload->channels);
        $this->assertSame('fcm-token-xyz', $payload->recipient->fcmToken);
        $this->assertStringContainsString('Rà soát báo cáo', $payload->content);
        $this->assertSame("/task-assignment-items/{$item->id}", $payload->context['url']);
        $this->assertSame('document_issued', $payload->context['type']);
    }

    public function test_fcm_returns_null_when_fcm_token_missing(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient(['fcm_token' => null]);

        $payload = (new DocumentIssuedContentBuilder())->build('fcm', $recipient, $item);

        $this->assertNull($payload);
    }

    public function test_title_and_body_and_context_methods(): void
    {
        $item = $this->makeItemWithDocument();
        $recipient = $this->makeRecipient();
        $builder = new DocumentIssuedContentBuilder();

        $title = $builder->title($recipient, $item);
        $body = $builder->shortBody($recipient, $item);
        $ctx = $builder->inAppContext($recipient, $item);

        $this->assertSame('Bạn được giao công việc mới', $title);
        $this->assertStringContainsString('Rà soát báo cáo', $body);
        $this->assertSame("/task-assignment-items/{$item->id}", $ctx['url']);
        $this->assertSame($item->document_id, $ctx['document_id']);
    }
}
