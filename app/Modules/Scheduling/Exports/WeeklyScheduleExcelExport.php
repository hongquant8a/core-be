<?php

namespace App\Modules\Scheduling\Exports;

use App\Modules\Core\Exports\AbstractExcelExport;
use App\Modules\Scheduling\Enums\SessionTypeEnum;
use App\Modules\Scheduling\Models\Schedule;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class WeeklyScheduleExcelExport extends AbstractExcelExport implements FromCollection
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

    public function __construct(private array $filters = []) {}

    public function collection()
    {
        $query = Schedule::with(['host', 'driver'])
            ->filter($this->filters)
            ->orderBy('date_time', 'asc')
            ->orderBy('session', 'asc')
            ->orderBy('sort_order', 'asc');

        return $query->get()
            ->values()
            ->map(function ($item, $i) {
                // Vietnamese Day representation
                $dayLabel = 'N/A';
                if ($item->date_time) {
                    $dayOfWeek = $item->date_time->dayOfWeek;
                    $dayName = self::$daysMap[$dayOfWeek] ?? '';
                    $dateStr = $item->date_time->format('d/m/Y');
                    $dayLabel = "{$dayName} ({$dateStr})";
                }

                // Session representation
                $sessionLabel = 'N/A';
                if ($item->session) {
                    $val = is_object($item->session) ? $item->session->value : $item->session;
                    if ($val === 'S') $sessionLabel = 'Sáng';
                    elseif ($val === 'C') $sessionLabel = 'Chiều';
                    elseif ($val === 'T') $sessionLabel = 'Tối';
                }

                // Time representation
                $timeLabel = '';
                if ($item->date_time) {
                    $timeLabel = $item->date_time->format('H:i');
                }

                // Host
                $hostName = $item->host ? $item->host->name : ($item->host_text ?? '');

                // Nature (Ghi chú)
                $natureLabel = '';
                if ($item->nature) {
                    $val = is_object($item->nature) ? $item->nature->value : $item->nature;
                    $natureLabel = ($val === 'HOST') ? 'Chủ trì' : 'Tham dự';
                }

                // Driver
                $driverName = $item->driver ? $item->driver->name : ($item->driver_text ?? '');

                return [
                    'day' => $dayLabel,
                    'session' => $sessionLabel,
                    'time' => $timeLabel,
                    'content' => $item->content ?? '',
                    'host' => $hostName,
                    'location' => $item->location ?? '',
                    'prep_unit' => $item->preparation_unit ?? '',
                    'driver' => $driverName,
                    'notes' => $natureLabel,
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Ngày',
            'Buổi',
            'Giờ',
            'Nội dung công tác',
            'Chủ trì',
            'Địa điểm',
            'Đơn vị chuẩn bị',
            'Lái xe',
            'Ghi chú',
        ];
    }

    protected function customEvents(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $lastRow = $sheet->getHighestRow();

        if ($lastRow < 2) {
            return;
        }

        // Merge cells in Column A (Ngày)
        $startRowA = 2;
        $prevValA = $sheet->getCell("A2")->getValue();

        for ($row = 3; $row <= $lastRow; $row++) {
            $currValA = $sheet->getCell("A{$row}")->getValue();
            if ($currValA !== $prevValA) {
                if ($row - 1 > $startRowA) {
                    $sheet->mergeCells("A{$startRowA}:A" . ($row - 1));
                }
                $startRowA = $row;
                $prevValA = $currValA;
            }
        }
        if ($lastRow > $startRowA) {
            $sheet->mergeCells("A{$startRowA}:A{$lastRow}");
        }

        // Merge cells in Column B (Buổi)
        $startRowB = 2;
        $prevDayVal = $sheet->getCell("A2")->getValue();
        $prevSessionVal = $sheet->getCell("B2")->getValue();

        for ($row = 3; $row <= $lastRow; $row++) {
            $currDayVal = $sheet->getCell("A{$row}")->getValue();
            $currSessionVal = $sheet->getCell("B{$row}")->getValue();

            if ($currDayVal !== $prevDayVal || $currSessionVal !== $prevSessionVal) {
                if ($row - 1 > $startRowB) {
                    $sheet->mergeCells("B{$startRowB}:B" . ($row - 1));
                }
                $startRowB = $row;
                $prevDayVal = $currDayVal;
                $prevSessionVal = $currSessionVal;
            }
        }
        if ($lastRow > $startRowB) {
            $sheet->mergeCells("B{$startRowB}:B{$lastRow}");
        }

        // Style alignment for merged cells (align center and vertically center)
        $sheet->getStyle("A2:B{$lastRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
}
