<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAgenda;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use App\Modules\Meeting\Models\MeetingDocument;
use App\Modules\Meeting\Models\MeetingDocumentType;
use App\Modules\Meeting\Models\MeetingLocation;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingType;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use App\Modules\Meeting\Models\MeetingVoteTopic;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed dữ liệu mẫu cho module Meeting.
 *
 * Catalog (4 + 4 + 4 + 3 + ~10):
 *  - meeting_types, meeting_locations, meeting_document_types, meeting_attendee_groups, meeting_attendees
 *
 * Sample meetings (3):
 *  - HĐND thường kỳ tháng 5 (published, public) — đầy đủ chương trình, tài liệu, biểu quyết, kết luận
 *  - Họp giao ban tuần (published, internal) — chương trình + tài liệu nội bộ
 *  - Họp chuyên đề (draft) — đang chuẩn bị
 */
class MeetingDataSeeder extends Seeder
{
    private int $orgId = 1;

    /** @var array<int> User IDs khả dụng để gán creator/attendee. */
    private array $userIds = [];

    public function run(): void
    {
        $admin = User::firstWhere('email', 'admin@example.com') ?? User::first();
        if (! $admin) {
            $this->command?->warn('MeetingDataSeeder: chưa có user nào — bỏ qua seed.');

            return;
        }

        auth()->login($admin);
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($this->orgId);
        }

        $this->userIds = User::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(8)
            ->pluck('id')
            ->all();

        $types = $this->seedMeetingTypes();
        $locations = $this->seedMeetingLocations();
        $docTypes = $this->seedDocumentTypes();
        $groups = $this->seedAttendeeGroups();
        $attendees = $this->seedAttendees($groups);

        $this->seedMeetingHdndThuongKy($types, $locations, $docTypes, $attendees);
        $this->seedMeetingGiaoBan($types, $locations, $docTypes, $attendees);
        $this->seedMeetingChuyenDeDraft($types, $locations, $attendees);

        // 4 meeting bổ sung — đa dạng status + thời gian.
        $this->seedAdditionalMeetings($types, $locations, $docTypes, $attendees);

        auth()->logout();
    }

    /**
     * Seed thêm 4 cuộc họp đa dạng (past finished, upcoming, in-progress now, draft sắp tới).
     *
     * @param  array<string, MeetingType>  $types
     * @param  array<string, MeetingLocation>  $locations
     * @param  array<string, MeetingDocumentType>  $docTypes
     * @param  array<int, MeetingAttendee>  $attendees
     */
    private function seedAdditionalMeetings(array $types, array $locations, array $docTypes, array $attendees): void
    {
        // Helper rút gọn: tạo meeting + auto chair=attendees[0], op=attendees[1], participants=attendees[2..N].
        $createMeeting = function (array $payload, int $participantCount = 8) use ($attendees): Meeting {
            $meeting = Meeting::updateOrCreate(
                ['title' => $payload['title'], 'organization_id' => $this->orgId],
                array_merge([
                    'chairperson_meeting_attendee_id' => $attendees[0]->id ?? null,
                    'operator_meeting_attendee_id' => $attendees[1]->id ?? null,
                ], $payload)
            );
            $this->cleanupChairOpParticipants($meeting, $attendees);

            // Add participants — slice attendees từ index 2.
            $participants = array_slice($attendees, 2, $participantCount);
            foreach ($participants as $idx => $attendee) {
                $attendee->loadMissing('user.profile');
                $responseStatus = $idx <= ($participantCount - 3) ? 'accepted' : 'pending';
                MeetingParticipant::firstOrCreate(
                    ['meeting_id' => $meeting->id, 'meeting_attendee_id' => $attendee->id],
                    [
                        'organization_id' => $this->orgId,
                        'display_name' => $attendee->user?->name,
                        'position_name' => $attendee->position_name,
                        'department_name' => $attendee->department_name,
                        'email' => $attendee->user?->email,
                        'phone' => $attendee->user?->profile?->phone,
                        'response_status' => $responseStatus,
                        'responded_at' => $responseStatus === 'accepted' ? Carbon::parse($payload['start_time'])->subDays(2) : null,
                    ]
                );
            }

            return $meeting;
        };

        // 1. Cuộc họp đã kết thúc (1 tháng trước) — có nhiều tài liệu kết luận.
        $start1 = Carbon::parse('2026-04-10 08:30:00');
        $createMeeting([
            'title' => 'HĐND chuyên đề về quy hoạch đô thị 2026-2030',
            'meeting_type_id' => $types['HĐND chuyên đề']->id,
            'meeting_location_id' => $locations['Hội trường lớn UBND TP Đà Nẵng']->id,
            'is_public' => true,
            'content' => 'Đánh giá tổng thể quy hoạch đô thị thành phố giai đoạn 2026-2030, cập nhật điều chỉnh phù hợp tình hình mới.',
            'start_time' => $start1,
            'end_time' => $start1->copy()->addHours(7),
            'status' => 'published',
            'view_count' => 234,
            'published_at' => $start1->copy()->subDays(10),
        ], 14);

        // 2. Cuộc họp sắp tới (2 tuần sau) — public, đã ban hành.
        $start2 = Carbon::parse('2026-05-22 14:00:00');
        $createMeeting([
            'title' => 'Họp UBND triển khai kế hoạch chuyển đổi số quý III',
            'meeting_type_id' => $types['Họp chuyên đề']->id,
            'meeting_location_id' => $locations['Phòng họp tầng 5 - Sở Nội vụ']->id,
            'is_public' => false,
            'content' => 'Triển khai kế hoạch chuyển đổi số quý III/2026, phân công nhiệm vụ cụ thể cho các sở, ngành.',
            'start_time' => $start2,
            'end_time' => $start2->copy()->addHours(3),
            'status' => 'published',
            'view_count' => 28,
            'published_at' => Carbon::parse('2026-05-08 09:00:00'),
        ], 10);

        // 3. Cuộc họp đột xuất tuần này (start_time = ngày mai) — public.
        $start3 = Carbon::parse('2026-05-09 09:00:00');
        $createMeeting([
            'title' => 'Họp đột xuất ứng phó bão số 5 hướng vào miền Trung',
            'meeting_type_id' => $types['Họp đột xuất']->id,
            'meeting_location_id' => $locations['Hội trường lớn UBND TP Đà Nẵng']->id,
            'is_public' => true,
            'content' => 'Triển khai phương án ứng phó bão số 5, đảm bảo an toàn dân cư và sản xuất.',
            'start_time' => $start3,
            'end_time' => $start3->copy()->addHours(2),
            'status' => 'published',
            'view_count' => 412,
            'published_at' => Carbon::parse('2026-05-08 16:00:00'),
        ], 12);

        // 4. Cuộc họp giao ban tháng (3 tuần sau, vẫn draft).
        $start4 = Carbon::parse('2026-05-30 08:00:00');
        $createMeeting([
            'title' => 'Họp giao ban tháng 5/2026 - Sở Kế hoạch & Đầu tư',
            'meeting_type_id' => $types['Họp giao ban']->id,
            'meeting_location_id' => $locations['Phòng họp tầng 5 - Sở Nội vụ']->id,
            'is_public' => false,
            'content' => 'Đánh giá tiến độ công việc tháng 5, triển khai nhiệm vụ tháng 6/2026.',
            'start_time' => $start4,
            'end_time' => $start4->copy()->addHours(2),
            'status' => 'draft',
            'view_count' => 0,
        ], 6);
    }

    /** @return array<string, MeetingType> */
    private function seedMeetingTypes(): array
    {
        $rows = [
            ['name' => 'HĐND thường kỳ', 'description' => 'Kỳ họp HĐND tổ chức định kỳ theo quy định.'],
            ['name' => 'HĐND chuyên đề', 'description' => 'Kỳ họp HĐND chuyên đề về một lĩnh vực cụ thể.'],
            ['name' => 'Họp giao ban', 'description' => 'Họp nội bộ giao ban tuần/tháng.'],
            ['name' => 'Họp chuyên đề', 'description' => 'Họp chuyên đề theo lĩnh vực, dự án.'],
            ['name' => 'Họp đột xuất', 'description' => 'Họp đột xuất theo yêu cầu lãnh đạo.'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = MeetingType::firstOrCreate(
                ['name' => $row['name'], 'organization_id' => $this->orgId],
                ['description' => $row['description'], 'status' => 'active']
            );
        }

        return $out;
    }

    /** @return array<string, MeetingLocation> */
    private function seedMeetingLocations(): array
    {
        $rows = [
            [
                'name' => 'Hội trường lớn UBND TP Đà Nẵng',
                'address' => '24 Trần Phú, Hải Châu, Đà Nẵng',
                'google_maps_url' => 'https://maps.google.com/?q=16.0739,108.2240',
                'description' => 'Hội trường chính tầng 2.',
            ],
            [
                'name' => 'Phòng họp tầng 5 - Sở Nội vụ',
                'address' => 'Số 1 Trần Phú, Đà Nẵng',
                'description' => 'Phòng họp dùng chung tầng 5.',
            ],
            [
                'name' => 'Phòng họp HĐND TP',
                'address' => '24 Trần Phú, Hải Châu, Đà Nẵng',
                'description' => 'Phòng họp riêng HĐND.',
            ],
            [
                'name' => 'Họp trực tuyến (Zoom)',
                'address' => null,
                'description' => 'Phòng họp ảo qua Zoom.',
            ],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = MeetingLocation::firstOrCreate(
                ['name' => $row['name'], 'organization_id' => $this->orgId],
                array_merge($row, ['status' => 'active'])
            );
        }

        return $out;
    }

    /** @return array<string, MeetingDocumentType> */
    private function seedDocumentTypes(): array
    {
        $rows = [
            ['name' => 'Tờ trình', 'description' => 'Tờ trình HĐND, UBND.'],
            ['name' => 'Báo cáo', 'description' => 'Báo cáo công tác.'],
            ['name' => 'Dự thảo nghị quyết', 'description' => 'Dự thảo nghị quyết chờ biểu quyết.'],
            ['name' => 'Tài liệu tham khảo', 'description' => 'Tài liệu tham khảo bổ trợ.'],
            ['name' => 'Tài liệu kết luận cuộc họp', 'description' => 'Văn bản kết luận thư ký upload sau họp.'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = MeetingDocumentType::firstOrCreate(
                ['name' => $row['name'], 'organization_id' => $this->orgId],
                ['description' => $row['description'], 'status' => 'active']
            );
        }

        return $out;
    }

    /** @return array<string, MeetingAttendeeGroup> */
    private function seedAttendeeGroups(): array
    {
        $rows = [
            ['name' => 'Thường trực HĐND', 'description' => 'Thường trực HĐND TP.'],
            ['name' => 'Đại biểu HĐND khóa X', 'description' => 'Đại biểu HĐND khóa X (2021-2026).'],
            ['name' => 'Lãnh đạo Sở/Ban/Ngành', 'description' => 'Lãnh đạo các sở, ban, ngành TP.'],
            ['name' => 'Lãnh đạo UBND quận', 'description' => 'Lãnh đạo UBND các quận, huyện.'],
            ['name' => 'Khách mời', 'description' => 'Khách mời ngoài thành phần đại biểu.'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $out[$row['name']] = MeetingAttendeeGroup::firstOrCreate(
                ['name' => $row['name'], 'organization_id' => $this->orgId],
                ['description' => $row['description'], 'status' => 'active']
            );
        }

        return $out;
    }

    /**
     * @param  array<string, MeetingAttendeeGroup>  $groups
     * @return array<int, MeetingAttendee>
     */
    /**
     * Xoá participants trùng với chair/op của meeting — đảm bảo quy ước
     * "1 user 1 vai trò trong meeting" sau khi re-seed (dữ liệu cũ có thể đã add chair/op vào participants).
     *
     * @param  array<int, MeetingAttendee>  $attendees
     */
    private function cleanupChairOpParticipants(Meeting $meeting, array $attendees): void
    {
        $chairAttendeeId = $attendees[0]->id ?? null;
        $opAttendeeId = $attendees[1]->id ?? null;
        $idsToRemove = array_filter([$chairAttendeeId, $opAttendeeId]);
        if (empty($idsToRemove)) {
            return;
        }

        MeetingParticipant::query()
            ->where('meeting_id', $meeting->id)
            ->whereIn('meeting_attendee_id', $idsToRemove)
            ->delete();
    }

    private function seedAttendees(array $groups): array
    {
        // Mỗi đại biểu link với 1 user nội bộ (1-1 unique trong org).
        // Sau refactor 2026-05-10: tất cả attendee dùng chung role "Đại biểu". FE check render
        // chair/operator dựa vào FK chairperson_meeting_attendee_id / operator_meeting_attendee_id.
        // Quy ước "1 user 1 vai trò xuyên suốt": chủ trì/thư ký không kiêm participant ở bất kỳ meeting nào (xem skip ở seedMeeting*).
        $rows = [
            ['user_email' => 'nvhung@snvdn.gov.vn', 'position_name' => 'Chủ tịch HĐND', 'department_name' => 'HĐND TP', 'group' => 'Thường trực HĐND', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'ttmai@snvdn.gov.vn', 'position_name' => 'Phó Chủ tịch HĐND', 'department_name' => 'HĐND TP', 'group' => 'Thường trực HĐND', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'lhnam@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Kế hoạch & Đầu tư', 'group' => 'Đại biểu HĐND khóa X', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'pthong@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Tài chính', 'group' => 'Đại biểu HĐND khóa X', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'vdthang@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'UBND quận Hải Châu', 'group' => 'Đại biểu HĐND khóa X', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'htlan@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Y tế', 'group' => 'Đại biểu HĐND khóa X', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'dmtuan@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Giáo dục', 'group' => 'Đại biểu HĐND khóa X', 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'btngoc@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở LĐ-TB-XH', 'group' => 'Đại biểu HĐND khóa X', 'spatie_role' => 'Đại biểu'],
            // 12 attendee bổ sung — đa dạng group, role.
            ['user_email' => 'hvphuc@snvdn.gov.vn',  'position_name' => 'Đại biểu HĐND',          'department_name' => 'Sở Nội vụ',            'group' => 'Đại biểu HĐND khóa X',     'spatie_role' => 'Đại biểu'],
            ['user_email' => 'ntthanh@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND',          'department_name' => 'Sở Tư pháp',           'group' => 'Đại biểu HĐND khóa X',     'spatie_role' => 'Đại biểu'],
            ['user_email' => 'tvkhai@snvdn.gov.vn',  'position_name' => 'Giám đốc',                'department_name' => 'Sở Kế hoạch & Đầu tư', 'group' => 'Lãnh đạo Sở/Ban/Ngành',    'spatie_role' => 'Đại biểu'],
            ['user_email' => 'ltbich@snvdn.gov.vn',  'position_name' => 'Giám đốc',                'department_name' => 'Sở Tài chính',         'group' => 'Lãnh đạo Sở/Ban/Ngành',    'spatie_role' => 'Đại biểu'],
            ['user_email' => 'dqminh@snvdn.gov.vn',  'position_name' => 'Giám đốc',                'department_name' => 'Sở Y tế',              'group' => 'Lãnh đạo Sở/Ban/Ngành',    'spatie_role' => 'Đại biểu'],
            ['user_email' => 'vthha@snvdn.gov.vn',   'position_name' => 'Giám đốc',                'department_name' => 'Sở Giáo dục & Đào tạo', 'group' => 'Lãnh đạo Sở/Ban/Ngành',    'spatie_role' => 'Đại biểu'],
            ['user_email' => 'pdlong@snvdn.gov.vn',  'position_name' => 'Chủ tịch UBND',          'department_name' => 'UBND quận Hải Châu',   'group' => 'Lãnh đạo UBND quận',        'spatie_role' => 'Đại biểu'],
            ['user_email' => 'tmlinh@snvdn.gov.vn',  'position_name' => 'Chủ tịch UBND',          'department_name' => 'UBND quận Thanh Khê',  'group' => 'Lãnh đạo UBND quận',        'spatie_role' => 'Đại biểu'],
            ['user_email' => 'cvson@snvdn.gov.vn',   'position_name' => 'Chủ tịch UBND',          'department_name' => 'UBND quận Sơn Trà',    'group' => 'Lãnh đạo UBND quận',        'spatie_role' => 'Đại biểu'],
            ['user_email' => 'lthuong@snvdn.gov.vn', 'position_name' => 'Chủ tịch UBND',          'department_name' => 'UBND quận Ngũ Hành Sơn','group' => 'Lãnh đạo UBND quận',       'spatie_role' => 'Đại biểu'],
            ['user_email' => 'dbkhoi@snvdn.gov.vn',  'position_name' => 'Chủ tịch UBND',          'department_name' => 'UBND quận Liên Chiểu', 'group' => 'Lãnh đạo UBND quận',        'spatie_role' => 'Đại biểu'],
            ['user_email' => 'thyen@snvdn.gov.vn',   'position_name' => 'Phóng viên',              'department_name' => 'Báo Đà Nẵng',          'group' => 'Khách mời',                 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'mqhung@snvdn.gov.vn',  'position_name' => 'Đại biểu Mặt trận',     'department_name' => 'UBMTTQVN TP',          'group' => 'Khách mời',                 'spatie_role' => 'Đại biểu'],
            ['user_email' => 'htduong@snvdn.gov.vn', 'position_name' => 'Phó Chánh Văn phòng',  'department_name' => 'Văn phòng UBND TP',     'group' => 'Khách mời',                 'spatie_role' => 'Đại biểu'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $user = User::where('email', $row['user_email'])->first();
            if (! $user) {
                continue;
            }
            $attendee = MeetingAttendee::firstOrCreate(
                ['organization_id' => $this->orgId, 'user_id' => $user->id],
                [
                    'position_name' => $row['position_name'],
                    'department_name' => $row['department_name'],
                    'status' => 'active',
                ]
            );

            // Sync group qua pivot M-N (refactor 2026-05-08).
            $groupId = $groups[$row['group']]->id;
            $attendee->groups()->syncWithoutDetaching([
                $groupId => ['organization_id' => $this->orgId],
            ]);

            // Sync Spatie role — guard 'web' theo MeetingPermissionSeeder.
            $user->syncRoles([$row['spatie_role']]);
            $out[] = $attendee;
        }

        return $out;
    }

    /**
     * Cuộc họp 1: HĐND thường kỳ tháng 5 — published, public, đầy đủ data.
     *
     * @param  array<string, MeetingType>  $types
     * @param  array<string, MeetingLocation>  $locations
     * @param  array<string, MeetingDocumentType>  $docTypes
     * @param  array<int, MeetingAttendee>  $attendees
     */
    private function seedMeetingHdndThuongKy(array $types, array $locations, array $docTypes, array $attendees): void
    {
        $start = Carbon::parse('2026-05-15 08:00:00');

        // attendees[0] = Nguyễn Văn Hùng → chủ trì
        // attendees[1] = Trần Thị Mai → thư ký
        $meeting = Meeting::updateOrCreate(
            ['title' => 'Kỳ họp HĐND thường kỳ tháng 5/2026', 'organization_id' => $this->orgId],
            [
                'meeting_type_id' => $types['HĐND thường kỳ']->id,
                'meeting_location_id' => $locations['Hội trường lớn UBND TP Đà Nẵng']->id,
                'chairperson_meeting_attendee_id' => $attendees[0]->id ?? null,
                'operator_meeting_attendee_id' => $attendees[1]->id ?? null,
                'is_public' => true,
                'content' => 'Xem xét tình hình KT-XH 4 tháng đầu năm; thông qua nghị quyết phân bổ ngân sách bổ sung; chất vấn lãnh đạo các sở.',
                'start_time' => $start,
                'end_time' => $start->copy()->addHours(8),
                'status' => 'published',
                'view_count' => 145,
                'published_at' => $start->copy()->subDays(7),
            ]
        );

        // Clean up: xoá participants trùng chair/op (data từ seed cũ).
        $this->cleanupChairOpParticipants($meeting, $attendees);

        // 4 chương trình họp
        $agendaRows = [
            ['start' => '08:00', 'end' => '08:30', 'content' => 'Khai mạc kỳ họp, thông qua chương trình, báo cáo thẩm tra.', 'person' => 'Chủ tịch HĐND', 'allow_disc' => false, 'allow_q' => false],
            ['start' => '08:30', 'end' => '10:00', 'content' => 'Báo cáo tình hình KT-XH 4 tháng đầu năm 2026.', 'person' => 'Chủ tịch UBND', 'allow_disc' => true, 'allow_q' => true],
            ['start' => '10:15', 'end' => '11:30', 'content' => 'Thảo luận, chất vấn lãnh đạo các sở.', 'person' => 'Thường trực HĐND', 'allow_disc' => true, 'allow_q' => true],
            ['start' => '14:00', 'end' => '15:30', 'content' => 'Biểu quyết thông qua nghị quyết phân bổ ngân sách bổ sung; bế mạc kỳ họp.', 'person' => 'Thường trực HĐND', 'allow_disc' => false, 'allow_q' => false],
        ];
        $agendas = [];
        foreach ($agendaRows as $i => $row) {
            $agendas[] = MeetingAgenda::firstOrCreate(
                ['meeting_id' => $meeting->id, 'sort_order' => $i + 1],
                [
                    'organization_id' => $this->orgId,
                    'start_time' => $row['start'],
                    'end_time' => $row['end'],
                    'content' => $row['content'],
                    'person_in_charge' => $row['person'],
                    'allow_discussion_registration' => $row['allow_disc'],
                    'allow_question_registration' => $row['allow_q'],
                ]
            );
        }

        // 3 tài liệu
        $docRows = [
            ['title' => 'Tờ trình về phân bổ ngân sách bổ sung 2026', 'doc_no' => '01/TTr-UBND', 'type' => 'Tờ trình', 'is_public' => true, 'agenda_idx' => 1],
            ['title' => 'Báo cáo KT-XH 4 tháng đầu năm 2026', 'doc_no' => '02/BC-UBND', 'type' => 'Báo cáo', 'is_public' => true, 'agenda_idx' => 1],
            ['title' => 'Dự thảo Nghị quyết phân bổ ngân sách bổ sung 2026', 'doc_no' => '03/DT-NQ', 'type' => 'Dự thảo nghị quyết', 'is_public' => false, 'agenda_idx' => 3],
        ];
        foreach ($docRows as $i => $row) {
            MeetingDocument::firstOrCreate(
                ['title' => $row['title'], 'meeting_id' => $meeting->id],
                [
                    'organization_id' => $this->orgId,
                    'meeting_agenda_id' => $agendas[$row['agenda_idx']]->id,
                    'meeting_document_type_id' => $docTypes[$row['type']]->id,
                    'document_number' => $row['doc_no'],
                    'summary' => "Tóm tắt cho {$row['title']}.",
                    'is_public' => $row['is_public'],
                    'sort_order' => $i + 1,
                ]
            );
        }

        // Participants — chỉ attendees[2..7]. attendees[0] (chair) + [1] (operator) đã set qua
        // FK trên meeting, KHÔNG kiêm participant theo quy ước "1 user 1 vai trò".
        $participants = [];
        foreach (array_slice($attendees, 2) as $idx => $attendee) {
            $attendee->loadMissing('user.profile');
            // Index trong subset (0..5) — 4 đầu accepted, 2 cuối pending để có data đa dạng.
            $responseStatus = $idx <= 3 ? 'accepted' : 'pending';

            $participants[] = MeetingParticipant::firstOrCreate(
                ['meeting_id' => $meeting->id, 'meeting_attendee_id' => $attendee->id],
                [
                    'organization_id' => $this->orgId,
                    'display_name' => $attendee->user?->name,
                    'position_name' => $attendee->position_name,
                    'department_name' => $attendee->department_name,
                    'email' => $attendee->user?->email,
                    'phone' => $attendee->user?->profile?->phone,
                    'response_status' => $responseStatus,
                    'responded_at' => $responseStatus === 'accepted' ? $start->copy()->subDays(3) : null,
                ]
            );
        }

        // 2 chương trình biểu quyết
        $voteTopics = [
            ['title' => 'Biểu quyết thông qua dự thảo Nghị quyết phân bổ ngân sách bổ sung 2026', 'vote_type' => 'agree_disagree_abstain', 'ballot_mode' => 'public_named', 'agenda_idx' => 3],
            ['title' => 'Biểu quyết bổ nhiệm Trưởng ban Kinh tế - Ngân sách', 'vote_type' => 'approve_reject_abstain', 'ballot_mode' => 'anonymous', 'agenda_idx' => 3],
        ];
        foreach ($voteTopics as $i => $vt) {
            $topic = MeetingVoteTopic::firstOrCreate(
                ['title' => $vt['title'], 'meeting_id' => $meeting->id],
                [
                    'organization_id' => $this->orgId,
                    'meeting_agenda_id' => $agendas[$vt['agenda_idx']]->id,
                    'vote_type' => $vt['vote_type'],
                    'ballot_mode' => $vt['ballot_mode'],
                    'show_result_on_projector' => true,
                    'show_result_on_personal_device' => true,
                    'sort_order' => $i + 1,
                    // Phase derive từ opened_at + closed_at — set cả 2 để đại diện vote đã đóng.
                    'opened_at' => $start->copy()->addHours(6),
                    'closed_at' => $start->copy()->addHours(6)->addMinutes(15),
                ]
            );

            // Tạo phiếu cho từng participant — phân bổ agree/disagree/abstain
            foreach ($participants as $pIdx => $participant) {
                $option = match ($pIdx % 5) {
                    0, 1, 2 => $vt['vote_type'] === 'agree_disagree_abstain' ? 'agree' : 'approve',
                    3 => $vt['vote_type'] === 'agree_disagree_abstain' ? 'disagree' : 'reject',
                    4 => 'abstain',
                };
                MeetingVoteResponse::firstOrCreate(
                    ['meeting_vote_topic_id' => $topic->id, 'meeting_participant_id' => $participant->id],
                    [
                        'organization_id' => $this->orgId,
                        'option' => $option,
                        'voted_at' => $start->copy()->addHours(6)->addMinutes(rand(1, 14)),
                    ]
                );
            }
        }

        // Kết luận: thư ký upload qua meeting_documents với loại "Tài liệu kết luận cuộc họp" (post-meeting upload).
        $conclusionDocType = $docTypes['Tài liệu kết luận cuộc họp'] ?? null;
        if ($conclusionDocType) {
            MeetingDocument::firstOrCreate(
                ['title' => 'Kết luận kỳ họp HĐND thường kỳ tháng 5/2026', 'meeting_id' => $meeting->id],
                [
                    'organization_id' => $this->orgId,
                    'meeting_agenda_id' => $agendas[3]->id ?? null,
                    'meeting_document_type_id' => $conclusionDocType->id,
                    'document_number' => '04/KL-UBND',
                    'summary' => 'Kỳ họp đã thông qua nghị quyết phân bổ ngân sách bổ sung 2026 với tỷ lệ tán thành cao. Yêu cầu UBND TP triển khai theo kế hoạch.',
                    'is_public' => true,
                    'sort_order' => 4,
                ]
            );
        }
    }

    /**
     * Cuộc họp 2: Họp giao ban tuần — published, internal.
     *
     * @param  array<string, MeetingType>  $types
     * @param  array<string, MeetingLocation>  $locations
     * @param  array<string, MeetingDocumentType>  $docTypes
     * @param  array<int, MeetingAttendee>  $attendees
     */
    private function seedMeetingGiaoBan(array $types, array $locations, array $docTypes, array $attendees): void
    {
        $start = Carbon::parse('2026-05-04 14:00:00');

        $meeting = Meeting::updateOrCreate(
            ['title' => 'Họp giao ban tuần 19/2026', 'organization_id' => $this->orgId],
            [
                'meeting_type_id' => $types['Họp giao ban']->id,
                'meeting_location_id' => $locations['Phòng họp tầng 5 - Sở Nội vụ']->id,
                'chairperson_meeting_attendee_id' => $attendees[0]->id ?? null,
                'operator_meeting_attendee_id' => $attendees[1]->id ?? null,
                'is_public' => false,
                'content' => 'Tổng kết công việc tuần trước, triển khai nhiệm vụ tuần này.',
                'start_time' => $start,
                'end_time' => $start->copy()->addHours(2),
                'status' => 'published',
                'view_count' => 12,
                'published_at' => $start->copy()->subDay(),
            ]
        );

        $this->cleanupChairOpParticipants($meeting, $attendees);

        $agendas = [];
        $agendaRows = [
            ['content' => 'Tổng kết công việc tuần 18.', 'start' => '14:00', 'end' => '14:45'],
            ['content' => 'Triển khai nhiệm vụ tuần 19.', 'start' => '14:45', 'end' => '15:30'],
            ['content' => 'Trao đổi vướng mắc, đề xuất.', 'start' => '15:30', 'end' => '16:00'],
        ];
        foreach ($agendaRows as $i => $row) {
            $agendas[] = MeetingAgenda::firstOrCreate(
                ['meeting_id' => $meeting->id, 'sort_order' => $i + 1],
                [
                    'organization_id' => $this->orgId,
                    'start_time' => $row['start'],
                    'end_time' => $row['end'],
                    'content' => $row['content'],
                    'person_in_charge' => 'Trưởng phòng',
                    'allow_discussion_registration' => true,
                    'allow_question_registration' => false,
                ]
            );
        }

        MeetingDocument::firstOrCreate(
            ['title' => 'Báo cáo tổng kết tuần 18/2026', 'meeting_id' => $meeting->id],
            [
                'organization_id' => $this->orgId,
                'meeting_agenda_id' => $agendas[0]->id,
                'meeting_document_type_id' => $docTypes['Báo cáo']->id,
                'document_number' => 'BC-T18',
                'summary' => 'Báo cáo tổng kết tuần 18.',
                'is_public' => false,
                'sort_order' => 1,
            ]
        );

        // Participants — chỉ vài đại biểu nội bộ
        foreach (array_slice($attendees, 2, 5) as $attendee) {
            $attendee->loadMissing('user.profile');
            MeetingParticipant::firstOrCreate(
                ['meeting_id' => $meeting->id, 'meeting_attendee_id' => $attendee->id],
                [
                    'organization_id' => $this->orgId,
                    'display_name' => $attendee->user?->name,
                    'position_name' => $attendee->position_name,
                    'department_name' => $attendee->department_name,
                    'email' => $attendee->user?->email,
                    'phone' => $attendee->user?->profile?->phone,
                    'response_status' => 'accepted',
                    'responded_at' => $start->copy()->subHours(4),
                ]
            );
        }
    }

    /**
     * Cuộc họp 3: Họp chuyên đề — draft, đang chuẩn bị.
     *
     * @param  array<string, MeetingType>  $types
     * @param  array<string, MeetingLocation>  $locations
     * @param  array<int, MeetingAttendee>  $attendees
     */
    private function seedMeetingChuyenDeDraft(array $types, array $locations, array $attendees): void
    {
        $start = Carbon::parse('2026-05-25 08:30:00');

        $meeting = Meeting::updateOrCreate(
            ['title' => 'Họp chuyên đề chuyển đổi số ngành y tế (DRAFT)', 'organization_id' => $this->orgId],
            [
                'meeting_type_id' => $types['Họp chuyên đề']->id,
                'meeting_location_id' => $locations['Họp trực tuyến (Zoom)']->id,
                'chairperson_meeting_attendee_id' => $attendees[0]->id ?? null,
                'operator_meeting_attendee_id' => $attendees[1]->id ?? null,
                'is_public' => false,
                'content' => 'Đánh giá tiến độ chuyển đổi số ngành y tế thành phố — bản thảo.',
                'start_time' => $start,
                'end_time' => $start->copy()->addHours(3),
                'status' => 'draft',
                'view_count' => 0,
            ]
        );

        $this->cleanupChairOpParticipants($meeting, $attendees);

        MeetingAgenda::firstOrCreate(
            ['meeting_id' => $meeting->id, 'sort_order' => 1],
            [
                'organization_id' => $this->orgId,
                'start_time' => '08:30',
                'end_time' => '11:30',
                'content' => 'Báo cáo tiến độ + thảo luận giải pháp.',
                'person_in_charge' => 'Sở Y tế',
                'allow_discussion_registration' => true,
                'allow_question_registration' => false,
            ]
        );

        // Một vài attendee dự kiến (chưa publish nên chưa có invitations)
        foreach (array_slice($attendees, 5, 3) as $attendee) {
            $attendee->loadMissing('user.profile');
            MeetingParticipant::firstOrCreate(
                ['meeting_id' => $meeting->id, 'meeting_attendee_id' => $attendee->id],
                [
                    'organization_id' => $this->orgId,
                    'display_name' => $attendee->user?->name,
                    'position_name' => $attendee->position_name,
                    'department_name' => $attendee->department_name,
                    'email' => $attendee->user?->email,
                    'phone' => $attendee->user?->profile?->phone,
                    'response_status' => 'pending',
                ]
            );
        }
    }
}
