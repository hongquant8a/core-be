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
            ],
        ],
        'discussion' => [
            'label' => 'Ý kiến thảo luận',
            'description' => 'V. Thảo luận — mỗi row = 1 ý kiến',
            'columns' => [
                'd_stt' => 'STT',
                'd_speaker' => 'Đại biểu phát biểu',
                'd_content' => 'Nội dung ý kiến',
                'd_note' => 'Ghi chú thảo luận (operator điền)',
            ],
        ],
        'question' => [
            'label' => 'Chất vấn',
            'description' => 'VI. Chất vấn — mỗi row = 1 chất vấn',
            'columns' => [
                'q_stt' => 'STT',
                'q_speaker' => 'Đại biểu chất vấn',
                'q_content' => 'Nội dung chất vấn',
                'q_answer' => 'Nội dung trả lời chất vấn (operator điền)',
            ],
        ],
        'vote' => [
            'label' => 'Biểu quyết (Tỷ lệ trên Tổng đại biểu)',
            'description' => 'VII. Biểu quyết (Tỷ lệ / Tổng ĐB) — mỗi row = 1 nội dung',
            'columns' => [
                'v_stt' => 'STT',
                'v_topic' => 'Nội dung biểu quyết',
                'v_agree' => 'Số phiếu tán thành',
                'v_disagree' => 'Số phiếu không tán thành',
                'v_abstain' => 'Số phiếu không ý kiến',
                'v_not_voted' => 'Số phiếu chưa biểu quyết',
                'v_total_eligible' => 'Tổng số đại biểu',
                'v_total_voted' => 'Số đại biểu có mặt',
                'v_agree_rate_total' => 'Tỷ lệ tán thành / tổng ĐB (%)',
                'v_agree_rate_present' => 'Tỷ lệ tán thành / ĐB có mặt (%)',
                'v_disagree_rate_total' => 'Tỷ lệ không tán thành / tổng ĐB (%)',
                'v_disagree_rate_present' => 'Tỷ lệ không tán thành / ĐB có mặt (%)',
                'v_abstain_rate_total' => 'Tỷ lệ không ý kiến / tổng ĐB (%)',
                'v_abstain_rate_present' => 'Tỷ lệ không ý kiến / ĐB có mặt (%)',
                'v_not_voted_rate_total' => 'Tỷ lệ chưa biểu quyết / tổng ĐB (%)',
                'v_result' => 'Kết quả (Thông qua / Không thông qua)',
            ],
        ],
        'vote_present' => [
            'label' => 'Biểu quyết (Tỷ lệ trên Đại biểu có mặt)',
            'description' => 'VII. Biểu quyết (Tỷ lệ / ĐB có mặt) — mỗi row = 1 nội dung',
            'columns' => [
                'vp_stt' => 'STT',
                'vp_topic' => 'Nội dung biểu quyết',
                'vp_agree' => 'Số phiếu tán thành',
                'vp_disagree' => 'Số phiếu không tán thành',
                'vp_abstain' => 'Số phiếu không ý kiến',
                'vp_not_voted' => 'Số phiếu chưa biểu quyết (trong số có mặt)',
                'vp_total_voted' => 'Số đại biểu có mặt',
                'vp_agree_rate_present' => 'Tỷ lệ tán thành (%)',
                'vp_disagree_rate_present' => 'Tỷ lệ không tán thành (%)',
                'vp_abstain_rate_present' => 'Tỷ lệ không ý kiến (%)',
                'vp_not_voted_rate_present' => 'Tỷ lệ chưa biểu quyết (%)',
                'vp_result' => 'Kết quả (Thông qua / Không thông qua)',
            ],
        ],
    ];

    /**
     * Build file .docx mẫu chứa toàn bộ placeholder hỗ trợ — dùng cho user download
     * làm starting point khi soạn template thật. Cấu trúc match biên bản HĐND chuẩn.
     *
     * @return string path file output
     */
    public function generateSample(): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(13);

        $h1 = ['bold' => true, 'size' => 14];
        $h2 = ['bold' => true, 'size' => 13];
        $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
        $headerRow = ['bgColor' => 'CCCCCC'];

        $phpWord->addTableStyle('MainTable', $tableStyle);
        // Signature table cần style riêng (no border) — nhưng PHẢI có style để PhpWord
        // emit <w:tblPr>. Word strict mode reject <w:tbl> không có <w:tblPr>.
        $phpWord->addTableStyle('SignatureTable', ['borderSize' => 0, 'cellMargin' => 0]);

        $section = $phpWord->addSection();

        // Header
        $section->addText('HỘI ĐỒNG NHÂN DÂN', $h1, $center);
        $section->addText('.......................................................', null, $center);
        $section->addTextBreak(1);
        $section->addText('BIÊN BẢN HỌP', $h1, $center);
        $section->addText('${meeting_title}', $h2, $center);
        $section->addText('(Thường lệ / Bất thường - Số: ......../${so_bien_ban}/HĐND)', null, $center);
        $section->addTextBreak(1);

        // Thông tin
        $infoTable = $section->addTable('MainTable');
        foreach ([
            ['Địa điểm họp:', ': ${dia_diem_hop}'],
            ['Thời gian bắt đầu:', ': ${thoi_gian_bat_dau}'],
            ['Thời gian kết thúc:', ': ${thoi_gian_ket_thuc}'],
            ['Ngày họp:', '${ngay_hop_so} tháng ${thang_hop} năm ${nam_hop}'],
            ['Chủ tọa:', ': ${chu_toa}'],
            ['Thư ký:', ': ${thu_ky}'],
        ] as $row) {
            $r = $infoTable->addRow();
            $r->addCell(3500)->addText($row[0], ['bold' => true]);
            $r->addCell(6500)->addText($row[1]);
        }
        $section->addTextBreak(1);

        // I. Chương trình
        $section->addText('I. CHƯƠNG TRÌNH HỌP', $h1);
        $section->addText('Cuộc họp được tiến hành theo chương trình sau:');
        $t = $section->addTable('MainTable');
        $hr = $t->addRow();
        $hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr->addCell(5000, $headerRow)->addText('Nội dung chương trình', ['bold' => true], $center);
        $hr->addCell(2500, $headerRow)->addText('Người trình bày', ['bold' => true], $center);
        $hr->addCell(1500, $headerRow)->addText('Thời gian (phút)', ['bold' => true], $center);
        $dr = $t->addRow();
        $dr->addCell(800)->addText('${stt}', null, $center);
        $dr->addCell(5000)->addText('${agenda_content}');
        $dr->addCell(2500)->addText('${agenda_person}');
        $dr->addCell(1500)->addText('${agenda_duration}', null, $center);
        $section->addTextBreak(1);

        // II. Thành phần dự họp
        $section->addText('II. THÀNH PHẦN DỰ HỌP', $h1);
        $section->addText('- Tổng số đại biểu: ${tong_dai_bieu} người');
        $section->addText('- Số đại biểu có mặt: ${dai_bieu_co_mat} người (đạt ${ty_le_co_mat} %)');
        $section->addText('- Số đại biểu vắng mặt: ${dai_bieu_vang_mat} người');
        $section->addTextBreak(1);
        $section->addText('Danh sách đại biểu dự họp:', ['bold' => true]);
        $t = $section->addTable('MainTable');
        $hr = $t->addRow();
        $hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr->addCell(4000, $headerRow)->addText('Họ và tên', ['bold' => true], $center);
        $hr->addCell(5000, $headerRow)->addText('Chức vụ / Đơn vị', ['bold' => true], $center);
        $dr = $t->addRow();
        $dr->addCell(800)->addText('${p_stt}', null, $center);
        $dr->addCell(4000)->addText('${p_name}');
        $dr->addCell(5000)->addText('${p_position}');
        $section->addTextBreak(1);

        // III. Vắng mặt
        $section->addText('III. THÀNH PHẦN VẮNG MẶT', $h1);
        $t = $section->addTable('MainTable');
        $hr = $t->addRow();
        $hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr->addCell(4500, $headerRow)->addText('Họ và tên', ['bold' => true], $center);
        $hr->addCell(4500, $headerRow)->addText('Đơn vị', ['bold' => true], $center);
        $dr = $t->addRow();
        $dr->addCell(800)->addText('${a_stt}', null, $center);
        $dr->addCell(4500)->addText('${a_name}');
        $dr->addCell(4500)->addText('${a_dept}');
        $section->addTextBreak(1);

        // IV. Điểm danh
        $section->addText('IV. ĐIỂM DANH', $h1);
        $section->addText('Chủ tọa cuộc họp tiến hành điểm danh đại biểu:');
        $section->addText('- Tổng số đại biểu theo danh sách: ${tong_dai_bieu} người');
        $section->addText('- Số đại biểu có mặt: ${dai_bieu_co_mat} người');
        $section->addText('- Số đại biểu vắng mặt: ${dai_bieu_vang_mat} người');
        $section->addText('Kết luận điểm danh: Cuộc họp ${du_dieu_kien} đủ điều kiện tiến hành / ${chua_du_dieu_kien} chưa đủ điều kiện (đạt ${ty_le_co_mat} % tổng số đại biểu).');
        $section->addTextBreak(1);

        // V. Thảo luận
        $section->addText('V. CÁC Ý KIẾN THẢO LUẬN', $h1);
        $section->addText('Sau khi nghe trình bày các nội dung, các đại biểu tiến hành thảo luận:');
        $t = $section->addTable('MainTable');
        $hr = $t->addRow();
        $hr->addCell(700, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr->addCell(3000, $headerRow)->addText('Đại biểu phát biểu', ['bold' => true], $center);
        $hr->addCell(3000, $headerRow)->addText('Nội dung ý kiến thảo luận', ['bold' => true], $center);
        $hr->addCell(3300, $headerRow)->addText('Ghi chú thảo luận', ['bold' => true], $center);
        $dr = $t->addRow();
        $dr->addCell(700)->addText('${d_stt}', null, $center);
        $dr->addCell(3000)->addText('${d_speaker}');
        $dr->addCell(3000)->addText('${d_content}');
        $dr->addCell(3300)->addText('${d_note}');
        $section->addTextBreak(1);

        // VI. Chất vấn
        $section->addText('VI. CÁC Ý KIẾN CHẤT VẤN', $h1);
        $section->addText('Phần chất vấn và trả lời chất vấn tại kỳ họp:');
        $t = $section->addTable('MainTable');
        $hr = $t->addRow();
        $hr->addCell(700, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr->addCell(3000, $headerRow)->addText('Đại biểu chất vấn', ['bold' => true], $center);
        $hr->addCell(3000, $headerRow)->addText('Nội dung chất vấn', ['bold' => true], $center);
        $hr->addCell(3300, $headerRow)->addText('Nội dung trả lời', ['bold' => true], $center);
        $dr = $t->addRow();
        $dr->addCell(700)->addText('${q_stt}', null, $center);
        $dr->addCell(3000)->addText('${q_speaker}');
        $dr->addCell(3000)->addText('${q_content}');
        $dr->addCell(3300)->addText('${q_answer}');
        $section->addTextBreak(1);

        // VII. Biểu quyết
        $section->addText('VII. CÁC NỘI DUNG BIỂU QUYẾT VÀ KẾT QUẢ', $h1);
        $section->addText('Hội đồng nhân dân tiến hành biểu quyết các nội dung sau:');
        
        $section->addTextBreak(1);
        $section->addText('1. Kết quả biểu quyết tính trên tổng số đại biểu', ['bold' => true]);
        $t1 = $section->addTable('MainTable');
        $hr1 = $t1->addRow();
        $hr1->addCell(700, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr1->addCell(3500, $headerRow)->addText('Nội dung biểu quyết', ['bold' => true], $center);
        $hr1->addCell(1500, $headerRow)->addText('Tán thành (Số phiếu / Tỷ lệ)', ['bold' => true], $center);
        $hr1->addCell(1500, $headerRow)->addText('Không tán thành', ['bold' => true], $center);
        $hr1->addCell(1000, $headerRow)->addText('Không ý kiến', ['bold' => true], $center);
        $hr1->addCell(1000, $headerRow)->addText('Chưa biểu quyết', ['bold' => true], $center);
        $hr1->addCell(1500, $headerRow)->addText('Kết quả', ['bold' => true], $center);
        
        $dr1 = $t1->addRow();
        $dr1->addCell(700)->addText('${v_stt}', null, $center);
        $dr1->addCell(3500)->addText('${v_topic}');
        $dr1->addCell(1500)->addText('${v_agree} (${v_agree_rate_total}%)', null, $center);
        $dr1->addCell(1500)->addText('${v_disagree} (${v_disagree_rate_total}%)', null, $center);
        $dr1->addCell(1000)->addText('${v_abstain} (${v_abstain_rate_total}%)', null, $center);
        $dr1->addCell(1000)->addText('${v_not_voted} (${v_not_voted_rate_total}%)', null, $center);
        $dr1->addCell(1500)->addText('${v_result}', null, $center);
        $section->addTextBreak(1);

        $section->addText('2. Kết quả biểu quyết tính trên số đại biểu có mặt', ['bold' => true]);
        $t2 = $section->addTable('MainTable');
        $hr2 = $t2->addRow();
        $hr2->addCell(700, $headerRow)->addText('STT', ['bold' => true], $center);
        $hr2->addCell(3500, $headerRow)->addText('Nội dung biểu quyết', ['bold' => true], $center);
        $hr2->addCell(1500, $headerRow)->addText('Tán thành (Số phiếu / Tỷ lệ)', ['bold' => true], $center);
        $hr2->addCell(1500, $headerRow)->addText('Không tán thành', ['bold' => true], $center);
        $hr2->addCell(1000, $headerRow)->addText('Không ý kiến', ['bold' => true], $center);
        $hr2->addCell(1000, $headerRow)->addText('Chưa biểu quyết', ['bold' => true], $center);
        $hr2->addCell(1500, $headerRow)->addText('Kết quả', ['bold' => true], $center);

        $dr2 = $t2->addRow();
        $dr2->addCell(700)->addText('${vp_stt}', null, $center);
        $dr2->addCell(3500)->addText('${vp_topic}');
        $dr2->addCell(1500)->addText('${vp_agree} (${vp_agree_rate_present}%)', null, $center);
        $dr2->addCell(1500)->addText('${vp_disagree} (${vp_disagree_rate_present}%)', null, $center);
        $dr2->addCell(1000)->addText('${vp_abstain} (${vp_abstain_rate_present}%)', null, $center);
        $dr2->addCell(1000)->addText('${vp_not_voted} (${vp_not_voted_rate_present}%)', null, $center);
        $dr2->addCell(1500)->addText('${vp_result}', null, $center);
        $section->addTextBreak(1);

        // VIII. Kết luận
        $section->addText('VIII. KẾT LUẬN CUỘC HỌP', $h1);
        $section->addText('Cuộc họp kết thúc lúc ${gio_ket_thuc} giờ ${phut_ket_thuc} phút, ngày ${ngay_ket_thuc_so} tháng ${thang_ket_thuc} năm ${nam_ket_thuc}.');
        $section->addTextBreak(1);
        $section->addText('Chủ tọa tổng kết và kết luận cuộc họp như sau:', ['bold' => true]);
        $section->addText('${noi_dung_ket_luan}');
        $section->addTextBreak(3);

        // Chữ ký
        $signTable = $section->addTable('SignatureTable');
        $sr = $signTable->addRow();
        $c1 = $sr->addCell(5000);
        $c1->addText('THƯ KÝ', ['bold' => true], $center);
        $c1->addText('(Ký, ghi rõ họ tên)', null, $center);
        $c1->addTextBreak(3);
        $c1->addText('${thu_ky}', ['bold' => true], $center);

        $c2 = $sr->addCell(5000);
        $c2->addText('CHỦ TỌA CUỘC HỌP', ['bold' => true], $center);
        $c2->addText('(Ký, đóng dấu, ghi rõ họ tên)', null, $center);
        $c2->addTextBreak(3);
        $c2->addText('${chu_toa}', ['bold' => true], $center);

        $outPath = storage_path('app/'.uniqid('sample_').'.docx');
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outPath);

        return $outPath;
    }

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

        // Word strict mode reject <w:tbl> không có <w:tblPr>. Một số template (sample cũ)
        // bị thiếu element này ở signature table → file mở trong Word báo lỗi corrupt.
        // Inject <w:tblPr/> rỗng cho mọi <w:tbl> bị thiếu để cứu output.
        $this->repairDocxTables($outPath);

        return $outPath;
    }

    /**
     * Post-process docx output để fix các issue làm Word strict mode báo "corrupt":
     *
     *   1. PhpWord output `<w:tblPr>` SAU `<w:tblGrid>` trong khi OOXML schema CT_Tbl
     *      yêu cầu sequence (tblPr, tblGrid, tr+) — tblPr PHẢI trước tblGrid → swap.
     *      Trường hợp tbl thiếu tblPr → insert empty trước tblGrid.
     *      Duplicate tblPr → giữ cái có nội dung (style), bỏ cái rỗng.
     *   2. `<w:pgSz>` / `<w:pgMar>` có giá trị thập phân (PhpWord convert mm → twips
     *      ra decimal, vd "11905.511811023622") → Word schema yêu cầu integer twips
     *      → round về int.
     *
     * Idempotent: phần đã đúng giữ nguyên.
     */
    private function repairDocxTables(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return;
        }
        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            return;
        }

        $original = $xml;

        // Issue 1+5: order tblPr trước tblGrid + dedupe + reorder rPr children canonical.
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        // Suppress warning từ XML potentially malformed; nếu fail thì skip fix #1.
        if (@$dom->loadXML($xml)) {
            $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

            // Reorder mọi <w:rPr> children theo CT_RPr canonical sequence.
            // PhpWord output sai: <w:sz/><w:szCs/><w:b/><w:bCs/> — phải <w:b/><w:bCs/><w:sz/><w:szCs/>.
            $rPrOrder = [
                'rStyle', 'rFonts', 'b', 'bCs', 'i', 'iCs', 'caps', 'smallCaps',
                'strike', 'dstrike', 'outline', 'shadow', 'emboss', 'imprint',
                'noProof', 'snapToGrid', 'vanish', 'webHidden', 'color', 'spacing',
                'w', 'kern', 'position', 'sz', 'szCs', 'highlight', 'u', 'effect',
                'bdr', 'shd', 'fitText', 'vertAlign', 'rtl', 'cs', 'em', 'lang',
                'eastAsianLayout', 'specVanish', 'oMath',
            ];
            foreach (iterator_to_array($dom->getElementsByTagNameNS($ns, 'rPr')) as $rPr) {
                $this->sortChildrenByLocalName($rPr, $rPrOrder);
            }

            $tbls = $dom->getElementsByTagNameNS($ns, 'tbl');
            // Snapshot vì NodeList sẽ thay đổi khi move/remove node.
            $tblNodes = [];
            foreach ($tbls as $tbl) {
                $tblNodes[] = $tbl;
            }
            foreach ($tblNodes as $tbl) {
                $tblPrs = [];
                $tblGrid = null;
                foreach (iterator_to_array($tbl->childNodes) as $child) {
                    if (! ($child instanceof \DOMElement)) {
                        continue;
                    }
                    if ($child->localName === 'tblPr') {
                        $tblPrs[] = $child;
                    } elseif ($child->localName === 'tblGrid' && $tblGrid === null) {
                        $tblGrid = $child;
                    }
                }

                // Chọn 1 tblPr "chính" — ưu tiên cái có nội dung (style/borders), fallback tblPr rỗng.
                $primary = null;
                foreach ($tblPrs as $pr) {
                    if ($pr->hasChildNodes()) {
                        $primary = $pr;
                        break;
                    }
                }
                if ($primary === null && ! empty($tblPrs)) {
                    $primary = $tblPrs[0];
                }
                if ($primary === null) {
                    $primary = $dom->createElementNS($ns, 'w:tblPr');
                }

                // Remove ALL tblPr children rồi insert primary trước tblGrid (hoặc đầu tbl).
                foreach ($tblPrs as $pr) {
                    if ($pr->parentNode === $tbl) {
                        $tbl->removeChild($pr);
                    }
                }
                if ($tblGrid !== null) {
                    $tbl->insertBefore($primary, $tblGrid);
                } else {
                    $tbl->insertBefore($primary, $tbl->firstChild);
                }
            }
            $xml = $dom->saveXML();
        }

        // Issue 2: pgSz/pgMar attributes có decimal → round về integer.
        $xml = preg_replace_callback(
            '/<w:(pgSz|pgMar)\b[^>]*>/u',
            function ($m) {
                return preg_replace_callback(
                    '/(\bw:(?:w|h|top|right|bottom|left|header|footer|gutter)=")(\d+(?:\.\d+)?)("\s*)/u',
                    fn ($attr) => $attr[1].(string) (int) round((float) $attr[2]).$attr[3],
                    $m[0]
                );
            },
            $xml
        );

        if ($xml !== $original) {
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $xml);
        }

        // Issue 3: word/styles.xml — PhpWord emit <w:tblPr> con sai sequence (CT_TblPrBase).
        // Spec: tblStyle → tblpPr → tblOverlap → ... → tblW → ... → tblBorders → shd → tblLayout → tblCellMar → ...
        // PhpWord output: tblW → tblLayout → tblCellMar → tblBorders → Word strict reject.
        $stylesXml = $zip->getFromName('word/styles.xml');
        if ($stylesXml !== false) {
            $fixed = $this->reorderTblPrChildren($stylesXml);
            if ($fixed !== null && $fixed !== $stylesXml) {
                $zip->deleteName('word/styles.xml');
                $zip->addFromString('word/styles.xml', $fixed);
            }
        }

        $zip->close();
    }

    /**
     * Reorder children của mọi <w:tblPr> theo CT_TblPrBase canonical sequence.
     * Dùng cho styles.xml (PhpWord emit sai order trong style definition).
     */
    private function reorderTblPrChildren(string $xml): ?string
    {
        $order = [
            'tblStyle', 'tblpPr', 'tblOverlap', 'bidiVisual',
            'tblStyleRowBandSize', 'tblStyleColBandSize',
            'tblW', 'jc', 'tblCellSpacing', 'tblInd',
            'tblBorders', 'shd', 'tblLayout', 'tblCellMar',
            'tblLook', 'tblCaption', 'tblDescription',
        ];

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (! @$dom->loadXML($xml)) {
            return null;
        }
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        foreach (iterator_to_array($dom->getElementsByTagNameNS($ns, 'tblPr')) as $tblPr) {
            $this->sortChildrenByLocalName($tblPr, $order);
        }

        return $dom->saveXML();
    }

    /**
     * Sort children của 1 DOMElement theo array tên local — element không có trong list
     * sẽ đẩy về cuối, giữ relative order.
     */
    private function sortChildrenByLocalName(\DOMElement $parent, array $order): void
    {
        $children = [];
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! ($child instanceof \DOMElement)) {
                continue;
            }
            $children[] = $child;
            $parent->removeChild($child);
        }
        usort($children, function ($a, $b) use ($order) {
            $ia = array_search($a->localName, $order, true);
            $ib = array_search($b->localName, $order, true);
            if ($ia === false) {
                $ia = PHP_INT_MAX;
            }
            if ($ib === false) {
                $ib = PHP_INT_MAX;
            }

            return $ia <=> $ib;
        });
        foreach ($children as $child) {
            $parent->appendChild($child);
        }
    }

    /**
     * Strip HTML tags + decode entities — dùng cho text fields nhập từ rich-text editor
     * (meeting.content, registration.content, ...). PhpWord setValue KHÔNG escape XML
     * → nếu để HTML tags raw, chúng thành child element của <w:t> → OOXML schema reject.
     */
    private function cleanText(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        return trim(html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function fillScalar(TemplateProcessor $tp, Meeting $m): void
    {
        $start = $m->start_time;
        $end = $m->end_time;
        $participants = $m->participants ?? collect();
        $totalParticipants = $participants->count();
        $presentCount = $participants->filter(fn ($p) => $p->attendance && $p->attendance->status === 'present')->count();
        // Vắng mặt = những người chưa được điểm danh có mặt (pending, absent, hoặc chưa có record)
        $absentCount = $totalParticipants - $presentCount;
        $rate = $totalParticipants > 0 ? round(($presentCount / $totalParticipants) * 100, 1) : 0;
        $hasQuorum = $rate >= 50;

        // Text fields có thể chứa HTML (rich-text editor) → strip qua cleanText() trước khi
        // setValue. PhpWord setValue KHÔNG escape XML → tag HTML literal phá schema OOXML.
        $scalars = [
            'meeting_title' => $this->cleanText($m->title),
            'so_bien_ban' => (string) $m->id,
            'dia_diem_hop' => $this->cleanText($m->meetingLocation?->name),
            'thoi_gian_bat_dau' => $start?->format('H:i') ?? '',
            'thoi_gian_ket_thuc' => $end?->format('H:i') ?? '',
            'ngay_hop' => $start?->format('d/m/Y') ?? '',
            'ngay_hop_so' => $start?->format('d') ?? '',
            'thang_hop' => $start?->format('m') ?? '',
            'nam_hop' => $start?->format('Y') ?? '',
            'chu_toa' => $this->cleanText($m->chairperson?->user?->name ?? $m->chairperson?->name),
            'thu_ky' => $this->cleanText($m->operator?->user?->name ?? $m->operator?->name),
            'tong_dai_bieu' => (string) $totalParticipants,
            'dai_bieu_co_mat' => (string) $presentCount,
            'dai_bieu_vang_mat' => (string) $absentCount,
            'ty_le_co_mat' => (string) $rate,
            'du_dieu_kien' => $hasQuorum ? 'X' : '',
            'chua_du_dieu_kien' => $hasQuorum ? '' : 'X',
            // meeting.content thường chứa HTML (rich-text) → strip mạnh nhất ở đây.
            'noi_dung_ket_luan' => $this->cleanText($m->content),
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
        // Agenda có phân cấp cha-con (parent_id) → flatten DFS order, không phải flat sort_order.
        $rows = \App\Modules\Meeting\Models\MeetingAgenda::flattenedByMeeting($m->id);
        if ($rows->isEmpty()) {
            // Không có row → setValue trống để xóa placeholder (nếu template có row mẫu).
            $tp->cloneRow('stt', 0);
            return;
        }
        $tp->cloneRow('stt', $rows->count());
        foreach ($rows->values() as $i => $a) {
            $idx = $i + 1;
            // STT theo path tree (1, 1.1, 1.2, 2, ...) — agenda có phân cấp cha-con.
            $tp->setValue("stt#{$idx}", $a->_tree_path ?? (string) $idx);
            $tp->setValue("agenda_content#{$idx}", $this->cleanText($a->content));
            $tp->setValue("agenda_person#{$idx}", $this->cleanText($a->person_in_charge));
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
            $tp->setValue("p_name#{$idx}", $this->cleanText($p->display_name ?: $p->attendee?->user?->name));
            $tp->setValue("p_position#{$idx}", $this->cleanText(trim(($p->position_name ?? '').' / '.($p->department_name ?? ''), ' /')));
        }
    }

    private function fillAbsenceTable(TemplateProcessor $tp, Meeting $m): void
    {
        $rows = ($m->participants ?? collect())->filter(
            fn ($p) => ! $p->attendance || $p->attendance->status !== 'present'
        )->values();
        $tp->cloneRow('a_stt', max($rows->count(), 0));
        foreach ($rows as $i => $p) {
            $idx = $i + 1;
            $tp->setValue("a_stt#{$idx}", (string) $idx);
            $tp->setValue("a_name#{$idx}", $this->cleanText($p->display_name ?: $p->attendee?->user?->name));
            $tp->setValue("a_dept#{$idx}", $this->cleanText($p->department_name));
            $tp->setValue("a_reason#{$idx}", ''); // Giữ lại để không bị hiện text literal nếu template cũ còn dùng
        }
    }

    private function fillDiscussionTable(TemplateProcessor $tp, Meeting $m): void
    {
        // Agenda có phân cấp parent-child → sort theo tree_index thay vì agenda_id flat.
        $treeIndex = \App\Modules\Meeting\Models\MeetingAgenda::treeIndexMap($m->id);
        $rows = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::query()
            ->with('participant.attendee.user')
            ->where('meeting_id', $m->id)
            ->where('type', 'discussion')
            ->get()
            ->sortBy(fn ($r) => sprintf('%010d|%s',
                $treeIndex[$r->meeting_agenda_id] ?? PHP_INT_MAX,
                $r->created_at?->toIso8601String() ?? '~'
            ))
            ->values();
        $tp->cloneRow('d_stt', max($rows->count(), 0));
        foreach ($rows->values() as $i => $r) {
            $idx = $i + 1;
            $tp->setValue("d_stt#{$idx}", (string) $idx);
            $tp->setValue("d_speaker#{$idx}", $this->cleanText($r->participant?->display_name));
            $tp->setValue("d_content#{$idx}", $this->cleanText($r->content));
            $tp->setValue("d_note#{$idx}", $this->cleanText($r->operator_note));
        }
    }

    private function fillQuestionTable(TemplateProcessor $tp, Meeting $m): void
    {
        $treeIndex = \App\Modules\Meeting\Models\MeetingAgenda::treeIndexMap($m->id);
        $rows = \App\Modules\Meeting\Models\MeetingDiscussionRegistration::query()
            ->with('participant.attendee.user')
            ->where('meeting_id', $m->id)
            ->where('type', 'question')
            ->get()
            ->sortBy(fn ($r) => sprintf('%010d|%s',
                $treeIndex[$r->meeting_agenda_id] ?? PHP_INT_MAX,
                $r->created_at?->toIso8601String() ?? '~'
            ))
            ->values();
        $tp->cloneRow('q_stt', max($rows->count(), 0));
        foreach ($rows->values() as $i => $r) {
            $idx = $i + 1;
            $tp->setValue("q_stt#{$idx}", (string) $idx);
            $tp->setValue("q_speaker#{$idx}", $this->cleanText($r->participant?->display_name));
            $tp->setValue("q_content#{$idx}", $this->cleanText($r->content));
            // Chất vấn → answer_content (Nội dung trả lời chất vấn) — KHÔNG dùng operator_note
            // (operator_note dành cho thảo luận = ghi chú thảo luận).
            $tp->setValue("q_answer#{$idx}", $this->cleanText($r->answer_content));
        }
    }

    private function fillVoteTable(TemplateProcessor $tp, Meeting $m): void
    {
        $treeIndex = \App\Modules\Meeting\Models\MeetingAgenda::treeIndexMap($m->id);
        $rows = \App\Modules\Meeting\Models\MeetingVoteTopic::query()
            ->where('meeting_id', $m->id)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->sortBy(fn ($t) => sprintf('%010d|%010d',
                $treeIndex[$t->meeting_agenda_id] ?? PHP_INT_MAX,
                $t->sort_order ?? 0
            ))
            ->values();
        try {
            $tp->cloneRow('v_stt', max($rows->count(), 0));
        } catch (\Exception $e) {}
        try {
            $tp->cloneRow('vp_stt', max($rows->count(), 0));
        } catch (\Exception $e) {}

        // Tính tổng số đại biểu và số đại biểu có mặt
        $participantUserIds = collect($m->participants)->pluck('attendee.user_id')->filter()->unique();
        $presentUserIds = collect($m->participants)->filter(fn ($p) => $p->attendance && $p->attendance->status === 'present')->pluck('attendee.user_id')->filter()->unique();

        $eligibleCount = $participantUserIds->count();
        $presentCount = $presentUserIds->count();

        foreach ($rows->values() as $i => $t) {
            $idx = $i + 1;
            $base = MeetingVoteResponse::query()->where('meeting_vote_topic_id', $t->id);
            $agree = (clone $base)->whereIn('option', ['agree', 'approve'])->count();
            $disagree = (clone $base)->whereIn('option', ['disagree', 'reject'])->count();
            $abstain = (clone $base)->where('option', 'abstain')->count();
            $voted = $agree + $disagree + $abstain;
            
            // Tính số "Chưa biểu quyết" cho 2 bảng riêng biệt
            $notVotedTotal = max(0, $eligibleCount - $voted);
            $notVotedPresent = max(0, $presentCount - $voted);

            // BẢNG 1: TÍNH TRÊN TỔNG ĐẠI BIỂU (v_)
            $tp->setValue("v_stt#{$idx}", (string) $idx);
            $tp->setValue("v_topic#{$idx}", $this->cleanText($t->title));
            $tp->setValue("v_agree#{$idx}", (string) $agree);
            $tp->setValue("v_disagree#{$idx}", (string) $disagree);
            $tp->setValue("v_abstain#{$idx}", (string) $abstain);
            $tp->setValue("v_not_voted#{$idx}", (string) $notVotedTotal);
            $tp->setValue("v_total_eligible#{$idx}", (string) $eligibleCount);
            $tp->setValue("v_total_voted#{$idx}", (string) $presentCount); // Support cũ

            $agreeRateTotal = $eligibleCount > 0 ? round(($agree / $eligibleCount) * 100, 1) : 0;
            $disagreeRateTotal = $eligibleCount > 0 ? round(($disagree / $eligibleCount) * 100, 1) : 0;
            $abstainRateTotal = $eligibleCount > 0 ? round(($abstain / $eligibleCount) * 100, 1) : 0;
            $notVotedRateTotal = $eligibleCount > 0 ? round(($notVotedTotal / $eligibleCount) * 100, 1) : 0;

            $tp->setValue("v_agree_rate_total#{$idx}", (string) $agreeRateTotal);
            $tp->setValue("v_disagree_rate_total#{$idx}", (string) $disagreeRateTotal);
            $tp->setValue("v_abstain_rate_total#{$idx}", (string) $abstainRateTotal);
            $tp->setValue("v_not_voted_rate_total#{$idx}", (string) $notVotedRateTotal);
            $tp->setValue("v_result#{$idx}", $agreeRateTotal >= 50 ? 'Thông qua' : 'Không thông qua');

            // BẢNG 2: TÍNH TRÊN ĐẠI BIỂU CÓ MẶT (vp_)
            $tp->setValue("vp_stt#{$idx}", (string) $idx);
            $tp->setValue("vp_topic#{$idx}", $this->cleanText($t->title));
            $tp->setValue("vp_agree#{$idx}", (string) $agree);
            $tp->setValue("vp_disagree#{$idx}", (string) $disagree);
            $tp->setValue("vp_abstain#{$idx}", (string) $abstain);
            $tp->setValue("vp_not_voted#{$idx}", (string) $notVotedPresent);
            $tp->setValue("vp_total_voted#{$idx}", (string) $presentCount);

            $agreeRatePresent = $presentCount > 0 ? round(($agree / $presentCount) * 100, 1) : 0;
            $disagreeRatePresent = $presentCount > 0 ? round(($disagree / $presentCount) * 100, 1) : 0;
            $abstainRatePresent = $presentCount > 0 ? round(($abstain / $presentCount) * 100, 1) : 0;
            $notVotedRatePresent = $presentCount > 0 ? round(($notVotedPresent / $presentCount) * 100, 1) : 0;

            $tp->setValue("vp_agree_rate_present#{$idx}", (string) $agreeRatePresent);
            $tp->setValue("vp_disagree_rate_present#{$idx}", (string) $disagreeRatePresent);
            $tp->setValue("vp_abstain_rate_present#{$idx}", (string) $abstainRatePresent);
            $tp->setValue("vp_not_voted_rate_present#{$idx}", (string) $notVotedRatePresent);
            $tp->setValue("vp_result#{$idx}", $agreeRateTotal >= 50 ? 'Thông qua' : 'Không thông qua');

            // Gán giá trị rate_present cho template cũ vẫn đang gọi v_
            $tp->setValue("v_agree_rate_present#{$idx}", (string) $agreeRatePresent);
            $tp->setValue("v_disagree_rate_present#{$idx}", (string) $disagreeRatePresent);
            $tp->setValue("v_abstain_rate_present#{$idx}", (string) $abstainRatePresent);
        }
    }
}
