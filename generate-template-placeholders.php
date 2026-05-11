<?php

/**
 * Script 1-lần — generate file .docx template với placeholder pre-filled,
 * cấu trúc match Bieu_mau_bien_ban_hop_HDND_1.docx của user.
 *
 * Run: php generate-template-placeholders.php
 * Output: storage/app/meeting-minutes-template-filled.docx
 */

require __DIR__.'/vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();

// Default styles
$phpWord->setDefaultFontName('Times New Roman');
$phpWord->setDefaultFontSize(13);

$h1 = ['bold' => true, 'size' => 14];
$h2 = ['bold' => true, 'size' => 13];
$center = ['alignment' => Jc::CENTER];
$tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80];
$headerRow = ['bgColor' => 'CCCCCC'];

$phpWord->addTableStyle('MainTable', $tableStyle);

$section = $phpWord->addSection();

// HEADER
$section->addText('HỘI ĐỒNG NHÂN DÂN', $h1, $center);
$section->addText('.......................................................', null, $center);
$section->addTextBreak(1);
$section->addText('BIÊN BẢN HỌP', $h1, $center);
$section->addText('${meeting_title}', $h2, $center);
$section->addText('(Thường lệ / Bất thường - Số: ......../${so_bien_ban}/HĐND)', null, $center);
$section->addTextBreak(1);

// THÔNG TIN HỌP — bảng 2 cột
$infoTable = $section->addTable('MainTable');
$infoRows = [
    ['Địa điểm họp:', ': ${dia_diem_hop}'],
    ['Thời gian bắt đầu:', ': ${thoi_gian_bat_dau}'],
    ['Thời gian kết thúc:', ': ${thoi_gian_ket_thuc}'],
    ['Ngày họp:', '${ngay_hop_so} tháng ${thang_hop} năm ${nam_hop}'],
    ['Chủ tọa:', ': ${chu_toa}'],
    ['Thư ký:', ': ${thu_ky}'],
];
foreach ($infoRows as $row) {
    $r = $infoTable->addRow();
    $r->addCell(3500)->addText($row[0], ['bold' => true]);
    $r->addCell(6500)->addText($row[1]);
}
$section->addTextBreak(1);

// I. CHƯƠNG TRÌNH HỌP
$section->addText('I. CHƯƠNG TRÌNH HỌP', $h1);
$section->addText('Cuộc họp được tiến hành theo chương trình sau:');
$agendaTable = $section->addTable('MainTable');
$hr = $agendaTable->addRow();
$hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
$hr->addCell(5000, $headerRow)->addText('Nội dung chương trình', ['bold' => true], $center);
$hr->addCell(2500, $headerRow)->addText('Người trình bày', ['bold' => true], $center);
$hr->addCell(1500, $headerRow)->addText('Thời gian (phút)', ['bold' => true], $center);
// Row mẫu cho cloneRow — BE sẽ clone theo số agenda
$dr = $agendaTable->addRow();
$dr->addCell(800)->addText('${stt}', null, $center);
$dr->addCell(5000)->addText('${agenda_content}');
$dr->addCell(2500)->addText('${agenda_person}');
$dr->addCell(1500)->addText('${agenda_duration}', null, $center);
$section->addTextBreak(1);

// II. THÀNH PHẦN DỰ HỌP
$section->addText('II. THÀNH PHẦN DỰ HỌP', $h1);
$section->addText('- Tổng số đại biểu: ${tong_dai_bieu} người');
$section->addText('- Số đại biểu có mặt: ${dai_bieu_co_mat} người (đạt ${ty_le_co_mat} %)');
$section->addText('- Số đại biểu vắng mặt: ${dai_bieu_vang_mat} người');
$section->addTextBreak(1);
$section->addText('Danh sách đại biểu dự họp:', ['bold' => true]);
$pTable = $section->addTable('MainTable');
$hr = $pTable->addRow();
$hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
$hr->addCell(4000, $headerRow)->addText('Họ và tên', ['bold' => true], $center);
$hr->addCell(5000, $headerRow)->addText('Chức vụ / Đơn vị', ['bold' => true], $center);
$dr = $pTable->addRow();
$dr->addCell(800)->addText('${p_stt}', null, $center);
$dr->addCell(4000)->addText('${p_name}');
$dr->addCell(5000)->addText('${p_position}');
$section->addTextBreak(1);

// III. VẮNG MẶT
$section->addText('III. THÀNH PHẦN VẮNG MẶT', $h1);
$aTable = $section->addTable('MainTable');
$hr = $aTable->addRow();
$hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
$hr->addCell(3500, $headerRow)->addText('Họ và tên', ['bold' => true], $center);
$hr->addCell(2500, $headerRow)->addText('Đơn vị', ['bold' => true], $center);
$hr->addCell(3000, $headerRow)->addText('Lý do', ['bold' => true], $center);
$dr = $aTable->addRow();
$dr->addCell(800)->addText('${a_stt}', null, $center);
$dr->addCell(3500)->addText('${a_name}');
$dr->addCell(2500)->addText('${a_dept}');
$dr->addCell(3000)->addText('${a_reason}');
$section->addTextBreak(1);

// IV. ĐIỂM DANH (text — không có table)
$section->addText('IV. ĐIỂM DANH', $h1);
$section->addText('Chủ tọa cuộc họp tiến hành điểm danh đại biểu:');
$section->addText('- Tổng số đại biểu theo danh sách: ${tong_dai_bieu} người');
$section->addText('- Số đại biểu có mặt: ${dai_bieu_co_mat} người');
$section->addText('- Số đại biểu vắng mặt: ${dai_bieu_vang_mat} người');
$section->addText('Kết luận điểm danh: Cuộc họp ${du_dieu_kien} đủ điều kiện tiến hành / ${chua_du_dieu_kien} chưa đủ điều kiện (đạt ${ty_le_co_mat} % tổng số đại biểu).');
$section->addTextBreak(1);

// V. THẢO LUẬN
$section->addText('V. CÁC Ý KIẾN THẢO LUẬN', $h1);
$section->addText('Sau khi nghe trình bày các nội dung, các đại biểu tiến hành thảo luận:');
$dTable = $section->addTable('MainTable');
$hr = $dTable->addRow();
$hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
$hr->addCell(3500, $headerRow)->addText('Đại biểu phát biểu', ['bold' => true], $center);
$hr->addCell(5500, $headerRow)->addText('Nội dung ý kiến thảo luận', ['bold' => true], $center);
$dr = $dTable->addRow();
$dr->addCell(800)->addText('${d_stt}', null, $center);
$dr->addCell(3500)->addText('${d_speaker}');
$dr->addCell(5500)->addText('${d_content}');
$section->addTextBreak(1);

// VI. CHẤT VẤN
$section->addText('VI. CÁC Ý KIẾN CHẤT VẤN', $h1);
$section->addText('Phần chất vấn và trả lời chất vấn tại kỳ họp:');
$qTable = $section->addTable('MainTable');
$hr = $qTable->addRow();
$hr->addCell(800, $headerRow)->addText('STT', ['bold' => true], $center);
$hr->addCell(3500, $headerRow)->addText('Đại biểu chất vấn', ['bold' => true], $center);
$hr->addCell(5500, $headerRow)->addText('Nội dung chất vấn', ['bold' => true], $center);
$dr = $qTable->addRow();
$dr->addCell(800)->addText('${q_stt}', null, $center);
$dr->addCell(3500)->addText('${q_speaker}');
$dr->addCell(5500)->addText('${q_content}');
$section->addTextBreak(1);

// VII. BIỂU QUYẾT
$section->addText('VII. CÁC NỘI DUNG BIỂU QUYẾT VÀ KẾT QUẢ', $h1);
$section->addText('Hội đồng nhân dân tiến hành biểu quyết các nội dung sau:');
$vTable = $section->addTable('MainTable');
$hr = $vTable->addRow();
$hr->addCell(700, $headerRow)->addText('STT', ['bold' => true], $center);
$hr->addCell(3500, $headerRow)->addText('Nội dung biểu quyết', ['bold' => true], $center);
$hr->addCell(1000, $headerRow)->addText('Tán thành', ['bold' => true], $center);
$hr->addCell(1200, $headerRow)->addText('Không tán thành', ['bold' => true], $center);
$hr->addCell(1200, $headerRow)->addText('Không biểu quyết', ['bold' => true], $center);
$hr->addCell(1000, $headerRow)->addText('Tỷ lệ (%)', ['bold' => true], $center);
$hr->addCell(1500, $headerRow)->addText('Kết quả', ['bold' => true], $center);
$dr = $vTable->addRow();
$dr->addCell(700)->addText('${v_stt}', null, $center);
$dr->addCell(3500)->addText('${v_topic}');
$dr->addCell(1000)->addText('${v_agree}', null, $center);
$dr->addCell(1200)->addText('${v_disagree}', null, $center);
$dr->addCell(1200)->addText('${v_abstain}', null, $center);
$dr->addCell(1000)->addText('${v_rate}', null, $center);
$dr->addCell(1500)->addText('${v_result}', null, $center);
$section->addTextBreak(1);

// VIII. KẾT LUẬN
$section->addText('VIII. KẾT LUẬN CUỘC HỌP', $h1);
$section->addText('Cuộc họp kết thúc lúc ${gio_ket_thuc} giờ ${phut_ket_thuc} phút, ngày ${ngay_ket_thuc_so} tháng ${thang_ket_thuc} năm ${nam_ket_thuc}.');
$section->addTextBreak(1);
$section->addText('Chủ tọa tổng kết và kết luận cuộc họp như sau:', ['bold' => true]);
$section->addText('${noi_dung_ket_luan}');
$section->addTextBreak(3);

// Chữ ký — 2 cột
$signTable = $section->addTable();
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

// Save
$outDir = __DIR__.'/storage/app';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$outPath = $outDir.'/meeting-minutes-template-filled-v2.docx';
$writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
$writer->save($outPath);

echo "Generated: {$outPath}".PHP_EOL;
