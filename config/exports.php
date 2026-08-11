<?php

/**
 * Đăng ký các export Excel có thể tải qua signed URL (không cần Authorization
 * header) — dùng cho Zalo Mini App vì zmp-sdk downloadFile()/openWebview() chỉ
 * nhận 1 URL, không đính kèm được header.
 *
 * Thêm 1 export mới = thêm đúng 1 phần tử vào mảng bên dưới, TÁI DÙNG NGUYÊN
 * action export() hiện có (không viết controller/route mới cho từng export).
 * Cơ chế chung nằm ở App\Modules\Core\ExportLinkController + routes/api.php
 * ('/exports/{type}/link' và '/exports/{type}').
 *
 * - controller/action: action export hiện có, phải trả về response chứa file
 *   (BinaryFileResponse của Excel::download(), v.v).
 * - permission: quyền Spatie cần có để lấy link (check thủ công trong
 *   ExportLinkController vì {type} là tham số động, không gắn được middleware
 *   'permission:...' tĩnh theo route).
 */

return [

    'task-assignment-items' => [
        'controller' => \App\Modules\TaskAssignment\Controllers\TaskAssignmentItemController::class,
        'action' => 'export',
        'permission' => 'task-assignment-items.export',
    ],

    'task-assignment-documents' => [
        'controller' => \App\Modules\TaskAssignment\Controllers\TaskAssignmentDocumentController::class,
        'action' => 'export',
        'permission' => 'task-assignment-documents.export',
    ],

    'task-assignment-petitions' => [
        'controller' => \App\Modules\TaskAssignment\Controllers\TaskAssignmentPetitionController::class,
        'action' => 'export',
        'permission' => 'task-assignment-petitions.export',
    ],

];
