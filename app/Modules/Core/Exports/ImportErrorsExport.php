<?php

namespace App\Modules\Core\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

/**
 * Tổng hợp lỗi import thành 1 file Excel để cán bộ tải về, đối chiếu và sửa.
 *
 * Cột: STT | Hàng số (dòng trong file gốc) | Cột (trường lỗi) | Lỗi (thông báo) | Giá trị (ô sai).
 * Mỗi dòng = 1 lỗi; 1 hàng dữ liệu gốc có nhiều trường sai sẽ tạo nhiều dòng.
 *
 * @see \App\Http\Controllers\Controller::importResult()
 */
class ImportErrorsExport extends AbstractExcelExport implements FromArray
{
    /** @param array<int, array{0:int,1:int,2:string,3:string,4:string}> $rows */
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['STT', 'Hàng số', 'Cột', 'Lỗi', 'Giá trị'];
    }
}
