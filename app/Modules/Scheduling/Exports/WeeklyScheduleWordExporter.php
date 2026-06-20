<?php

namespace App\Modules\Scheduling\Exports;

use App\Modules\Core\Models\Organization;
use App\Modules\Scheduling\Models\Schedule;
use Carbon\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;

class WeeklyScheduleWordExporter
{
    private static array $daysMap = [
        1 => 'Thứ Hai',
        2 => 'Thứ Ba',
        3 => 'Thứ Tư',
        4 => 'Thứ Năm',
        5 => 'Thứ Sáu',
        6 => 'Thứ Bảy',
        0 => 'Chủ Nhật',
    ];

    public function generate(array $filters): string
    {
        $templatePath = storage_path('app/scheduling/templates/weekly-schedule.docx');

        // Self-generate default template if not exists
        if (!is_file($templatePath)) {
            $this->generateDefaultTemplate($templatePath);
        }

        $tp = new TemplateProcessor($templatePath);

        $query = Schedule::with(['host', 'driver'])
            ->filter($filters)
            ->orderBy('date_time', 'asc')
            ->orderBy('session', 'asc')
            ->orderBy('sort_order', 'asc');

        $schedulesList = $query->get();

        // Calculate date range of the week
        $dateFrom = '';
        $dateTo = '';
        if (!empty($filters['week_number']) && !empty($filters['year'])) {
            $carbon = Carbon::now()->setISODate($filters['year'], $filters['week_number']);
            $dateFrom = $carbon->startOfWeek()->format('d/m/Y');
            $dateTo = $carbon->endOfWeek()->format('d/m/Y');
        }

        // Format schedules
        $formattedSchedules = [];
        foreach ($schedulesList as $item) {
            $dayLabel = 'N/A';
            if ($item->date_time) {
                $dayOfWeek = $item->date_time->dayOfWeek;
                $dayName = self::$daysMap[$dayOfWeek] ?? '';
                $dateStr = $item->date_time->format('d/m/Y');
                $dayLabel = "{$dayName} ({$dateStr})";
            }

            $sessionLabel = 'N/A';
            if ($item->session) {
                $val = is_object($item->session) ? $item->session->value : $item->session;
                if ($val === 'S') $sessionLabel = 'Sáng';
                elseif ($val === 'C') $sessionLabel = 'Chiều';
                elseif ($val === 'T') $sessionLabel = 'Tối';
            }

            $timeLabel = '';
            if ($item->date_time) {
                $timeLabel = $item->date_time->format('H:i');
            }

            $hostName = $item->host ? $item->host->name : ($item->host_text ?? '');

            $formattedSchedules[] = [
                'day' => $dayLabel,
                'session' => $sessionLabel,
                'time' => $timeLabel,
                'content' => $item->content ?? '',
                'host' => $hostName,
                'location' => $item->location ?? '',
                'prep_unit' => $item->preparation_unit ?? '',
            ];
        }

        // Apply Rowspan-like merge logic for Word
        // In Word template processor, since we can't merge cells vertically easily via TemplateProcessor,
        // we can print the Day and Session only for the first row of each group and leave it blank for subsequent rows.
        // This is clean and looks professional!
        $prevDay = null;
        $prevSession = null;
        for ($i = 0; $i < count($formattedSchedules); $i++) {
            $dayVal = $formattedSchedules[$i]['day'];
            $sessionVal = $formattedSchedules[$i]['session'];

            if ($dayVal === $prevDay) {
                $formattedSchedules[$i]['day'] = '';
            } else {
                $prevDay = $dayVal;
            }

            if ($dayVal === '' && $sessionVal === $prevSession) {
                $formattedSchedules[$i]['session'] = '';
            } else {
                $prevSession = $sessionVal;
            }
        }

        // Resolve current organization name
        $orgName = 'Văn Phòng';
        $orgId = function_exists('getPermissionsTeamId') ? getPermissionsTeamId() : null;
        if ($orgId) {
            $org = Organization::find($orgId);
            if ($org) {
                $orgName = $org->name;
            }
        }

        // Set scalar values
        $tp->setValue('organization_name', $orgName);
        $tp->setValue('week_number', $filters['week_number'] ?? '');
        $tp->setValue('year', $filters['year'] ?? '');
        $tp->setValue('date_from', $dateFrom);
        $tp->setValue('date_to', $dateTo);

        // Fill table rows
        if (count($formattedSchedules) === 0) {
            $tp->cloneRow('row.day', 0);
        } else {
            $tp->cloneRow('row.day', count($formattedSchedules));
            foreach ($formattedSchedules as $i => $s) {
                $idx = $i + 1;
                $tp->setValue("row.day#{$idx}", $s['day']);
                $tp->setValue("row.session#{$idx}", $s['session']);
                $tp->setValue("row.time#{$idx}", $s['time']);
                // Clean HTML content tags if any
                $content = trim(html_entity_decode(strip_tags((string) $s['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $tp->setValue("row.content#{$idx}", $content);
                $tp->setValue("row.host#{$idx}", $s['host']);
                $tp->setValue("row.location#{$idx}", $s['location']);
                $tp->setValue("row.prep_unit#{$idx}", $s['prep_unit']);
            }
        }

        $outPath = storage_path('app/' . uniqid('weekly_schedule_') . '.docx');
        $tp->saveAs($outPath);

        $this->repairDocxTables($outPath);

        return $outPath;
    }

    private function generateDefaultTemplate(string $path): void
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(13);

        $h1 = ['bold' => true, 'size' => 14];
        $center = ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER];
        $tableStyle = ['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80];
        $headerRow = ['bgColor' => '1F4E78'];

        $phpWord->addTableStyle('MainTable', $tableStyle);

        $section = $phpWord->addSection(['orientation' => 'landscape']);

        // Header
        $section->addText('${organization_name}', $h1, $center);
        $section->addText('LỊCH CÔNG TÁC TUẦN ${week_number} - NĂM ${year}', $h1, $center);
        $section->addText('(Từ ngày ${date_from} đến ngày ${date_to})', ['italic' => true], $center);
        $section->addTextBreak(1);

        // Table
        $t = $section->addTable('MainTable');
        $hr = $t->addRow();
        
        $thStyle = ['bold' => true, 'color' => 'FFFFFF'];
        $hr->addCell(2000, $headerRow)->addText('Ngày', $thStyle, $center);
        $hr->addCell(1200, $headerRow)->addText('Buổi', $thStyle, $center);
        $hr->addCell(1200, $headerRow)->addText('Giờ', $thStyle, $center);
        $hr->addCell(5000, $headerRow)->addText('Nội dung công tác', $thStyle, $center);
        $hr->addCell(2000, $headerRow)->addText('Chủ trì', $thStyle, $center);
        $hr->addCell(2000, $headerRow)->addText('Địa điểm', $thStyle, $center);
        $hr->addCell(2000, $headerRow)->addText('Đơn vị chuẩn bị', $thStyle, $center);

        $dr = $t->addRow();
        $dr->addCell(2000)->addText('${row.day}');
        $dr->addCell(1200)->addText('${row.session}');
        $dr->addCell(1200)->addText('${row.time}');
        $dr->addCell(5000)->addText('${row.content}');
        $dr->addCell(2000)->addText('${row.host}');
        $dr->addCell(2000)->addText('${row.location}');
        $dr->addCell(2000)->addText('${row.prep_unit}');

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($path);
    }

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
