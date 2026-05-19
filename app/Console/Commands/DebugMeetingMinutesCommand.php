<?php

namespace App\Console\Commands;

use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingMinutesTemplate;
use App\Modules\Meeting\Services\MeetingMinutesGenerator;
use DOMDocument;
use Illuminate\Console\Command;
use ZipArchive;

/**
 * Debug command — generate biên bản .docx + dump diagnostic report ra console.
 *
 * Cách dùng:
 *   php artisan meeting:debug-minutes {meeting_id} {template_id}
 *
 * In ra: file path, size, zip parts, document.xml/styles.xml stats, first tbl preview,
 * XML validation errors. Mục đích: copy/paste output gửi BE dev để inspect.
 *
 * KHÔNG xóa file output — user có thể tải về kiểm tra Word.
 */
class DebugMeetingMinutesCommand extends Command
{
    protected $signature = 'meeting:debug-minutes {meeting} {template}';

    protected $description = 'Generate biên bản .docx + dump diagnostic cho debug Word strict';

    public function handle(MeetingMinutesGenerator $gen): int
    {
        $meeting = Meeting::find($this->argument('meeting'));
        $template = MeetingMinutesTemplate::find($this->argument('template'));
        if (! $meeting || ! $template) {
            $this->error('Meeting hoặc Template không tồn tại.');

            return self::FAILURE;
        }

        $out = $gen->generate($meeting, $template);
        $this->line('===== FILE =====');
        $this->line("Path: $out");
        $this->line('Size: '.filesize($out).' bytes');

        $zip = new ZipArchive();
        $zip->open($out);
        $xml = (string) $zip->getFromName('word/document.xml');
        $styles = (string) $zip->getFromName('word/styles.xml');

        $this->line("\n===== ZIP PARTS =====");
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $this->line('  '.$zip->getNameIndex($i));
        }
        $zip->close();

        $this->line("\n===== document.xml STATS =====");
        $this->line('Length: '.strlen($xml));
        $this->line('tbl='.substr_count($xml, '<w:tbl>').' tblPr='.substr_count($xml, '<w:tblPr').' tblGrid='.substr_count($xml, '<w:tblGrid'));

        if (preg_match('/<w:pgSz[^>]+>/', $xml, $m1)) {
            $this->line('pgSz: '.$m1[0]);
        }
        if (preg_match('/<w:pgMar[^>]+>/', $xml, $m2)) {
            $this->line('pgMar: '.$m2[0]);
        }
        preg_match_all('/(\w+:?\w*)="(\d+\.\d+)"/', $xml, $m3);
        $this->line('Decimal attrs: '.count($m3[0]).(count($m3[0]) ? ' ('.implode(', ', array_slice(array_unique($m3[0]), 0, 5)).')' : ''));

        $this->line("\n===== FIRST TBL (400 chars) =====");
        $pos = strpos($xml, '<w:tbl>');
        $this->line($pos !== false ? substr($xml, $pos, 400) : 'no tbl');

        $this->line("\n===== MainTable tblPr (styles.xml) =====");
        $spos = strpos($styles, 'w:styleId="MainTable"');
        if ($spos !== false) {
            $tpStart = strpos($styles, '<w:tblPr>', $spos);
            if ($tpStart !== false) {
                $tpEnd = strpos($styles, '</w:tblPr>', $tpStart);
                $this->line(substr($styles, $tpStart, $tpEnd - $tpStart + 11));
            } else {
                $this->line('no tblPr in MainTable');
            }
        } else {
            $this->line('No MainTable style found');
        }

        $this->line("\n===== XML VALIDATION =====");
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $this->line('document.xml: '.($dom->loadXML($xml) ? 'valid' : 'INVALID'));
        foreach (libxml_get_errors() as $e) {
            $this->line('  '.trim($e->message));
        }
        libxml_clear_errors();
        $this->line('styles.xml: '.($dom->loadXML($styles) ? 'valid' : 'INVALID'));
        foreach (libxml_get_errors() as $e) {
            $this->line('  '.trim($e->message));
        }

        return self::SUCCESS;
    }
}
