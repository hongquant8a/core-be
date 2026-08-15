<?php

use App\Modules\Beneficiary\Controllers\BeneficiaryRelationshipController as Controller;
use Illuminate\Support\Facades\Route;

// Route tĩnh phải đứng TRƯỚC /{relationship}.
Route::get('/stats', [Controller::class, 'stats'])
    ->middleware('permission:beneficiary-relationships.stats,web');
Route::get('/export', [Controller::class, 'export'])
    ->middleware('permission:beneficiary-relationships.export,web');
Route::post('/import', [Controller::class, 'import'])
    ->middleware('permission:beneficiary-relationships.import,web');
Route::get('/import-template', [Controller::class, 'importTemplate'])
    ->middleware('permission:beneficiary-relationships.import,web');
Route::delete('/bulk-delete', [Controller::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-relationships.bulkDestroy,web');
Route::patch('/bulk-status', [Controller::class, 'bulkUpdateStatus'])
    ->middleware('permission:beneficiary-relationships.bulkUpdateStatus,web');
Route::patch('/reorder', [Controller::class, 'reorder'])
    ->middleware('permission:beneficiary-relationships.update,web');   // dùng chung .update

Route::get('/', [Controller::class, 'index'])
    ->middleware('permission:beneficiary-relationships.index,web');
Route::post('/', [Controller::class, 'store'])
    ->middleware('permission:beneficiary-relationships.store,web');

Route::get('/{relationship}', [Controller::class, 'show'])
    ->whereNumber('relationship')->middleware('permission:beneficiary-relationships.show,web');
Route::put('/{relationship}', [Controller::class, 'update'])
    ->whereNumber('relationship')->middleware('permission:beneficiary-relationships.update,web');
Route::delete('/{relationship}', [Controller::class, 'destroy'])
    ->whereNumber('relationship')->middleware('permission:beneficiary-relationships.destroy,web');
Route::patch('/{relationship}/status', [Controller::class, 'changeStatus'])
    ->whereNumber('relationship')->middleware('permission:beneficiary-relationships.changeStatus,web');
