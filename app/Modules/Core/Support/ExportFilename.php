<?php

namespace App\Modules\Core\Support;

/**
 * Helper sinh filename Excel chuẩn dự án.
 *
 * Convention: `export__{slug}_HH-mm-ss_dd-MM-yyyy.xlsx`
 *  - Prefix `export__` cố định để FE/user nhận biết file xuất từ hệ thống.
 *  - Slug tiếng Việt không dấu, lowercase, dash-separated (vd thao-luan-hop).
 *  - Timestamp giờ-phút-giây_ngày-tháng-năm (theo giờ máy chủ via now()).
 *  - Đuôi .xlsx.
 *
 * Dùng:
 *   ExportFilename::make('thao-luan-hop')
 *   → "export__thao-luan-hop_15-30-45_16-05-2026.xlsx"
 *
 * Mục đích timestamp: tránh đè file khi user tải nhiều lần + audit dễ.
 */
class ExportFilename
{
    /**
     * Sinh filename Excel theo convention dự án.
     *
     * @param  string  $slug  Slug tiếng Việt không dấu (vd: thao-luan-hop, dai-bieu-hop)
     * @param  string  $extension  Phần đuôi không có dấu chấm (default xlsx)
     */
    public static function make(string $slug, string $extension = 'xlsx'): string
    {
        $timestamp = now()->format('H-i-s_d-m-Y');

        return "export__{$slug}_{$timestamp}.{$extension}";
    }
}
