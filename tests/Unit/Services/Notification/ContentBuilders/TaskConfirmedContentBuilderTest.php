<?php

namespace Tests\Unit\Services\Notification\ContentBuilders;

use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Services\Notification\ContentBuilders\TaskConfirmedContentBuilder;
use App\Services\Notification\DTOs\NotificationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TaskConfirmedContentBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makeRecipient(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Assignee',
            'email' => 'a@test.com',
            'phone' => '0901112233',
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
            'name' => 'Soạn công văn',
            'priority' => 'normal',
            'deadline_type' => 'has_deadline',
            'end_at' => now()->addDays(2),
            'processing_status' => 'done',
            'completion_percent' => 100,
            'completed_at' => now(),
        ]);
    }

    public function test_returns_null_for_non_item_notifiable(): void
    {
        $r = $this->makeRecipient();
        $o = User::factory()->create();
        $this->assertNull((new TaskConfirmedContentBuilder)->build('sms', $r, $o));
    }

    public function test_sms_payload(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new TaskConfirmedContentBuilder)->build('sms', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertSame(['sms'], $p->channels);
        $this->assertStringContainsString('Soan cong van', $p->content);
        $this->assertStringContainsString('xac nhan hoan thanh', $p->content);
    }

    public function test_sms_null_when_no_phone(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['phone' => null]);
        $this->assertNull((new TaskConfirmedContentBuilder)->build('sms', $r, $item));
    }

    public function test_mail_payload_rendered(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new TaskConfirmedContentBuilder)->build('mail', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertSame(['mail'], $p->channels);
        $this->assertStringContainsString('Soạn công văn', $p->subject);
        $this->assertStringContainsString('Soạn công văn', $p->content);
    }

    public function test_mail_null_when_no_email(): void
    {
        $item = $this->makeItem();
        $r = new User(['name' => 'x', 'email' => null, 'phone' => '090', 'fcm_token' => 't']);
        $this->assertNull((new TaskConfirmedContentBuilder)->build('mail', $r, $item));
    }

    public function test_zalo_payload_event_task_confirmed(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $p = (new TaskConfirmedContentBuilder)->build('zalo', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertSame(['zalo'], $p->channels);
        $this->assertSame('', $p->content);
        $this->assertSame('task_confirmed', $p->context['event']);
        $this->assertSame('Soạn công văn', $p->context['task_name']);
    }

    public function test_zalo_null_when_no_phone(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['phone' => null]);
        $this->assertNull((new TaskConfirmedContentBuilder)->build('zalo', $r, $item));
    }

    public function test_fcm_payload(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        \App\Modules\Core\Models\FcmToken::create([
            'user_id' => $r->id, 'device_id' => 'd1',
            'fcm_token' => 'fcm-a-xyz', 'last_used_at' => now(),
        ]);
        $p = (new TaskConfirmedContentBuilder)->build('fcm', $r, $item);

        $this->assertInstanceOf(NotificationPayload::class, $p);
        $this->assertSame(['fcm'], $p->channels);
        $this->assertSame(['fcm-a-xyz'], $p->recipient->fcmTokens);
        $this->assertSame('task_confirmed', $p->context['type']);
        $this->assertSame("/task-assignment-items/{$item->id}", $p->context['url']);
    }

    public function test_fcm_null_when_no_token(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient(['fcm_token' => null]);
        $this->assertNull((new TaskConfirmedContentBuilder)->build('fcm', $r, $item));
    }

    public function test_title_body_context(): void
    {
        $item = $this->makeItem();
        $r = $this->makeRecipient();
        $builder = new TaskConfirmedContentBuilder;

        $this->assertSame('Công việc đã được xác nhận hoàn thành', $builder->title($r, $item));
        $this->assertStringContainsString('Soạn công văn', $builder->shortBody($r, $item));
        $this->assertStringContainsString('đã được xác nhận hoàn thành', $builder->shortBody($r, $item));
        $this->assertSame("/task-assignment-items/{$item->id}", $builder->inAppContext($r, $item)['url']);
    }
}
