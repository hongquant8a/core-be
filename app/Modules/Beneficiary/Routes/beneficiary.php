<?php

use App\Modules\Beneficiary\Controllers\BeneficiaryController;
use App\Modules\Beneficiary\Controllers\BeneficiaryDependentController;
use App\Modules\Beneficiary\Controllers\BeneficiaryDocumentController;
use App\Modules\Beneficiary\Controllers\BeneficiaryTypeRelationController;
use Illuminate\Support\Facades\Route;

// THỨ TỰ QUAN TRỌNG: mọi route tĩnh phải đứng TRƯỚC /{beneficiary}.
// Đặt sau thì POST /api/beneficiaries/save-full khớp vào /{beneficiary} với
// {beneficiary}="save-full" → model binding hỏng → 404 không giải thích được.
Route::get('/stats', [BeneficiaryController::class, 'stats'])
    ->middleware('permission:beneficiaries.stats,web');
Route::get('/export', [BeneficiaryController::class, 'export'])
    ->middleware('permission:beneficiaries.export,web');
Route::post('/import', [BeneficiaryController::class, 'import'])
    ->middleware('permission:beneficiaries.import,web');
Route::get('/import-template', [BeneficiaryController::class, 'importTemplate'])
    ->middleware('permission:beneficiaries.import,web');   // dùng chung permission .import
Route::delete('/bulk-delete', [BeneficiaryController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiaries.bulkDestroy,web');

// KHÔNG có /bulk-status: bảng chính không có cột trạng thái (CLAUDE.md B3 sau khi nới).

// save-full dùng CHUNG permission .store/.update của bản chính — không tạo permission
// riêng (cùng lý lẽ với import-template).
Route::post('/save-full', [BeneficiaryController::class, 'saveFull'])
    ->middleware('permission:beneficiaries.store,web');

Route::get('/', [BeneficiaryController::class, 'index'])
    ->middleware('permission:beneficiaries.index,web');
Route::post('/', [BeneficiaryController::class, 'store'])
    ->middleware('permission:beneficiaries.store,web');

// scopeBindings(): {typeRelation} bắt buộc thuộc về {beneficiary} — Laravel tự chặn IDOR,
// không cần kiểm beneficiary_id thủ công trong controller.
Route::scopeBindings()->group(function () {

    // --- Bản chính theo ID -------------------------------------------------
    Route::get('/{beneficiary}', [BeneficiaryController::class, 'show'])
        ->whereNumber('beneficiary')->middleware('permission:beneficiaries.show,web');
    Route::post('/{beneficiary}', [BeneficiaryController::class, 'update'])          // _method=PUT
        ->whereNumber('beneficiary')->middleware('permission:beneficiaries.update,web');
    Route::put('/{beneficiary}', [BeneficiaryController::class, 'update'])
        ->whereNumber('beneficiary')->middleware('permission:beneficiaries.update,web');
    Route::delete('/{beneficiary}', [BeneficiaryController::class, 'destroy'])
        ->whereNumber('beneficiary')->middleware('permission:beneficiaries.destroy,web');
    Route::post('/{beneficiary}/save-full', [BeneficiaryController::class, 'saveFull'])
        ->whereNumber('beneficiary')->middleware('permission:beneficiaries.update,web');

    // --- Đối tượng: dạng D (n–n có thuộc tính, có tệp) ----------------------
    Route::prefix('/{beneficiary}/type-relations')->whereNumber('beneficiary')->group(function () {
        Route::delete('/bulk-delete', [BeneficiaryTypeRelationController::class, 'bulkDestroy'])
            ->middleware('permission:beneficiary-type-relations.bulkDestroy,web');
        Route::get('/', [BeneficiaryTypeRelationController::class, 'index'])
            ->middleware('permission:beneficiary-type-relations.index,web');
        Route::post('/', [BeneficiaryTypeRelationController::class, 'store'])
            ->middleware('permission:beneficiary-type-relations.store,web');
        Route::get('/{typeRelation}', [BeneficiaryTypeRelationController::class, 'show'])
            ->whereNumber('typeRelation')->middleware('permission:beneficiary-type-relations.show,web');
        Route::post('/{typeRelation}', [BeneficiaryTypeRelationController::class, 'update'])   // _method=PUT
            ->whereNumber('typeRelation')->middleware('permission:beneficiary-type-relations.update,web');
        Route::put('/{typeRelation}', [BeneficiaryTypeRelationController::class, 'update'])
            ->whereNumber('typeRelation')->middleware('permission:beneficiary-type-relations.update,web');
        Route::delete('/{typeRelation}', [BeneficiaryTypeRelationController::class, 'destroy'])
            ->whereNumber('typeRelation')->middleware('permission:beneficiary-type-relations.destroy,web');
    });

    // --- Thân nhân: dạng B (1–n không tệp) → PUT thẳng được -----------------
    Route::prefix('/{beneficiary}/dependents')->whereNumber('beneficiary')->group(function () {
        Route::delete('/bulk-delete', [BeneficiaryDependentController::class, 'bulkDestroy'])
            ->middleware('permission:beneficiary-dependents.bulkDestroy,web');
        Route::get('/', [BeneficiaryDependentController::class, 'index'])
            ->middleware('permission:beneficiary-dependents.index,web');
        Route::post('/', [BeneficiaryDependentController::class, 'store'])
            ->middleware('permission:beneficiary-dependents.store,web');
        Route::get('/{dependent}', [BeneficiaryDependentController::class, 'show'])
            ->whereNumber('dependent')->middleware('permission:beneficiary-dependents.show,web');
        Route::put('/{dependent}', [BeneficiaryDependentController::class, 'update'])
            ->whereNumber('dependent')->middleware('permission:beneficiary-dependents.update,web');
        Route::delete('/{dependent}', [BeneficiaryDependentController::class, 'destroy'])
            ->whereNumber('dependent')->middleware('permission:beneficiary-dependents.destroy,web');
    });

    // --- Tài liệu: dạng A (1–n có tệp) -------------------------------------
    Route::prefix('/{beneficiary}/documents')->whereNumber('beneficiary')->group(function () {
        Route::delete('/bulk-delete', [BeneficiaryDocumentController::class, 'bulkDestroy'])
            ->middleware('permission:beneficiary-documents.bulkDestroy,web');
        Route::get('/', [BeneficiaryDocumentController::class, 'index'])
            ->middleware('permission:beneficiary-documents.index,web');
        Route::post('/', [BeneficiaryDocumentController::class, 'store'])
            ->middleware('permission:beneficiary-documents.store,web');
        Route::get('/{document}', [BeneficiaryDocumentController::class, 'show'])
            ->whereNumber('document')->middleware('permission:beneficiary-documents.show,web');
        Route::post('/{document}', [BeneficiaryDocumentController::class, 'update'])   // _method=PUT
            ->whereNumber('document')->middleware('permission:beneficiary-documents.update,web');
        Route::put('/{document}', [BeneficiaryDocumentController::class, 'update'])
            ->whereNumber('document')->middleware('permission:beneficiary-documents.update,web');
        Route::delete('/{document}', [BeneficiaryDocumentController::class, 'destroy'])
            ->whereNumber('document')->middleware('permission:beneficiary-documents.destroy,web');
    });
});
