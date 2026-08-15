<?php

use App\Modules\Beneficiary\Controllers\BeneficiaryResidentialAreaController as Controller;
use Illuminate\Support\Facades\Route;

// Route tĩnh phải đứng TRƯỚC /{residentialArea}.
Route::get('/stats', [Controller::class, 'stats'])
    ->middleware('permission:beneficiary-residential-areas.stats,web');
Route::get('/export', [Controller::class, 'export'])
    ->middleware('permission:beneficiary-residential-areas.export,web');
Route::post('/import', [Controller::class, 'import'])
    ->middleware('permission:beneficiary-residential-areas.import,web');
Route::get('/import-template', [Controller::class, 'importTemplate'])
    ->middleware('permission:beneficiary-residential-areas.import,web');
Route::delete('/bulk-delete', [Controller::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-residential-areas.bulkDestroy,web');
Route::patch('/bulk-status', [Controller::class, 'bulkUpdateStatus'])
    ->middleware('permission:beneficiary-residential-areas.bulkUpdateStatus,web');
Route::patch('/reorder', [Controller::class, 'reorder'])
    ->middleware('permission:beneficiary-residential-areas.update,web');   // dùng chung .update

Route::get('/', [Controller::class, 'index'])
    ->middleware('permission:beneficiary-residential-areas.index,web');
Route::post('/', [Controller::class, 'store'])
    ->middleware('permission:beneficiary-residential-areas.store,web');

Route::get('/{residentialArea}', [Controller::class, 'show'])
    ->whereNumber('residentialArea')->middleware('permission:beneficiary-residential-areas.show,web');
Route::put('/{residentialArea}', [Controller::class, 'update'])
    ->whereNumber('residentialArea')->middleware('permission:beneficiary-residential-areas.update,web');
Route::delete('/{residentialArea}', [Controller::class, 'destroy'])
    ->whereNumber('residentialArea')->middleware('permission:beneficiary-residential-areas.destroy,web');
Route::patch('/{residentialArea}/status', [Controller::class, 'changeStatus'])
    ->whereNumber('residentialArea')->middleware('permission:beneficiary-residential-areas.changeStatus,web');
