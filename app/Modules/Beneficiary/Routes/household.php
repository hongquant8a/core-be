<?php

use App\Modules\Beneficiary\Controllers\HouseholdController;
use Illuminate\Support\Facades\Route;

/**
 * Household không có cột status (không có vòng đời trạng thái) — không có route
 * bulk-status/{id}/status, khác bộ action chuẩn CLAUDE.md §3 một cách có chủ đích.
 */
Route::get('/stats', [HouseholdController::class, 'stats'])
    ->middleware('permission:beneficiary-households.stats,web');
Route::get('/export', [HouseholdController::class, 'export'])
    ->middleware('permission:beneficiary-households.export,web');
Route::post('/import', [HouseholdController::class, 'import'])
    ->middleware('permission:beneficiary-households.import,web');
Route::delete('/bulk-delete', [HouseholdController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-households.bulkDestroy,web');

Route::get('/', [HouseholdController::class, 'index'])
    ->middleware('permission:beneficiary-households.index,web');
Route::post('/', [HouseholdController::class, 'store'])
    ->middleware('permission:beneficiary-households.store,web');
Route::get('/{household}', [HouseholdController::class, 'show'])
    ->middleware('permission:beneficiary-households.show,web');
Route::put('/{household}', [HouseholdController::class, 'update'])
    ->middleware('permission:beneficiary-households.update,web');
Route::delete('/{household}', [HouseholdController::class, 'destroy'])
    ->middleware('permission:beneficiary-households.destroy,web');
