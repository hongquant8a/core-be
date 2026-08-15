<?php

use App\Modules\Beneficiary\Controllers\BeneficiaryTypeController as Controller;
use Illuminate\Support\Facades\Route;

// Route tĩnh phải đứng TRƯỚC /{beneficiaryType}.
Route::get('/stats', [Controller::class, 'stats'])
    ->middleware('permission:beneficiary-types.stats,web');
Route::get('/export', [Controller::class, 'export'])
    ->middleware('permission:beneficiary-types.export,web');
Route::post('/import', [Controller::class, 'import'])
    ->middleware('permission:beneficiary-types.import,web');
Route::get('/import-template', [Controller::class, 'importTemplate'])
    ->middleware('permission:beneficiary-types.import,web');
Route::delete('/bulk-delete', [Controller::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-types.bulkDestroy,web');
Route::patch('/bulk-status', [Controller::class, 'bulkUpdateStatus'])
    ->middleware('permission:beneficiary-types.bulkUpdateStatus,web');
Route::patch('/reorder', [Controller::class, 'reorder'])
    ->middleware('permission:beneficiary-types.update,web');   // dùng chung .update

Route::get('/', [Controller::class, 'index'])
    ->middleware('permission:beneficiary-types.index,web');
Route::post('/', [Controller::class, 'store'])
    ->middleware('permission:beneficiary-types.store,web');

Route::get('/{beneficiaryType}', [Controller::class, 'show'])
    ->whereNumber('beneficiaryType')->middleware('permission:beneficiary-types.show,web');
Route::put('/{beneficiaryType}', [Controller::class, 'update'])
    ->whereNumber('beneficiaryType')->middleware('permission:beneficiary-types.update,web');
Route::delete('/{beneficiaryType}', [Controller::class, 'destroy'])
    ->whereNumber('beneficiaryType')->middleware('permission:beneficiary-types.destroy,web');
Route::patch('/{beneficiaryType}/status', [Controller::class, 'changeStatus'])
    ->whereNumber('beneficiaryType')->middleware('permission:beneficiary-types.changeStatus,web');
