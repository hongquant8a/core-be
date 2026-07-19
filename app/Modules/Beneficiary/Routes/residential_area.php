<?php

use App\Modules\Beneficiary\Controllers\ResidentialAreaController;
use Illuminate\Support\Facades\Route;

/**
 * ResidentialArea không có cột status (không có vòng đời trạng thái) — không có route
 * bulk-status/{id}/status, khác bộ action chuẩn CLAUDE.md §3 một cách có chủ đích, giống Household.
 */
Route::get('/stats', [ResidentialAreaController::class, 'stats'])
    ->middleware('permission:beneficiary-residential-areas.stats,web');
Route::get('/export', [ResidentialAreaController::class, 'export'])
    ->middleware('permission:beneficiary-residential-areas.export,web');
Route::post('/import', [ResidentialAreaController::class, 'import'])
    ->middleware('permission:beneficiary-residential-areas.import,web');
Route::delete('/bulk-delete', [ResidentialAreaController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-residential-areas.bulkDestroy,web');

Route::get('/', [ResidentialAreaController::class, 'index'])
    ->middleware('permission:beneficiary-residential-areas.index,web');
Route::post('/', [ResidentialAreaController::class, 'store'])
    ->middleware('permission:beneficiary-residential-areas.store,web');
Route::get('/{residentialArea}', [ResidentialAreaController::class, 'show'])
    ->middleware('permission:beneficiary-residential-areas.show,web');
Route::put('/{residentialArea}', [ResidentialAreaController::class, 'update'])
    ->middleware('permission:beneficiary-residential-areas.update,web');
Route::delete('/{residentialArea}', [ResidentialAreaController::class, 'destroy'])
    ->middleware('permission:beneficiary-residential-areas.destroy,web');
