<?php

use App\Modules\Beneficiary\Controllers\SubsidyPolicyController;
use Illuminate\Support\Facades\Route;

Route::get('/stats', [SubsidyPolicyController::class, 'stats'])
    ->middleware('permission:beneficiary-subsidy-policies.stats,web');
Route::get('/export', [SubsidyPolicyController::class, 'export'])
    ->middleware('permission:beneficiary-subsidy-policies.export,web');
Route::post('/import', [SubsidyPolicyController::class, 'import'])
    ->middleware('permission:beneficiary-subsidy-policies.import,web');
Route::get('/import-template', [SubsidyPolicyController::class, 'importTemplate'])
    ->middleware('permission:beneficiary-subsidy-policies.import,web');
Route::delete('/bulk-delete', [SubsidyPolicyController::class, 'bulkDestroy'])
    ->middleware('permission:beneficiary-subsidy-policies.bulkDestroy,web');

Route::get('/', [SubsidyPolicyController::class, 'index'])
    ->middleware('permission:beneficiary-subsidy-policies.index,web');
Route::post('/', [SubsidyPolicyController::class, 'store'])
    ->middleware('permission:beneficiary-subsidy-policies.store,web');
Route::get('/{subsidyPolicy}', [SubsidyPolicyController::class, 'show'])
    ->middleware('permission:beneficiary-subsidy-policies.show,web');
Route::put('/{subsidyPolicy}', [SubsidyPolicyController::class, 'update'])
    ->middleware('permission:beneficiary-subsidy-policies.update,web');
Route::delete('/{subsidyPolicy}', [SubsidyPolicyController::class, 'destroy'])
    ->middleware('permission:beneficiary-subsidy-policies.destroy,web');
Route::post('/{subsidyPolicy}/renew', [SubsidyPolicyController::class, 'renew'])
    ->middleware('permission:beneficiary-subsidy-policies.renew,web');
