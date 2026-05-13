<?php

namespace App\Console\Commands;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingInvitation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleanup chair/operator bị seed dư vào meeting_participants.
 *
 * Chair (chairperson_meeting_attendee_id) và Operator (operator_meeting_attendee_id) đã có FK
 * riêng trên `meetings`. Nếu vô tình có row trong `meeting_participants` cùng meeting_attendee_id
 * → trùng vai trò, FE hiển thị họ ở cả 2 chỗ (chủ trì/thư ký + đại biểu).
 *
 * Command này:
 *  1. Tìm participants có meeting_attendee_id trùng chair/operator của meeting cha.
 *  2. Xóa các dependent rows (attendances/vote_responses/discussion_regs/invitations) theo participant_id.
 *  3. Xóa các participant rows đó.
 *
 * Usage:
 *   php artisan meeting:cleanup-chair-op-participants            # xóa thật
 *   php artisan meeting:cleanup-chair-op-participants --dry-run  # chỉ list, không xóa
 */
class CleanupChairOpParticipantsCommand extends Command
{
    protected $signature = 'meeting:cleanup-chair-op-participants {--dry-run : Chỉ liệt kê, không xóa}';

    protected $description = 'Xóa các meeting_participants trùng vai trò chairperson/operator (đã set FK trên meetings).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Lấy participants có meeting_attendee_id trùng với chair hoặc operator của meeting cha.
        $orphans = MeetingParticipant::query()
            ->join('meetings', 'meetings.id', '=', 'meeting_participants.meeting_id')
            ->where(function ($q) {
                $q->whereColumn('meeting_participants.meeting_attendee_id', 'meetings.chairperson_meeting_attendee_id')
                    ->orWhereColumn('meeting_participants.meeting_attendee_id', 'meetings.operator_meeting_attendee_id');
            })
            ->select([
                'meeting_participants.id',
                'meeting_participants.meeting_id',
                'meeting_participants.meeting_attendee_id',
                'meeting_participants.display_name',
                'meetings.title as meeting_title',
                'meetings.chairperson_meeting_attendee_id',
                'meetings.operator_meeting_attendee_id',
            ])
            ->get();

        if ($orphans->isEmpty()) {
            $this->info('Không có participant nào trùng vai trò chair/operator. DB sạch.');

            return self::SUCCESS;
        }

        $this->warn("Tìm thấy {$orphans->count()} participant trùng chair/operator:");
        foreach ($orphans as $p) {
            $role = $p->meeting_attendee_id === $p->chairperson_meeting_attendee_id ? 'chair' : 'operator';
            $this->line("  - [participant #{$p->id}] {$p->display_name} ({$role}) trong meeting #{$p->meeting_id} '{$p->meeting_title}'");
        }

        if ($dryRun) {
            $this->info('[DRY-RUN] Không xóa gì.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Xóa các participant trên + dependent rows (attendances/votes/discussions/invitations)?', true)) {
            $this->warn('Hủy bỏ.');

            return self::SUCCESS;
        }

        $ids = $orphans->pluck('id')->all();
        DB::transaction(function () use ($ids) {
            // Xóa dependent rows trước (tránh FK constraint fail).
            MeetingAttendance::whereIn('meeting_participant_id', $ids)->delete();
            MeetingVoteResponse::whereIn('meeting_participant_id', $ids)->delete();
            MeetingDiscussionRegistration::whereIn('meeting_participant_id', $ids)->delete();
            MeetingInvitation::whereIn('meeting_participant_id', $ids)->delete();
            MeetingParticipant::whereIn('id', $ids)->delete();
        });

        $this->info("Đã xóa {$orphans->count()} participant + dependent rows.");

        return self::SUCCESS;
    }
}
