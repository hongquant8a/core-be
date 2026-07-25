<?php

use App\Modules\Beneficiary\Controllers\DependentController;
use Illuminate\Support\Facades\Route;

Route::get('/stats', [DependentController::class, 'stats'])
    ->middleware('permission:beneficiary-dependents.stats,web');
Route::get('/export', [DependentController::class, 'export'])
    ->middleware('permission:beneficiary-dependents.export,web');
Route::post('/import', [DependentController::class, 'import'])
    ->middleware('permission:beneficiary-dependents.import,web');
Route::get('/import-template', [DependentController::class, 'importTemplate'])
    ->middleware('permission:beneficiary-dependents.import,web');
Route::delete('/bulk-delete', [DependentController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-dependents.bulkDestroy,web');

Route::get('/', [DependentController::class, 'index'])
    ->middleware('permission:beneficiary-dependents.index,web');
Route::post('/', [DependentController::class, 'store'])
    ->middleware('permission:beneficiary-dependents.store,web');
Route::get('/{dependent}', [DependentController::class, 'show'])
    ->middleware('permission:beneficiary-dependents.show,web');
Route::put('/{dependent}', [DependentController::class, 'update'])
    ->middleware('permission:beneficiary-dependents.update,web');
Route::delete('/{dependent}', [DependentController::class, 'destroy'])
    ->middleware('permission:beneficiary-dependents.destroy,web');

Route::post('/{dependent}/relations', [DependentController::class, 'storeRelation'])
    ->middleware('permission:beneficiary-dependents.storeRelation,web');
Route::delete('/{dependent}/relations/{relation}', [DependentController::class, 'destroyRelation'])
    ->whereNumber('relation')
    ->middleware('permission:beneficiary-dependents.destroyRelation,web');
