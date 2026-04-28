<?php

use App\Modules\Core\LogActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [LogActivityController::class, 'export'])->middleware('permission:log-activities.export,web');
Route::get('/stats', [LogActivityController::class, 'stats'])->middleware('permission:log-activities.stats,web');
Route::get('/stats/timeline', [LogActivityController::class, 'timeline'])->middleware('permission:log-activities.stats,web');
Route::get('/stats/top-users', [LogActivityController::class, 'topUsers'])->middleware('permission:log-activities.stats,web');
Route::get('/stats/top-organizations', [LogActivityController::class, 'topOrganizations'])->middleware('permission:log-activities.stats,web');
Route::post('/delete-by-date', [LogActivityController::class, 'destroyByDate'])->middleware('permission:log-activities.destroyByDate,web');
Route::post('/clear', [LogActivityController::class, 'destroyAll'])->middleware('permission:log-activities.destroyAll,web');
Route::delete('/bulk-delete', [LogActivityController::class, 'bulkDestroy'])->middleware('permission:log-activities.bulkDestroy,web');
Route::get('/', [LogActivityController::class, 'index'])->middleware('permission:log-activities.index,web');
Route::get('/{logActivity}', [LogActivityController::class, 'show'])->middleware('permission:log-activities.show,web');
Route::delete('/{logActivity}', [LogActivityController::class, 'destroy'])->middleware('permission:log-activities.destroy,web');
