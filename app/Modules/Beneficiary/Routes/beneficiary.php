<?php

use App\Modules\Beneficiary\Controllers\BeneficiaryController;
use Illuminate\Support\Facades\Route;

Route::get('/stats', [BeneficiaryController::class, 'stats'])
    ->middleware('permission:beneficiaries.stats,web');
Route::get('/export', [BeneficiaryController::class, 'export'])
    ->middleware('permission:beneficiaries.export,web');
Route::post('/import', [BeneficiaryController::class, 'import'])
    ->middleware('permission:beneficiaries.import,web');
Route::get('/import-template', [BeneficiaryController::class, 'importTemplate'])
    ->middleware('permission:beneficiaries.import,web');
Route::delete('/bulk-delete', [BeneficiaryController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiaries.bulkDestroy,web');
Route::patch('/bulk-status', [BeneficiaryController::class, 'bulkUpdateStatus'])
    ->middleware('permission:beneficiaries.bulkUpdateStatus,web');

Route::get('/', [BeneficiaryController::class, 'index'])
    ->middleware('permission:beneficiaries.index,web');
Route::post('/', [BeneficiaryController::class, 'store'])
    ->middleware('permission:beneficiaries.store,web');
Route::get('/{beneficiary}', [BeneficiaryController::class, 'show'])
    ->middleware('permission:beneficiaries.show,web');
Route::put('/{beneficiary}', [BeneficiaryController::class, 'update'])
    ->middleware('permission:beneficiaries.update,web');
Route::delete('/{beneficiary}', [BeneficiaryController::class, 'destroy'])
    ->middleware('permission:beneficiaries.destroy,web');
Route::patch('/{beneficiary}/status', [BeneficiaryController::class, 'changeStatus'])
    ->middleware('permission:beneficiaries.changeStatus,web');
Route::get('/{beneficiary}/status-histories', [BeneficiaryController::class, 'statusHistories'])
    ->middleware('permission:beneficiaries.show,web');
