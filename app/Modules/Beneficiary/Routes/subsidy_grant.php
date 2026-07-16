<?php

use App\Modules\Beneficiary\Controllers\SubsidyGrantController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubsidyGrantController::class, 'index'])
    ->middleware('permission:beneficiary-subsidy-grants.index,web');
Route::post('/', [SubsidyGrantController::class, 'store'])
    ->middleware('permission:beneficiary-subsidy-grants.store,web');
Route::patch('/{subsidyGrant}/status', [SubsidyGrantController::class, 'changeStatus'])
    ->middleware('permission:beneficiary-subsidy-grants.changeStatus,web');
