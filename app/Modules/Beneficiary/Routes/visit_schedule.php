<?php

use App\Modules\Beneficiary\Controllers\VisitScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VisitScheduleController::class, 'index'])
    ->middleware('permission:beneficiary-visit-schedules.index,web');
Route::get('/{visitSchedule}', [VisitScheduleController::class, 'show'])
    ->middleware('permission:beneficiary-visit-schedules.show,web');
Route::patch('/{visitSchedule}/status', [VisitScheduleController::class, 'changeStatus'])
    ->middleware('permission:beneficiary-visit-schedules.changeStatus,web');
