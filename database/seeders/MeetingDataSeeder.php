<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\MediaService;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAgenda;
use App\Modules\Meeting\Models\MeetingAttendance;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use App\Modules\Meeting\Models\MeetingDiscussionRegistration;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingDocumentType;
use App\Modules\Meeting\Models\MeetingLocation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingType;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo seeder cho module Meeting — phục vụ demo sản phẩm.
 *
 * 3 nhóm tài khoản dùng để login demo (mọi meeting đều xoay quanh 3 nhóm này):
 *  - chutri@example.com   — Chủ trì cố định, mọi meeting đều là người này
 *  - thuky@example.com    — Thư ký cố định, mọi meeting đều là người này
 *  - daibieu1..3@example.com — Đại biểu, được mời ở tất cả meeting
 *
 * 6 meeting với trạng thái khác nhau (past finished, in-progress, upcoming, draft)
 * — login bất kỳ user nào trong 5 user trên sẽ thấy nhiều meeting để demo.
 *
 * Mọi meeting tạo bởi seeder có marker `[DEMO]` ở description để cleanup idempotent.
 * Tài liệu attach dùng file `template-upload.xlsx` ở project root.
 */
class MeetingDataSeeder extends Seeder
{
    private const DEMO_MARKER = '[DEMO]';

    private const PASSWORD = 'password';

    private int $orgId = 1;

    /** @var array{chutri: User, thuky: User, daibieus: array<int, User>} */
    private array $demoUsers;

    /** @var array{chutri: MeetingAttendee, thuky: MeetingAttendee, daibieus: array<int, MeetingAttendee>, extras: array<int, MeetingAttendee>} */
    private array $demoAttendees;

    private MediaService $mediaService;

    /** Path tới file .docx demo (generate 1 lần ở đầu run, dùng chung cho mọi document). */
    private ?string $demoDocxPath = null;

    public function run(): void
    {
        $admin = User::firstWhere('email', 'admin@example.com') ?? User::first();
        if (! $admin) {
            $this->command?->warn('MeetingDataSeeder: chưa có admin — skip.');

            return;
        }
        auth()->setUser($admin);
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($this->orgId);
        }

        $this->mediaService = app(MediaService::class);
        $this->demoDocxPath = $this->generateSharedDemoDocx();

        $this->cleanupOldDemoMeetings();
        $this->seedDemoUsers();
        $catalogs = $this->seedCatalogs();
        $this->seedDemoAttendees($catalogs['groups']);

        // ── Past finished (8 cuộc) — trải dài 3 tháng gần nhất
        $this->seedFinishedMeeting($catalogs, 'quý trước', now()->subMonths(3)->setTime(8, 0));
        $this->seedFinishedMeeting($catalogs, '2 tháng trước', now()->subMonths(2)->setTime(14, 30));
        $this->seedFinishedMeeting($catalogs, 'tháng trước', now()->subMonth()->setTime(9, 0));
        $this->seedFinishedMeeting($catalogs, '3 tuần trước', now()->subWeeks(3)->setTime(8, 30));
        $this->seedFinishedMeeting($catalogs, '2 tuần trước', now()->subWeeks(2)->setTime(15, 0));
        $this->seedFinishedMeeting($catalogs, 'tuần trước', now()->subWeek()->setTime(8, 30));
        $this->seedFinishedMeeting($catalogs, '2 ngày trước', now()->subDays(2)->setTime(13, 30));
        $this->seedFinishedMeeting($catalogs, 'hôm qua', now()->subDay()->setTime(14, 0));

        // ── In-progress (2 cuộc) — đang diễn ra
        $this->seedInProgressMeeting($catalogs, now()->copy()->setTime(now()->hour, 0)->subHour(), 'phiên sáng');
        $this->seedInProgressMeeting($catalogs, now()->copy()->setTime(max(8, now()->hour - 2), 0), 'phiên chiều');

        // ── Upcoming (7 cuộc) — trải dài 3 tháng tới
        $this->seedUpcomingMeeting($catalogs, 'chiều nay', now()->addHours(3)->setTime(15, 0), withFullData: true);
        $this->seedUpcomingMeeting($catalogs, 'ngày mai', now()->addDay()->setTime(8, 30), withFullData: true);
        $this->seedUpcomingMeeting($catalogs, 'vài ngày tới', now()->addDays(3)->setTime(14, 0), withFullData: true);
        $this->seedUpcomingMeeting($catalogs, 'tuần tới', now()->addWeek()->setTime(9, 0), withFullData: false);
        $this->seedUpcomingMeeting($catalogs, '2 tuần tới', now()->addWeeks(2)->setTime(14, 30), withFullData: false);
        $this->seedUpcomingMeeting($catalogs, 'tháng tới', now()->addMonth()->setTime(8, 0), withFullData: false);
        $this->seedUpcomingMeeting($catalogs, '2 tháng tới', now()->addMonths(2)->setTime(9, 30), withFullData: false);

        // ── Draft (3 cuộc) — chưa publish
        $this->seedDraftMeeting($catalogs, now()->addWeeks(2)->setTime(9, 0), 'kỳ họp giữa tháng');
        $this->seedDraftMeeting($catalogs, now()->addMonths(1)->setTime(14, 0), 'kỳ chuyên đề');
        $this->seedDraftMeeting($catalogs, now()->addMonths(3)->setTime(8, 0), 'kỳ thường niên');

        auth()->forgetUser();

        if ($this->demoDocxPath && is_file($this->demoDocxPath)) {
            @unlink($this->demoDocxPath);
        }

        $this->command?->info('MeetingDataSeeder: seeded 20 demo meetings (8 finished + 2 in-progress + 7 upcoming + 3 draft) + 5 demo users.');
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Cleanup + users + catalogs
    // ────────────────────────────────────────────────────────────────────────────

    private function cleanupOldDemoMeetings(): void
    {
        $ids = Meeting::query()
            ->where('organization_id', $this->orgId)
            ->where('content', 'like', '%'.self::DEMO_MARKER.'%')
            ->pluck('id')
            ->all();
        if (empty($ids)) {
            return;
        }
        // Xóa sub-tables thủ công vì 1 số FK không cascade đúng cách.
        \Illuminate\Support\Facades\DB::table('meeting_vote_responses')
            ->whereIn('meeting_vote_topic_id', MeetingVoteTopic::whereIn('meeting_id', $ids)->pluck('id'))
            ->delete();
        MeetingVoteTopic::whereIn('meeting_id', $ids)->delete();
        MeetingDiscussionRegistration::whereIn('meeting_id', $ids)->delete();
        MeetingAttendance::whereIn('meeting_id', $ids)->delete();
        MeetingDocument::whereIn('meeting_id', $ids)->delete();
        MeetingAgenda::whereIn('meeting_id', $ids)->delete();
        \App\Modules\Meeting\Models\MeetingInvitation::whereIn('meeting_id', $ids)->delete();
        MeetingParticipant::whereIn('meeting_id', $ids)->delete();
        Meeting::whereIn('id', $ids)->delete();
    }

    private function seedDemoUsers(): void
    {
        // Sau refactor 2026-05-10: chỉ còn 1 role meeting "Đại biểu". Chair/operator phân biệt
        // qua FK chairperson_meeting_attendee_id / operator_meeting_attendee_id trên meeting,
        // không qua Spatie role.
        $daiBieuRole = Role::firstWhere('name', 'Đại biểu');

        $chutri = $this->upsertUser('chutri@example.com', 'Lê Văn Chủ Trì', 'chutri');
        if ($daiBieuRole) $chutri->syncRoles([$daiBieuRole]);

        $thuky = $this->upsertUser('thuky@example.com', 'Nguyễn Thị Thư Ký', 'thuky');
        if ($daiBieuRole) $thuky->syncRoles([$daiBieuRole]);

        $daibieus = [];
        $names = ['Trần Văn Đại Biểu 1', 'Phạm Thị Đại Biểu 2', 'Hoàng Văn Đại Biểu 3'];
        foreach (range(1, 3) as $i) {
            $u = $this->upsertUser("daibieu{$i}@example.com", $names[$i - 1], "daibieu{$i}");
            if ($daiBieuRole) $u->syncRoles([$daiBieuRole]);
            $daibieus[] = $u;
        }

        $this->demoUsers = [
            'chutri' => $chutri,
            'thuky' => $thuky,
            'daibieus' => $daibieus,
        ];
    }

    private function upsertUser(string $email, string $name, string $userName): User
    {
        $user = User::firstWhere('email', $email);
        if (! $user) {
            $user = User::create([
                'email' => $email,
                'name' => $name,
                'user_name' => $userName,
                'password' => Hash::make(self::PASSWORD),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $user->forceFill(['created_by' => $user->id, 'updated_by' => $user->id])->save();
        }

        return $user;
    }

    /**
     * @return array{type: MeetingType, location: MeetingLocation, docTypes: array<string, MeetingDocumentType>, groups: array<string, MeetingAttendeeGroup>}
     */
    private function seedCatalogs(): array
    {
        $type = MeetingType::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Họp HĐND'],
            ['description' => 'Họp Hội đồng nhân dân', 'status' => 'active']
        );

        $location = MeetingLocation::firstOrCreate(
            ['organization_id' => $this->orgId, 'name' => 'Hội trường UBND phường'],
            [
                'description' => 'Hội trường tầng 2',
                'address' => 'Số 1 Trần Phú, P. Hòa Cường, Đà Nẵng',
                'google_maps_url' => 'https://maps.google.com/?q=16.0544,108.2022',
                'status' => 'active',
            ]
        );

        $docTypes = [];
        foreach ([
            'tai-lieu-chinh' => 'Tài liệu chính',
            'bao-cao' => 'Báo cáo',
            'phu-luc' => 'Phụ lục',
            'ket-luan' => 'Tài liệu kết luận cuộc họp',
        ] as $slug => $name) {
            $docTypes[$slug] = MeetingDocumentType::firstOrCreate(
                ['organization_id' => $this->orgId, 'name' => $name],
                ['description' => $name, 'status' => 'active']
            );
        }

        $groups = [];
        foreach ([
            'lanh-dao' => 'Lãnh đạo',
            'dai-bieu' => 'Đại biểu',
            'khach-moi' => 'Khách mời',
        ] as $slug => $name) {
            $groups[$slug] = MeetingAttendeeGroup::firstOrCreate(
                ['organization_id' => $this->orgId, 'name' => $name],
                ['description' => $name, 'status' => 'active']
            );
        }

        return ['type' => $type, 'location' => $location, 'docTypes' => $docTypes, 'groups' => $groups];
    }

    /**
     * @param  array<string, MeetingAttendeeGroup>  $groups
     */
    private function seedDemoAttendees(array $groups): void
    {
        $chutri = $this->upsertAttendee(
            $this->demoUsers['chutri']->id,
            $groups['lanh-dao']->id,
            'Chủ tịch HĐND',
            'UBND phường'
        );
        $thuky = $this->upsertAttendee(
            $this->demoUsers['thuky']->id,
            $groups['lanh-dao']->id,
            'Thư ký HĐND',
            'Văn phòng UBND'
        );

        $daibieus = [];
        $positions = ['Đại biểu HĐND', 'Trưởng phòng', 'Phó trưởng phòng'];
        $depts = ['Phòng Tài chính', 'Phòng Văn hóa', 'Phòng Giáo dục'];
        foreach ($this->demoUsers['daibieus'] as $i => $user) {
            $daibieus[] = $this->upsertAttendee(
                $user->id,
                $groups['dai-bieu']->id,
                $positions[$i] ?? 'Đại biểu',
                $depts[$i] ?? 'Phòng ban'
            );
        }

        // Extra attendees không có user account — để dropdown nhiều người + thêm thực tế.
        $extras = [];
        $extraData = [
            ['Đỗ Quốc Bình', 'Phó chủ tịch UBND', 'UBND phường'],
            ['Nguyễn Thị Lan', 'Trưởng ban Kinh tế', 'Ban Kinh tế'],
            ['Trần Anh Tuấn', 'Trưởng phòng Nội vụ', 'Phòng Nội vụ'],
            ['Lê Thị Hoa', 'Cán bộ phụ trách', 'Văn phòng'],
        ];
        foreach ($extraData as [$displayName, $pos, $dept]) {
            // Extras vẫn cần user_id (FK NOT NULL trên meeting_attendees) — tạo placeholder user.
            $slug = Str::slug($displayName);
            $user = $this->upsertUser("{$slug}@example.com", $displayName, $slug);
            $extras[] = $this->upsertAttendee(
                $user->id,
                $groups['khach-moi']->id,
                $pos,
                $dept
            );
        }

        $this->demoAttendees = [
            'chutri' => $chutri,
            'thuky' => $thuky,
            'daibieus' => $daibieus,
            'extras' => $extras,
        ];
    }

    private function upsertAttendee(int $userId, int $groupId, string $position, string $dept): MeetingAttendee
    {
        $attendee = MeetingAttendee::firstWhere([
            'organization_id' => $this->orgId,
            'user_id' => $userId,
        ]);
        if (! $attendee) {
            $attendee = MeetingAttendee::create([
                'organization_id' => $this->orgId,
                'user_id' => $userId,
                'position_name' => $position,
                'department_name' => $dept,
                'status' => 'active',
            ]);
        }

        // Group relation qua pivot table (meeting_attendee_group_members).
        $attendee->groups()->syncWithoutDetaching([
            $groupId => ['organization_id' => $this->orgId],
        ]);

        return $attendee;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Meeting builders — scenarios khác nhau
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Past finished — đầy đủ data: attendance, votes, discussion, documents, kết luận.
     *
     * @param  array{type: MeetingType, location: MeetingLocation, docTypes: array, groups: array}  $cat
     */
    private function seedFinishedMeeting(array $cat, string $label, Carbon $startTime): Meeting
    {
        $meeting = $this->createMeeting(
            cat: $cat,
            title: "Cuộc họp HĐND thường kỳ — {$label}",
            content: self::DEMO_MARKER.' Cuộc họp đã kết thúc. Có đầy đủ điểm danh, biểu quyết, kết luận và tài liệu.',
            startTime: $startTime,
            duration: 4,  // hours
            status: 'published',
            attendanceLocked: true,
        );

        $participants = $this->createParticipantsForMeeting($meeting);
        $this->createInvitations($meeting, $participants);
        $agendas = $this->createAgendas($meeting);
        $this->createDocumentsWithFiles($meeting, $cat['docTypes'], $agendas, includeKetLuan: true);
        $this->createAttendances($meeting, $participants, fullCheckIn: true);
        $this->createVoteTopics($meeting, $agendas, $participants, allClosed: true);
        $this->createDiscussionRegistrations($meeting, $agendas, $participants, allCompleted: true);

        return $meeting;
    }

    /**
     * In-progress — đang diễn ra, partial attendance + 1 vote đang mở.
     */
    private function seedInProgressMeeting(array $cat, Carbon $startTime, ?string $titleSuffix = null): Meeting
    {
        $title = 'Cuộc họp HĐND chuyên đề — đang diễn ra'.($titleSuffix ? ' '.$titleSuffix : '');
        $meeting = $this->createMeeting(
            cat: $cat,
            title: $title,
            content: self::DEMO_MARKER.' Cuộc họp đang diễn ra. Đang điểm danh và biểu quyết.',
            startTime: $startTime,
            duration: 3,
            status: 'published',
            attendanceLocked: false,
        );

        $participants = $this->createParticipantsForMeeting($meeting);
        $this->createInvitations($meeting, $participants);
        $agendas = $this->createAgendas($meeting);
        $this->createDocumentsWithFiles($meeting, $cat['docTypes'], $agendas, includeKetLuan: false);

        // Partial check-in: 60% đại biểu đã check in
        $this->createAttendances($meeting, $participants, fullCheckIn: false);

        // 1 vote topic đang mở, 1 chưa mở
        $this->createVoteTopics($meeting, $agendas, $participants, allClosed: false);

        $this->createDiscussionRegistrations($meeting, $agendas, $participants, allCompleted: false);

        return $meeting;
    }

    /**
     * Upcoming — sắp diễn ra, có agenda + documents nhưng chưa có attendance/vote.
     */
    private function seedUpcomingMeeting(array $cat, string $label, Carbon $startTime, bool $withFullData): Meeting
    {
        $meeting = $this->createMeeting(
            cat: $cat,
            title: "Cuộc họp HĐND — {$label}",
            content: self::DEMO_MARKER.' Cuộc họp sắp diễn ra. Đã có chương trình và tài liệu.',
            startTime: $startTime,
            duration: 3,
            status: 'published',
            attendanceLocked: false,
        );

        $participants = $this->createParticipantsForMeeting($meeting);
        $this->createInvitations($meeting, $participants);
        $agendas = $this->createAgendas($meeting);

        if ($withFullData) {
            $this->createDocumentsWithFiles($meeting, $cat['docTypes'], $agendas, includeKetLuan: false);
        }

        return $meeting;
    }

    /**
     * Draft — chưa publish, thông tin tối thiểu.
     */
    private function seedDraftMeeting(array $cat, Carbon $startTime, ?string $titleSuffix = null): Meeting
    {
        $title = 'Cuộc họp dự thảo — chưa công bố'.($titleSuffix ? ' '.$titleSuffix : '');
        $meeting = $this->createMeeting(
            cat: $cat,
            title: $title,
            content: self::DEMO_MARKER.' Cuộc họp đang ở trạng thái dự thảo, chưa công bố cho đại biểu.',
            startTime: $startTime,
            duration: 2,
            status: 'draft',
            attendanceLocked: false,
        );

        $this->createAgendas($meeting, simple: true);

        return $meeting;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // Building blocks
    // ────────────────────────────────────────────────────────────────────────────

    private function createMeeting(
        array $cat,
        string $title,
        string $content,
        Carbon $startTime,
        int $duration,
        string $status,
        bool $attendanceLocked,
    ): Meeting {
        return Meeting::create([
            'organization_id' => $this->orgId,
            'meeting_type_id' => $cat['type']->id,
            'meeting_location_id' => $cat['location']->id,
            'chairperson_meeting_attendee_id' => $this->demoAttendees['chutri']->id,
            'operator_meeting_attendee_id' => $this->demoAttendees['thuky']->id,
            'title' => $title,
            'content' => $content,
            // Xen kẽ public/private để FE test cả 2 case (50/50 random).
            'is_public' => (bool) random_int(0, 1),
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours($duration),
            // Khung điểm danh: mở 1h trước start, đóng 1h sau start.
            'attendance_open_at' => $startTime->copy()->subHour(),
            'attendance_close_at' => $startTime->copy()->addHour(),
            'status' => $status,
            'attendance_locked' => $attendanceLocked,
            'view_count' => random_int(20, 150),
            'published_at' => $status === 'published' ? $startTime->copy()->subDays(3) : null,
            'created_by' => $this->demoUsers['thuky']->id,
            'updated_by' => $this->demoUsers['thuky']->id,
        ]);
    }

    /** @return array<int, MeetingParticipant> participants = chỉ đại biểu (3 daibieu + 4 extras).
     * Chair + Operator KHÔNG vào participants vì đã có FK riêng chairperson_meeting_attendee_id /
     * operator_meeting_attendee_id trên meetings, và được mời qua invitation theo attendee_id. */
    private function createParticipantsForMeeting(Meeting $meeting): array
    {
        $all = array_merge(
            $this->demoAttendees['daibieus'],
            $this->demoAttendees['extras']
        );

        $participants = [];
        foreach ($all as $attendee) {
            $participants[] = MeetingParticipant::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'meeting_attendee_id' => $attendee->id,
                'display_name' => $attendee->user?->name ?? 'Đại biểu',
                'position_name' => $attendee->position_name,
                'department_name' => $attendee->department_name,
                'response_status' => 'accepted',
                'responded_at' => now()->subDays(random_int(1, 5)),
            ]);
        }

        return $participants;
    }

    /** @param array<int, MeetingParticipant> $participants */
    private function createInvitations(Meeting $meeting, array $participants): void
    {
        foreach ($participants as $p) {
            \App\Modules\Meeting\Models\MeetingInvitation::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'meeting_participant_id' => $p->id,
                'send_type' => 'now',
                'status' => 'sent',
                'sent_at' => $meeting->published_at ?? now()->subDay(),
            ]);
        }
    }

    /** @return array<int, MeetingAgenda> */
    private function createAgendas(Meeting $meeting, bool $simple = false): array
    {
        $items = $simple
            ? [
                ['Nội dung dự thảo 1', 30],
                ['Nội dung dự thảo 2', 30],
            ]
            : [
                ['Khai mạc + thông qua chương trình', 15],
                ['Báo cáo tình hình kinh tế xã hội', 45],
                ['Thảo luận, chất vấn', 60],
                ['Biểu quyết nghị quyết', 30],
                ['Bế mạc', 15],
            ];

        $agendas = [];
        $cursor = $meeting->start_time->copy();
        foreach ($items as $i => [$content, $minutes]) {
            $agendas[] = MeetingAgenda::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'start_time' => $cursor->copy(),
                'end_time' => $cursor->copy()->addMinutes($minutes),
                'content' => $content,
                'person_in_charge' => $i === 0 ? $this->demoUsers['chutri']->name : null,
                'allow_discussion_registration' => $i === 2,
                'discussion_duration_minutes' => $i === 2 ? 5 : null,
                'allow_question_registration' => $i === 2,
                'question_duration_minutes' => $i === 2 ? 3 : null,
                // Agenda "Biểu quyết nghị quyết" (index 3 ở full meeting) → allow vote.
                'allow_vote_registration' => $i === 3,
                'sort_order' => $i + 1,
            ]);
            $cursor->addMinutes($minutes);
        }

        return $agendas;
    }

    /** @param array<int, MeetingAgenda> $agendas */
    private function createDocumentsWithFiles(Meeting $meeting, array $docTypes, array $agendas, bool $includeKetLuan): void
    {
        $configs = [
            ['Báo cáo tình hình kinh tế xã hội', $docTypes['bao-cao'], $agendas[1] ?? null, '01/2026/BC-UBND'],
            ['Dự thảo Nghị quyết', $docTypes['tai-lieu-chinh'], $agendas[3] ?? null, '02/2026/NQ-HDND'],
            ['Phụ lục số liệu', $docTypes['phu-luc'], $agendas[1] ?? null, '02/2026/PL-UBND'],
        ];
        if ($includeKetLuan) {
            $configs[] = ['Kết luận cuộc họp', $docTypes['ket-luan'], $agendas[4] ?? null, '03/2026/KL-HDND'];
        }

        foreach ($configs as $sortOrder => [$title, $docType, $agenda, $docNumber]) {
            $doc = MeetingDocument::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'meeting_agenda_id' => $agenda?->id,
                'meeting_document_type_id' => $docType->id,
                'title' => $title,
                'document_number' => $docNumber,
                'summary' => "Tóm tắt: {$title}",
                'is_public' => true,
                'download_count' => random_int(5, 50),
                'sort_order' => $sortOrder + 1,
                'created_by' => $this->demoUsers['thuky']->id,
                'updated_by' => $this->demoUsers['thuky']->id,
            ]);

            try {
                if (! $this->demoDocxPath || ! is_file($this->demoDocxPath)) {
                    throw new \RuntimeException('File demo .docx không tồn tại.');
                }
                $safeName = Str::slug($title, '-').'.docx';
                // Clone temp file vì MediaService có thể move file gốc.
                $cloned = tempnam(sys_get_temp_dir(), 'demo_doc_').'.docx';
                copy($this->demoDocxPath, $cloned);
                $file = new UploadedFile(
                    $cloned,
                    $safeName,
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true
                );
                $media = $this->mediaService->uploadOne($doc, $file, 'meeting-document-attachments', ['disk' => 'public']);
                $doc->update(['media_id' => $media->id]);
            } catch (\Throwable $e) {
                $this->command?->warn("Không attach được file cho doc '{$title}': ".$e->getMessage());
            }
        }
    }

    /**
     * Sinh 1 file .docx mẫu (1 lần / seed run) — share content cho mọi document.
     * Caller dùng `copy()` trước mỗi attach để tránh MediaService move file gốc.
     */
    private function generateSharedDemoDocx(): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(13);

        $section = $phpWord->addSection();
        $section->addText('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', ['bold' => true, 'size' => 13], ['alignment' => 'center']);
        $section->addText('Độc lập - Tự do - Hạnh phúc', ['bold' => true, 'italic' => true], ['alignment' => 'center']);
        $section->addTextBreak(1);

        $section->addText('TÀI LIỆU CUỘC HỌP (DEMO)', ['bold' => true, 'size' => 16], ['alignment' => 'center']);
        $section->addText('(File mẫu sinh tự động bởi seeder)', ['italic' => true, 'size' => 12], ['alignment' => 'center']);
        $section->addTextBreak(2);

        $section->addText('I. NỘI DUNG', ['bold' => true, 'size' => 14]);
        $section->addText('Đây là tài liệu mẫu phục vụ demo sản phẩm. Nội dung minh hoạ cấu trúc văn bản hành chính cơ bản.');
        $section->addTextBreak(1);

        $section->addText('II. CĂN CỨ', ['bold' => true, 'size' => 14]);
        $section->addListItem('Luật Tổ chức chính quyền địa phương năm 2015 (sửa đổi 2019).');
        $section->addListItem('Nghị quyết HĐND về chương trình công tác năm 2026.');
        $section->addListItem('Báo cáo của các phòng ban chuyên môn.');
        $section->addTextBreak(2);

        $section->addText('TM. UỶ BAN NHÂN DÂN', ['bold' => true], ['alignment' => 'right']);
        $section->addText('CHỦ TỊCH', ['bold' => true], ['alignment' => 'right']);
        $section->addText('(Đã ký)', ['italic' => true], ['alignment' => 'right']);

        $tmpPath = tempnam(sys_get_temp_dir(), 'demo_shared_').'.docx';
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);

        return $tmpPath;
    }

    /** @param array<int, MeetingParticipant> $participants */
    private function createAttendances(Meeting $meeting, array $participants, bool $fullCheckIn): void
    {
        $total = count($participants);
        $checkInCount = $fullCheckIn ? $total : (int) ceil($total * 0.6);

        foreach ($participants as $i => $p) {
            $isCheckedIn = $i < $checkInCount;
            MeetingAttendance::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'meeting_participant_id' => $p->id,
                'status' => $isCheckedIn ? 'present' : 'absent',
                'checkin_method' => $isCheckedIn ? (random_int(0, 1) ? 'qr' : 'manual') : null,
                'checked_in_at' => $isCheckedIn ? $meeting->start_time->copy()->addMinutes(random_int(-10, 15)) : null,
                'checked_in_by' => $isCheckedIn ? $this->demoUsers['thuky']->id : null,
                'note' => $isCheckedIn ? null : 'Vắng có phép',
            ]);
        }
    }

    /** @param array<int, MeetingAgenda> $agendas @param array<int, MeetingParticipant> $participants */
    private function createVoteTopics(Meeting $meeting, array $agendas, array $participants, bool $allClosed): void
    {
        $voteAgenda = $agendas[3] ?? $agendas[0];
        $topics = [
            ['Thông qua chương trình họp', 'agree_disagree_abstain', 'open'],
            ['Phê duyệt nghị quyết', 'agree_disagree_abstain', 'anonymous'],
        ];

        foreach ($topics as $idx => [$title, $voteType, $ballotMode]) {
            $isClosed = $allClosed || $idx === 0; // in-progress: topic[0] đã đóng, topic[1] đang mở
            $isOpen = ! $isClosed;

            $topic = MeetingVoteTopic::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'meeting_agenda_id' => $voteAgenda->id,
                'title' => $title,
                'description' => "Biểu quyết: {$title}",
                'duration_minutes' => 5,
                'vote_type' => $voteType,
                'ballot_mode' => $ballotMode,
                'show_result_on_projector' => true,
                'show_result_on_personal_device' => true,
                'sort_order' => $idx + 1,
                'opened_at' => $meeting->start_time->copy()->addMinutes(180 + $idx * 10),
                'closed_at' => $isClosed ? $meeting->start_time->copy()->addMinutes(185 + $idx * 10) : null,
                'created_by' => $this->demoUsers['thuky']->id,
                'updated_by' => $this->demoUsers['thuky']->id,
            ]);

            // Vote responses chỉ cho topic đã đóng hoặc đang mở (skip pure-draft topics)
            if (! $isClosed && ! $isOpen) {
                continue;
            }

            $options = ['agree', 'disagree', 'abstain'];
            $voteCount = $isClosed ? count($participants) : (int) ceil(count($participants) * 0.5);

            foreach (array_slice($participants, 0, $voteCount) as $p) {
                $pickedOption = $options[array_rand($options)];
                // Đa số đồng ý → bias 70% agree
                if (random_int(1, 10) <= 7) {
                    $pickedOption = 'agree';
                }
                MeetingVoteResponse::create([
                    'organization_id' => $this->orgId,
                    'meeting_vote_topic_id' => $topic->id,
                    'user_id' => $p->meeting_attendee_id ? MeetingAttendee::find($p->meeting_attendee_id)?->user_id : null,
                    'meeting_participant_id' => $p->id,
                    'option' => $pickedOption,
                    'voted_at' => $topic->opened_at->copy()->addSeconds(random_int(30, 240)),
                ]);
            }
        }
    }

    /** @param array<int, MeetingAgenda> $agendas @param array<int, MeetingParticipant> $participants */
    private function createDiscussionRegistrations(Meeting $meeting, array $agendas, array $participants, bool $allCompleted): void
    {
        $discussAgenda = $agendas[2] ?? $agendas[0];
        $registrations = [
            ['discussion', 'Đề nghị làm rõ tình hình thu chi quý 1.', $participants[2] ?? null],
            ['question', 'Tại sao tiến độ dự án X chậm hơn kế hoạch?', $participants[3] ?? null],
            ['discussion', 'Góp ý về quy hoạch khu công viên mới.', $participants[4] ?? null],
        ];

        foreach ($registrations as $idx => [$type, $content, $participant]) {
            if (! $participant) {
                continue;
            }
            $isCompleted = $allCompleted || $idx === 0;
            MeetingDiscussionRegistration::create([
                'organization_id' => $this->orgId,
                'meeting_id' => $meeting->id,
                'meeting_agenda_id' => $discussAgenda->id,
                'meeting_participant_id' => $participant->id,
                'type' => $type,
                'content' => $content,
                'operator_note' => $isCompleted ? 'Đã được chủ trì giải đáp.' : null,
                'status' => $isCompleted ? 'completed' : 'registered',
                'completed_at' => $isCompleted ? $meeting->start_time->copy()->addHours(2)->addMinutes($idx * 5) : null,
                'sort_order' => $idx + 1,
            ]);
        }
    }
}
