<?php

use App\Modules\Beneficiary\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

// Dashboard thống kê — read-only, dùng chung 1 permission `beneficiary-statistics.view`.
Route::middleware('permission:beneficiary-statistics.view,web')->group(function () {
    Route::get('/overview', [StatisticsController::class, 'overview']);
    Route::get('/by-type', [StatisticsController::class, 'byType']);
    Route::get('/by-status', [StatisticsController::class, 'byStatus']);
    Route::get('/by-residential-area', [StatisticsController::class, 'byResidentialArea']);
    Route::get('/households-by-area', [StatisticsController::class, 'householdsByArea']);
    Route::get('/by-gender', [StatisticsController::class, 'byGender']);
    Route::get('/by-age-group', [StatisticsController::class, 'byAgeGroup']);
    Route::get('/by-relationship', [StatisticsController::class, 'byRelationship']);
    Route::get('/new-by-month', [StatisticsController::class, 'newByMonth']);
});
