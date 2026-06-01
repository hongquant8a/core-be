<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingGuest;
use App\Modules\Meeting\Models\MeetingInvitationTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Str;

/**
 * Sinh file giấy mời .docx từ template + data của cuộc họp và khách mời.
 */
class MeetingInvitationGenerator
{
    /**
     * Danh sách biến scalar để chèn vào template giấy mời.
     */
    public const SCALAR_VARIABLES = [
        'guest_name' => 'Họ tên khách mời',
        'guest_position' => 'Chức vụ khách mời',
        'guest_organization' => 'Đơn vị / Cơ quan khách mời',
        'guest_phone' => 'Số điện thoại khách mời',
        'guest_email' => 'Email khách mời',
        'meeting_title' => 'Tiêu đề cuộc họp',
        'dia_diem_hop' => 'Địa điểm họp',
        'thoi_gian_bat_dau' => 'Thời gian bắt đầu (HH:mm)',
        'thoi_gian_ket_thuc' => 'Thời gian kết thúc (HH:mm)',
        'ngay_hop' => 'Ngày họp (dd/mm/yyyy)',
        'ngay_hop_so' => 'Ngày trong ngày họp (dd)',
        'thang_hop' => 'Tháng (mm)',
        'nam_hop' => 'Năm (yyyy)',
        'chu_toa' => 'Họ tên người chủ trì',
    ];

    /**
     * Build file .docx mẫu chứa toàn bộ placeholder để người dùng tải về tham khảo.
     */
    public function generateSample(): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(13);

        $h1 = ['bold' => true, 'size' => 14];
        $h2 = ['bold' => true, 'size' => 13];
        $italic = ['italic' => true];
        $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $right = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT];

        $phpWord->addTableStyle('HeaderTable', ['borderSize' => 0, 'cellMargin' => 0]);
        $phpWord->addTableStyle('SignatureTable', ['borderSize' => 0, 'cellMargin' => 0]);

        $section = $phpWord->addSection();

        // Header Table (Quốc hiệu & Tên cơ quan)
        $headerTable = $section->addTable('HeaderTable');
        $row = $headerTable->addRow();
        $c1 = $row->addCell(4500);
        $c1->addText('CƠ QUAN / ĐƠN VỊ CHỦ QUẢN', ['bold' => true], $center);
        $c1->addText('................................', ['bold' => true], $center);
        
        $c2 = $row->addCell(5500);
        $c2->addText('CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', ['bold' => true], $center);
        $c2->addText('Độc lập - Tự do - Hạnh phúc', ['bold' => true, 'underline' => 'single'], $center);
        
        $section->addTextBreak(2);

        // Title
        $section->addText('GIẤY MỜI HỌP', ['bold' => true, 'size' => 16], $center);
        $section->addText('V/v tham dự cuộc họp: ${meeting_title}', ['bold' => true], $center);
        $section->addTextBreak(1);

        // Body
        $section->addText('Kính gửi: Ông/Bà ${guest_name} - ${guest_position}', ['bold' => true]);
        $section->addText('Đơn vị: ${guest_organization}');
        $section->addTextBreak(1);
        $section->addText('Ban tổ chức trân trọng kính mời Ông/Bà tham dự cuộc họp với thông tin chi tiết như sau:');
        
        $section->addText('- Nội dung cuộc họp: ${meeting_title}');
        $section->addText('- Thời gian: ${thoi_gian_bat_dau} ngày ${ngay_hop}');
        $section->addText('- Địa điểm: ${dia_diem_hop}');
        $section->addText('- Chủ trì cuộc họp: ${chu_toa}');
        
        $section->addTextBreak(1);
        $section->addText('Rất mong sự có mặt đầy đủ của Ông/Bà để cuộc họp đạt kết quả tốt đẹp.');
        $section->addText('Trân trọng!', $italic);

        $section->addTextBreak(2);

        // Signature Table
        $signTable = $section->addTable('SignatureTable');
        $sr = $signTable->addRow();
        $sr->addCell(5000); // Empty left column
        
        $cSign = $sr->addCell(5000);
        $cSign->addText('..., ngày ${ngay_hop_so} tháng ${thang_hop} năm ${nam_hop}', $italic, $center);
        $cSign->addText('ĐẠI DIỆN BAN TỔ CHỨC', ['bold' => true], $center);
        $cSign->addText('(Ký tên và đóng dấu)', $italic, $center);
        $cSign->addTextBreak(3);
        $cSign->addText('${chu_toa}', ['bold' => true], $center);

        $outPath = storage_path('app/'.uniqid('sample_invitation_').'.docx');
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outPath);

        return $outPath;
    }

    /**
     * Generate file .docx cho 1 guest → trả về path tạm.
     */
    public function generateSingle(Meeting $meeting, MeetingGuest $guest, MeetingInvitationTemplate $template): string
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
        ]);

        $this->fillInvitation($tp, $meeting, $guest);

        $outPath = storage_path('app/'.uniqid('invitation_').'.docx');
        $tp->saveAs($outPath);

        $this->repairDocxTables($outPath);

        return $outPath;
    }

    /**
     * Sinh tất cả giấy mời trong 1 file .docx duy nhất, mỗi khách mời 1 trang
     * (phân cách bằng page break). Không dùng ZIP.
     *
     * Cơ chế: sinh .docx cho từng guest → extract body XML → nối vào 1 document
     * gốc, chèn <w:br w:type="page"/> giữa các bản.
     */
    public function generateBatch(Meeting $meeting, MeetingInvitationTemplate $template): string
    {
        $meeting->loadMissing('guests');
        $guests = $meeting->guests;
        if ($guests->isEmpty()) {
            throw new \Exception('Cuộc họp chưa có khách mời nào để xuất giấy mời.');
        }

        // Sinh riêng từng file docx cho mỗi guest
        $tempFiles = [];
        foreach ($guests as $guest) {
            $tempFiles[] = $this->generateSingle($meeting, $guest, $template);
        }

        // Lấy file đầu tiên làm base
        $basePath = array_shift($tempFiles);

        if (empty($tempFiles)) {
            // Chỉ có 1 khách → trả luôn file đó, không cần merge
            return $basePath;
        }

        // Merge các file còn lại vào base
        $this->mergeDocxFiles($basePath, $tempFiles);

        // Xóa các file tạm
        foreach ($tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return $basePath;
    }

    /**
     * Merge N file .docx phụ vào 1 file .docx base.
     * Mỗi file phụ sẽ bắt đầu ở trang mới (page break).
     */
    private function mergeDocxFiles(string $basePath, array $otherPaths): void
    {
        $baseZip = new \ZipArchive();
        if ($baseZip->open($basePath) !== true) {
            throw new \Exception('Không thể mở file base .docx.');
        }
        $baseXml = $baseZip->getFromName('word/document.xml');
        $baseZip->close();

        if ($baseXml === false) {
            throw new \Exception('File base .docx không có document.xml.');
        }

        $baseDom = new \DOMDocument();
        $baseDom->preserveWhiteSpace = false;
        $baseDom->formatOutput = false;
        if (! @$baseDom->loadXML($baseXml)) {
            throw new \Exception('Không thể parse document.xml của base.');
        }

        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $baseBody = $baseDom->getElementsByTagNameNS($wNs, 'body')->item(0);
        if (! $baseBody) {
            throw new \Exception('Không tìm thấy <w:body> trong base document.');
        }

        // Tách <w:sectPr> cuối body ra để append sau cùng
        $baseSectPr = null;
        $lastChild = $baseBody->lastChild;
        while ($lastChild && $lastChild->nodeType !== XML_ELEMENT_NODE) {
            $lastChild = $lastChild->previousSibling;
        }
        if ($lastChild && $lastChild->localName === 'sectPr') {
            $baseSectPr = $baseBody->removeChild($lastChild);
        }

        foreach ($otherPaths as $otherPath) {
            $otherZip = new \ZipArchive();
            if ($otherZip->open($otherPath) !== true) {
                continue;
            }
            $otherXml = $otherZip->getFromName('word/document.xml');
            $otherZip->close();
            if ($otherXml === false) {
                continue;
            }

            $otherDom = new \DOMDocument();
            $otherDom->preserveWhiteSpace = false;
            $otherDom->formatOutput = false;
            if (! @$otherDom->loadXML($otherXml)) {
                continue;
            }

            $otherBody = $otherDom->getElementsByTagNameNS($wNs, 'body')->item(0);
            if (! $otherBody) {
                continue;
            }

            // Chèn page break trước nội dung của file phụ
            $pageBreakP = $baseDom->createElementNS($wNs, 'w:p');
            $pageBreakR = $baseDom->createElementNS($wNs, 'w:r');
            $pageBreakBr = $baseDom->createElementNS($wNs, 'w:br');
            $pageBreakBr->setAttribute('w:type', 'page');
            $pageBreakR->appendChild($pageBreakBr);
            $pageBreakP->appendChild($pageBreakR);
            $baseBody->appendChild($pageBreakP);

            // Copy tất cả children của <w:body> (trừ <w:sectPr>) sang base
            foreach (iterator_to_array($otherBody->childNodes) as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'sectPr') {
                    continue; // bỏ qua sectPr của file phụ
                }
                $imported = $baseDom->importNode($child, true);
                $baseBody->appendChild($imported);
            }
        }

        // Đặt lại sectPr cuối body
        if ($baseSectPr) {
            $baseBody->appendChild($baseSectPr);
        }

        // Ghi lại document.xml vào base file
        $newXml = $baseDom->saveXML();
        $baseZip = new \ZipArchive();
        if ($baseZip->open($basePath) === true) {
            $baseZip->deleteName('word/document.xml');
            $baseZip->addFromString('word/document.xml', $newXml);
            $baseZip->close();
        }
    }

    private function fillInvitation(TemplateProcessor $tp, Meeting $m, MeetingGuest $g): void
    {
        $start = $m->start_time;
        $end = $m->end_time;

        $scalars = [
            'guest_name' => $this->cleanText($g->name),
            'guest_position' => $this->cleanText($g->position),
            'guest_organization' => $this->cleanText($g->organization),
            'guest_phone' => $this->cleanText($g->phone),
            'guest_email' => $this->cleanText($g->email),
            'meeting_title' => $this->cleanText($m->title),
            'dia_diem_hop' => $this->cleanText($m->meetingLocation?->name),
            'thoi_gian_bat_dau' => $start?->format('H:i') ?? '',
            'thoi_gian_ket_thuc' => $end?->format('H:i') ?? '',
            'ngay_hop' => $start?->format('d/m/Y') ?? '',
            'ngay_hop_so' => $start?->format('d') ?? '',
            'thang_hop' => $start?->format('m') ?? '',
            'nam_hop' => $start?->format('Y') ?? '',
            'chu_toa' => $this->cleanText($m->chairperson?->user?->name ?? $m->chairperson?->name),
        ];

        foreach ($scalars as $key => $val) {
            $tp->setValue($key, $val);
        }
    }

    private function cleanText(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        return trim(html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Fix các lỗi cấu trúc bảng và kích thước trang để tương thích tối đa với Microsoft Word.
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

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        if (@$dom->loadXML($xml)) {
            $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

            // Reorder <w:rPr> children
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

            // Reorder tbl children (tblPr & tblGrid)
            $tbls = $dom->getElementsByTagNameNS($ns, 'tbl');
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

        // Round decimal attributes
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
}
