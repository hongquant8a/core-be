<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingMinutesTemplate;
use App\Modules\Meeting\Models\MeetingVoteResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Sinh file biên bản .docx từ template upload bởi admin + data của 1 meeting cụ thể.
 *
 * Schema biến hỗ trợ 2 loại:
 *  1) Scalar — ${var_name}                                  -> setValue
 *  2) Table row — ${stt_row#index} (cloneRow first column)  -> cloneRow + setValue
 *
 * Block clone không hỗ trợ ở MVP — table row đủ cho mọi section của biên bản HĐND.
 */
class MeetingMinutesGenerator
{
    /**
     * Danh sách biến scalar — public cho FE hiển thị "cheatsheet" cho người soạn template.
     * Key = tên biến trong file .docx (placeholder ${key}); value = mô tả tiếng Việt.
     */
    public const SCALAR_VARIABLES = [
        // Header
        'meeting_title' => 'Tiêu đề cuộc họp',
        'so_bien_ban' => 'Số biên bản (lấy từ id meeting)',
        'dia_diem_hop' => 'Địa điểm họp',
        'thoi_gian_bat_dau' => 'Thời gian bắt đầu (HH:mm)',
        'thoi_gian_ket_thuc' => 'Thời gian kết thúc (HH:mm)',
        'ngay_hop' => 'Ngày họp (dd/mm/yyyy)',
        'ngay_hop_so' => 'Ngày trong ngày họp (dd)',
        'thang_hop' => 'Tháng (mm)',
        'nam_hop' => 'Năm (yyyy)',
        'chu_toa' => 'Họ tên chủ tọa',
        'thu_ky' => 'Họ tên thư ký / điều hành',

        // Aggregate điểm danh
        'tong_dai_bieu' => 'Tổng số đại biểu (theo danh sách mời)',
        'dai_bieu_co_mat' => 'Số đại biểu có mặt (đã duyệt)',
        'dai_bieu_vang_mat' => 'Số đại biểu vắng mặt',
        'ty_le_co_mat' => 'Tỷ lệ có mặt (%)',
        'du_dieu_kien' => 'Đủ điều kiện tiến hành (X / "")',
        'chua_du_dieu_kien' => 'Chưa đủ điều kiện (X / "")',

        // Kết luận
        'noi_dung_ket_luan' => 'Nội dung kết luận cuộc họp (lấy từ meeting.content)',
        'gio_ket_thuc' => 'Giờ kết thúc (HH)',
        'phut_ket_thuc' => 'Phút kết thúc (mm)',
        'ngay_ket_thuc_so' => 'Ngày kết thúc (dd)',
        'thang_ket_thuc' => 'Tháng kết thúc (mm)',
        'nam_ket_thuc' => 'Năm kết thúc (yyyy)',
    ];

    /**
     * Danh sách bảng (row loop) — public cho FE hiển thị. Mỗi key = column đầu tiên của row,
     * value = list các column khác cùng row + mô tả. FE chỉ cần show người dùng đoạn syntax:
     *   "Tạo bảng với header tùy ý, ở row mẫu (sẽ được clone) đặt: ${stt}, ${agenda_content}, ..."
     */
    public const TABLE_VARIABLES = [
        'agenda' => [
            'label' => 'Chương trình họp',
            'description' => 'I. Chương trình họp — mỗi row = 1 mục chương trình',
            'columns' => [
                'stt' => 'STT',
                'agenda_content' => 'Nội dung chương trình',
                'agenda_person' => 'Người trình bày',
                'agenda_duration' => 'Thời gian (phút)',
            ],
        ],
        'participant' => [
            'label' => 'Thành phần dự họp',
            'description' => 'II. Thành phần dự họp — mỗi row = 1 đại biểu',
            'columns' => [
                'p_stt' => 'STT',
                'p_name' => 'Họ và tên',
                'p_position' => 'Chức vụ / Đơn vị',
            ],
        ],
        'absence' => [
            'label' => 'Đại biểu vắng mặt',
            'description' => 'III. Vắng mặt — mỗi row = 1 đại biểu vắng',
            'columns' => [
                'a_stt' => 'STT',
                'a_name' => 'Họ và tên',
                'a_dept' => 'Đơn vị / Tổ đại biểu',
                'a_reason' => 'Lý do (note từ markAbsent)',
            ],
        ],
        'discussion' => [
            'label' => 'Ý kiến thảo luận',
            'description' => 'V. Thảo luận — mỗi row = 1 ý kiến',
            'columns' => [
                'd_stt' => 'STT',
                'd_speaker' => 'Đại biểu phát biểu',
                'd_content' => 'Nội dung ý kiến',
            ],
        ],
        'question' => [
            'label' => 'Chất vấn',
            'description' => 'VI. Chất vấn — mỗi row = 1 chất vấn',
            'columns' => [
                'q_stt' => 'STT',
                'q_speaker' => 'Đại biểu chất vấn',
                'q_content' => 'Nội dung chất vấn',
            ],
        ],
        'vote' => [
            'label' => 'Biểu quyết',
            'description' => 'VII. Biểu quyết — mỗi row = 1 chương trình biểu quyết',
            'columns' => [
                'v_stt' => 'STT',
                'v_topic' => 'Nội dung biểu quyết',
                'v_agree' => 'Số phiếu tán thành',
                'v_disagree' => 'Số phiếu không tán thành',
                'v_abstain' => 'Số phiếu không biểu quyết / ý kiến khác',
                'v_rate' => 'Tỷ lệ tán thành (%)',
                'v_result' => 'Kết quả (Thông qua / Không thông qua)',
            ],
        ],
    ];

    /**
     * Generate file .docx → return path tạm.
     */
    public function generate(Meeting $meeting, MeetingMinutesTemplate $template): string
    {
        $template->loadMissing('mediaFile');
        if (! $template->mediaFile) {
            throw new ModelNotFoundException('Template chưa có file đính kèm.');
        }

        $templatePath = $template->mediaFile->getPath();
        if (! is_file($templatePath)) {
            throw new ModelNotFoundException('File template không tồn tại trên storage.');
        }

        $tp = new TemplateProcessor($templatePath);

        $meeting->loadMissing([
            'meetingLocation',
            'chairperson.user',
            'operator.user',
            'agendas',
            'participants.attendee.user',
            'participants.attendance',
            'voteTopics',
        ]);

        $this->fillScalar($tp, $meeting);
        $this->fillAgendaTable($tp, $meeting);
        $this->fillParticipantTable($tp, $meeting);
        $this->fillAbsenceTable($tp, $meeting);
        $this->fillDiscussionTable($tp, $meeting);
        $this->fillQuestionTable($tp, $meeting);
        $this->fillVoteTable($tp, $meeting);

        $outPath = storage_path('app/'.uniqid('minutes_').'.docx');
        $tp->saveAs($outPath);

        return $outPath;
    }

    private function fillScalar(TemplateProcessor $tp, Meeting $m): void
    {
        $start = $m->start_time;
        $end = $m->end_time;
        $participants = $m->participants ?? collect();
        $totalParticipants = $participants->count();
        $presentCount = $participants->filter(fn ($p) => $p->attendance && $p->attendance->status === 'present')->count();
        // Vắng mặt = có row attendance với status=absent (KHÔNG tính total-present vì pending/null cũng bị tính sai).
        $absentCount = $participants->filter(fn ($p) => $p->attendance && $p->attendance->status === 'absent')->count();
        $rate = $totalParticipants > 0 ? round(($presentCount / $totalParticipants) * 100, 1) : 0;
        $hasQuorum = $rate >= 50;

        $scalars = [
            'meeting_title' => (string) $m->title,
            'so_bien_ban' => (string) $m->id,
            'dia_diem_hop' => (string) ($m->meetingLocation?->name ?? ''),
            'thoi_gian_bat_dau' => $start?->format('H:i') ?? '',
            'thoi_gian_ket_thuc' => $end?->format('H:i') ?? '',
            'ngay_hop' => $start?->format('d/m/Y') ?? '',
            'ngay_hop_so' => $start?->format('d') ?? '',
            'thang_hop' => $start?->format('m') ?? '',
            'nam_hop' => $start?->format('Y') ?? '',
            'chu_toa' => (string) ($m->chairperson?->user?->name ?? $m->chairperson?->name ?? ''),
            'thu_ky' => (string) ($m->operator?->user?->name ?? $m->operator?->name ?? ''),
            'tong_dai_bieu' => (string) $totalParticipants,
            'dai_bieu_co_mat' => (string) $presentCount,
            'dai_bieu_vang_mat' => (string) $absentCount,
            'ty_le_co_mat' => (string) $rate,
            'du_dieu_kien' => $hasQuorum ? 'X' : '',
            'chua_du_dieu_kien' => $hasQuorum ? '' : 'X',
            'noi_dung_ket_luan' => (string) ($m->content ?? ''),
            'gio_ket_thuc' => $end?->format('H') ?? '',
            'phut_ket_thuc' => $end?->format('i') ?? '',
            'ngay_ket_thuc_so' => $end?->format('d') ?? '',
            'thang_ket_thuc' => $end?->format('m') ?? '',
            'nam_ket_thuc' => $end?->format('Y') ?? '',
        ];

        foreach ($scalars as $k => $v) {
            // setValue silently ignore nếu biến không có trong template — an toàn.
            $tp->setValue($k, $v);
        }
    }

    private function fillAgendaTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = $m->agendas ?? collect();
        if ($rows->isEmpty()) {
            // Không có row → setValue trống để xóa placeholder (nếu template có row mẫu).
            $tp->cloneRow('stt', 0);
            return;
        }
        $tp->cloneRow('stt', $rows->count());
        foreach ($rows->values() as $i => $a) {
            $idx = $i + 1;
            $tp->setValue("stt#{$idx}", (string) $idx);
            $tp->setValue("agenda_content#{$idx}", (string) ($a->content ?? ''));
            $tp->setValue("agenda_person#{$idx}", (string) ($a->person_in_charge ?? ''));
            // start_time/end_time là column TIME (string "HH:mm:ss"), không phải Carbon.
            $duration = '';
            if ($a->start_time && $a->end_time) {
                try {
                    $s = \Carbon\Carbon::createFromTimeString((string) $a->start_time);
                    $e = \Carbon\Carbon::createFromTimeString((string) $a->end_time);
                    $duration = max(0, $s->diffInMinutes($e));
                } catch (\Throwable $e) {
                    $duration = '';
                }
            }
            $tp->setValue("agenda_duration#{$idx}", (string) $duration);
        }
    }

    private function fillParticipantTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = $m->participants ?? collect();
        $tp->cloneRow('p_stt', max($rows->count(), 0));
        foreach ($rows->values() as $i => $p) {
            $idx = $i + 1;
            $tp->setValue("p_stt#{$idx}", (string) $idx);
            $tp->setValue("p_name#{$idx}", (string) ($p->display_name ?? ''));
            $tp->setValue("p_position#{$idx}", trim(($p->position_name ?? '').' / '.($p->department_name ?? ''), ' /'));
        }
    }

    private function fillAbsenceTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = ($m->participants ?? collect())->filter(
            fn ($p) => $p->attendance && $p->attendance->status === 'absent'
        )->values();
        $tp->cloneRow('a_stt', max($rows->count(), 0));
        foreach ($rows as $i => $p) {
            $idx = $i + 1;
            $tp->setValue("a_stt#{$idx}", (string) $idx);
            $tp->setValue("a_name#{$idx}", (string) ($p->display_name ?? ''));
            $tp->setValue("a_dept#{$idx}", (string) ($p->department_name ?? ''));
            $tp->setValue("a_reason#{$idx}", (string) ($p->attendance?->note ?? ''));
        }
    }

    private function fillDiscussionTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::query()
            ->with('participant.attendee.user')
            ->where('meeting_id', $m->id)
            ->where('type', 'discussion')
            ->orderBy('sort_order')
            ->get();
        $tp->cloneRow('d_stt', max($rows->count(), 0));
        foreach ($rows->values() as $i => $r) {
            $idx = $i + 1;
            $tp->setValue("d_stt#{$idx}", (string) $idx);
            $tp->setValue("d_speaker#{$idx}", (string) ($r->participant?->display_name ?? ''));
            $tp->setValue("d_content#{$idx}", (string) ($r->content ?? ''));
        }
    }

    private function fillQuestionTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::query()
            ->with('participant.attendee.user')
            ->where('meeting_id', $m->id)
            ->where('type', 'question')
            ->orderBy('sort_order')
            ->get();
        $tp->cloneRow('q_stt', max($rows->count(), 0));
        foreach ($rows->values() as $i => $r) {
            $idx = $i + 1;
            $tp->setValue("q_stt#{$idx}", (string) $idx);
            $tp->setValue("q_speaker#{$idx}", (string) ($r->participant?->display_name ?? ''));
            $tp->setValue("q_content#{$idx}", (string) ($r->content ?? ''));
        }
    }

    private function fillVoteTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = $m->voteTopics ?? collect();
        $tp->cloneRow('v_stt', max($rows->count(), 0));
        foreach ($rows->values() as $i => $t) {
            $idx = $i + 1;
            $base = MeetingVoteResponse::query()->where('meeting_vote_topic_id', $t->id);
            $agree = (clone $base)->whereIn('option', ['agree', 'approve'])->count();
            $disagree = (clone $base)->whereIn('option', ['disagree', 'reject'])->count();
            $abstain = (clone $base)->where('option', 'abstain')->count();
            $total = $agree + $disagree + $abstain;
            $rate = $total > 0 ? round(($agree / $total) * 100, 1) : 0;

            $tp->setValue("v_stt#{$idx}", (string) $idx);
            $tp->setValue("v_topic#{$idx}", (string) $t->title);
            $tp->setValue("v_agree#{$idx}", (string) $agree);
            $tp->setValue("v_disagree#{$idx}", (string) $disagree);
            $tp->setValue("v_abstain#{$idx}", (string) $abstain);
            $tp->setValue("v_rate#{$idx}", (string) $rate);
            $tp->setValue("v_result#{$idx}", $rate >= 50 ? 'Thông qua' : 'Không thông qua');
        }
    }
}
