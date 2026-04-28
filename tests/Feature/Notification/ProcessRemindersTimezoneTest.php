<?php

namespace Tests\Feature\Notification;

use App\Modules\Core\Models\Notification;
use App\Modules\Core\Models\User;
use App\Modules\TaskAssignment\Models\TaskAssignmentItem;
use App\Modules\TaskAssignment\Models\TaskAssignmentReminder;
use App\Services\Notification\Jobs\SendDeliveryJob;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithNotifications;
use Tests\TestCase;

class ProcessRemindersTimezoneTest extends TestCase
{
    use InteractsWithNotifications;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedNotificationConfig();
    }

    private function makeItemWithAssignee(?string $endAt = null): TaskAssignmentItem
    {
        $typeId = DB::table('task_assignment_types')->insertGetId([
            'name' => 'T', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemTypeId = DB::table('task_assignment_item_types')->insertGetId([
            'name' => 'IT', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $docId = DB::table('task_assignment_documents')->insertGetId([
            'task_assignment_type_id' => $typeId, 'name' => 'Doc', 'status' => 'issued',
            'organization_id' => $this->resolveTestOrganization()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $deptId = DB::table('task_assignment_departments')->insertGetId([
            'code' => 'TZ-'.uniqid(), 'name' => 'Dept', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $item = TaskAssignmentItem::create([
            'task_assignment_document_id' => $docId,
            'task_assignment_item_type_id' => $itemTypeId,
            'name' => 'TZTask',
            'priority' => 'normal',
            'deadline_type' => $endAt ? 'has_deadline' : 'no_deadline',
            'end_at' => $endAt,
            'processing_status' => 'todo',
            'completion_percent' => 0,
        ]);
        $u = User::factory()->create();
        DB::table('task_assignment_item_user')->insert([
            'task_assignment_item_id' => $item->id,
            'user_id' => $u->id,
            'department_id' => $deptId,
            'department_role' => 'member',
            'assignment_status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $item->fresh(['users']);
    }

    public function test_now_uses_app_timezone_not_utc(): void
    {
        $this->assertSame(
            'Asia/Ho_Chi_Minh',
            now()->getTimezone()->getName(),
            'now() must follow app.timezone — failure means CI is not running APP_TIMEZONE=Asia/Ho_Chi_Minh'
        );
    }

    public function test_remind_at_comparison_consistent_in_app_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-28 14:37:00', 'Asia/Ho_Chi_Minh'));
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 15:37:00');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame('fired', TaskAssignmentReminder::first()->status);
    }

    public function test_no_implicit_utc_shift_when_storing_end_at(): void
    {
        $item = $this->makeItemWithAssignee(endAt: '2026-04-28 15:00:00');

        $raw = DB::table('task_assignment_items')->where('id', $item->id)->value('end_at');

        $this->assertSame(
            '2026-04-28 15:00:00',
            (string) $raw,
            'DB raw end_at must equal input — any -7h or +7h shift means UTC conversion leaked in'
        );
    }

    public function test_command_fires_correctly_under_app_timezone_change(): void
    {
        Config::set('app.timezone', 'UTC');
        date_default_timezone_set('UTC');
        Carbon::setTestNow(Carbon::parse('2026-04-28 07:37:00', 'UTC'));
        Queue::fake();
        $this->enableEvent('reminder_before', []);
        $this->addReminderSchedule('reminder_before', 'before', 60, ['mail']);
        $this->makeItemWithAssignee(endAt: '2026-04-28 08:37:00');

        $this->artisan('notifications:process-reminders')->assertExitCode(0);

        $this->assertSame(
            'fired',
            TaskAssignmentReminder::first()->status,
            'Cron must follow current app.timezone config — failure means hard-coded TZ leaked in'
        );

        // Reset for subsequent tests
        Config::set('app.timezone', 'Asia/Ho_Chi_Minh');
        date_default_timezone_set('Asia/Ho_Chi_Minh');
    }
}
