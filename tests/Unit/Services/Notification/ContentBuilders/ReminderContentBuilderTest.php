<?php

namespace Tests\Unit\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\ContentBuilders\ReminderContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReminderContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipient(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'User A',
            'email' => 'a@test.com',
            'phone' => '0905112233',
            'fcm_token' => 'fcm-a-xyz',
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
        $r = $this->makeRecipient();
        $o = User::factory()->create();
        $this->assertNull((new ReminderContentBuilder('before'))->build('sms', $r, $o));
    }

    public function test_title_varies_by_moment(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();

        $this->assertSame('Nhắc công việc sắp đến hạn', (new ReminderContentBuilder('before'))->title($r, $item));
        $this->assertSame('Công việc đã đến hạn', (new ReminderContentBuilder('on'))->title($r, $item));
        $this->assertSame('Công việc đã quá hạn', (new ReminderContentBuilder('after'))->title($r, $item));
    }

    public function test_short_body_varies_by_moment(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();

        $this->assertStringContainsString('sắp đến hạn', (new ReminderContentBuilder('before'))->shortBody($r, $item));
        $this->assertStringContainsString('đã đến hạn', (new ReminderContentBuilder('on'))->shortBody($r, $item));
        $this->assertStringContainsString('đã quá hạn', (new ReminderContentBuilder('after'))->shortBody($r, $item));
    }

    public function test_in_app_context_includes_url_and_moment(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $ctx = (new ReminderContentBuilder('before'))->inAppContext($r, $item);

        $this->assertSame("/task-assignment-items/{$item->id}", $ctx['url']);
        $this->assertSame('before', $ctx['moment']);
    }

    public function test_sms_payload_for_before(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new ReminderContentBuilder('before'))->build('sms', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertStringContainsString('Sap den han', $p->content);
        $this->assertStringContainsString('Ra soat bao cao', $p->content);
    }

    public function test_sms_payload_for_on(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new ReminderContentBuilder('on'))->build('sms', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertStringContainsString('Den han', $p->content);
    }

    public function test_sms_payload_for_after(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new ReminderContentBuilder('after'))->build('sms', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertStringContainsString('Qua han', $p->content);
    }

    public function test_sms_null_when_no_phone(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['phone' => null]);
        $this->assertNull((new ReminderContentBuilder('before'))->build('sms', $r, $item));
    }

    public function test_mail_payload_has_moment_specific_subject(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();

        $before = (new ReminderContentBuilder('before'))->build('mail', $r, $item);
        $on = (new ReminderContentBuilder('on'))->build('mail', $r, $item);
        $after = (new ReminderContentBuilder('after'))->build('mail', $r, $item);

        $this->assertStringContainsString('sắp đến hạn', $before->subject);
        $this->assertStringContainsString('đến hạn', $on->subject);
        $this->assertStringContainsString('quá hạn', $after->subject);
    }

    public function test_mail_null_when_no_email(): void
    {
        $item = $this->makeItem();
        $r = new User(['name' => 'x', 'email' => null, 'phone' => '090', 'fcm_token' => 't']);
        $this->assertNull((new ReminderContentBuilder('before'))->build('mail', $r, $item));
    }

    public function test_zalo_context_has_moment(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new ReminderContentBuilder('after'))->build('zalo', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertSame('after', $p->context['moment']);
        $this->assertSame('Rà soát báo cáo', $p->context['task_name']);
    }

    public function test_fcm_type_includes_moment(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new ReminderContentBuilder('before'))->build('fcm', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertSame('reminder_before', $p->context['type']);
        $this->assertSame("/task-assignment-items/{$item->id}", $p->context['url']);
    }

    public function test_fcm_null_when_no_token(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['fcm_token' => null]);
        $this->assertNull((new ReminderContentBuilder('before'))->build('fcm', $r, $item));
    }
}
