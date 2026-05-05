<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingAgenda;
use App\Modules\Meeting\Models\MeetingAttendee;
use App\Modules\Meeting\Models\MeetingAttendeeGroup;
use App\Modules\Meeting\Models\MeetingConclusion;
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

        auth()->logout();
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
    private function seedAttendees(array $groups): array
    {
        // Mỗi đại biểu link với 1 user nội bộ (1-1 unique trong org).
        $rows = [
            ['user_email' => 'nvhung@snvdn.gov.vn', 'position_name' => 'Chủ tịch HĐND', 'department_name' => 'HĐND TP', 'group' => 'Thường trực HĐND'],
            ['user_email' => 'ttmai@snvdn.gov.vn', 'position_name' => 'Phó Chủ tịch HĐND', 'department_name' => 'HĐND TP', 'group' => 'Thường trực HĐND'],
            ['user_email' => 'lhnam@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Kế hoạch & Đầu tư', 'group' => 'Đại biểu HĐND khóa X'],
            ['user_email' => 'pthong@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Tài chính', 'group' => 'Đại biểu HĐND khóa X'],
            ['user_email' => 'vdthang@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'UBND quận Hải Châu', 'group' => 'Đại biểu HĐND khóa X'],
            ['user_email' => 'htlan@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Y tế', 'group' => 'Đại biểu HĐND khóa X'],
            ['user_email' => 'dmtuan@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở Giáo dục', 'group' => 'Đại biểu HĐND khóa X'],
            ['user_email' => 'btngoc@snvdn.gov.vn', 'position_name' => 'Đại biểu HĐND', 'department_name' => 'Sở LĐ-TB-XH', 'group' => 'Đại biểu HĐND khóa X'],
        ];

        $out = [];
        foreach ($rows as $row) {
            $userId = User::where('email', $row['user_email'])->value('id');
            if (! $userId) {
                continue;
            }
            $attendee = MeetingAttendee::firstOrCreate(
                ['organization_id' => $this->orgId, 'user_id' => $userId],
                [
                    'meeting_attendee_group_id' => $groups[$row['group']]->id,
                    'position_name' => $row['position_name'],
                    'department_name' => $row['department_name'],
                    'status' => 'active',
                ]
            );
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
        $meeting = Meeting::firstOrCreate(
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
                    'status' => 'published',
                    'view_count' => rand(15, 80),
                    'sort_order' => $i + 1,
                ]
            );
        }

        // Participants — toàn bộ 8 attendees nội bộ. Chair/operator set qua FK trên meeting.
        $participants = [];
        foreach ($attendees as $idx => $attendee) {
            $attendee->loadMissing('user.profile');
            $responseStatus = $idx <= 5 ? 'accepted' : 'pending';

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
                    'status' => 'closed',
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

        // Kết luận
        MeetingConclusion::firstOrCreate(
            ['title' => 'Kết luận kỳ họp HĐND thường kỳ tháng 5/2026', 'meeting_id' => $meeting->id],
            [
                'organization_id' => $this->orgId,
                'content' => 'Kỳ họp đã thông qua nghị quyết phân bổ ngân sách bổ sung 2026 với tỷ lệ tán thành cao. Yêu cầu UBND TP triển khai theo kế hoạch.',
                'status' => 'published',
            ]
        );
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

        $meeting = Meeting::firstOrCreate(
            ['title' => 'Họp giao ban tuần 19/2026', 'organization_id' => $this->orgId],
            [
                'meeting_type_id' => $types['Họp giao ban']->id,
                'meeting_location_id' => $locations['Phòng họp tầng 5 - Sở Nội vụ']->id,
                'is_public' => false,
                'content' => 'Tổng kết công việc tuần trước, triển khai nhiệm vụ tuần này.',
                'start_time' => $start,
                'end_time' => $start->copy()->addHours(2),
                'status' => 'published',
                'view_count' => 12,
                'published_at' => $start->copy()->subDay(),
            ]
        );

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
                'status' => 'published',
                'view_count' => 8,
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

        $meeting = Meeting::firstOrCreate(
            ['title' => 'Họp chuyên đề chuyển đổi số ngành y tế (DRAFT)', 'organization_id' => $this->orgId],
            [
                'meeting_type_id' => $types['Họp chuyên đề']->id,
                'meeting_location_id' => $locations['Họp trực tuyến (Zoom)']->id,
                'is_public' => false,
                'content' => 'Đánh giá tiến độ chuyển đổi số ngành y tế thành phố — bản thảo.',
                'start_time' => $start,
                'end_time' => $start->copy()->addHours(3),
                'status' => 'draft',
                'view_count' => 0,
            ]
        );

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
