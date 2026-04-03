<?php

use App\Modules\Core\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [RoleController::class, 'export'])->middleware('permission:roles.export,web');
Route::post('/import', [RoleController::class, 'import'])->middleware('permission:roles.import,web');
Route::get('/import-template', [RoleController::class, 'importTemplate'])->middleware('permission:roles.import,web');
Route::post('/bulk-delete', [RoleController::class, 'bulkDestroy'])->middleware('permission:roles.bulkDestroy,web');
Route::get('/stats', [RoleController::class, 'stats'])->middleware('permission:roles.stats,web');
Route::get('/', [RoleController::class, 'index'])->middleware('permission:roles.index,web');
Route::get('/{role}', [RoleController::class, 'show'])->middleware('permission:roles.show,web');
Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.store,web');
Route::put('/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update,web');
Route::patch('/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update,web');
Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.destroy,web');
