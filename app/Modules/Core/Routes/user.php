<?php

use App\Modules\Core\UserController;
use App\Modules\Core\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [UserController::class, 'export'])->middleware('permission:users.export,web');
Route::post('/import', [UserController::class, 'import'])->middleware('permission:users.import,web');
Route::get('/import-template', [UserController::class, 'importTemplate'])->middleware('permission:users.import,web');
Route::delete('/bulk-delete', [UserController::class, 'bulkDestroy'])->middleware('permission:users.bulkDestroy,web');
Route::patch('/bulk-status', [UserController::class, 'bulkUpdateStatus'])->middleware('permission:users.bulkUpdateStatus,web');
Route::get('/stats', [UserController::class, 'stats'])->middleware('permission:users.stats,web');
Route::get('/stats/by-organization', [UserController::class, 'statsByOrganization'])->middleware('permission:users.stats,web');
Route::get('/', [UserController::class, 'index'])->middleware('permission:users.index,web');
Route::get('/{user}', [UserController::class, 'show'])->middleware('permission:users.show,web');
Route::post('/', [UserController::class, 'store'])->middleware('permission:users.store,web');
Route::put('/{user}', [UserController::class, 'update'])->middleware('permission:users.update,web');
Route::patch('/{user}', [UserController::class, 'update'])->middleware('permission:users.update,web');
Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('permission:users.destroy,web');
Route::patch('/{user}/status', [UserController::class, 'changeStatus'])->middleware('permission:users.changeStatus,web');
Route::get('/{user}/profile', [UserProfileController::class, 'show'])->middleware('permission:users.show,web');
Route::put('/{user}/profile', [UserProfileController::class, 'update'])->middleware('permission:users.update,web');
