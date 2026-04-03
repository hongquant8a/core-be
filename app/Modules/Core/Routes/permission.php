<?php

use App\Modules\Core\PermissionController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [PermissionController::class, 'export'])->middleware('permission:permissions.export,web');
Route::post('/import', [PermissionController::class, 'import'])->middleware('permission:permissions.import,web');
Route::get('/import-template', [PermissionController::class, 'importTemplate'])->middleware('permission:permissions.import,web');
Route::post('/bulk-delete', [PermissionController::class, 'bulkDestroy'])->middleware('permission:permissions.bulkDestroy,web');
Route::get('/stats', [PermissionController::class, 'stats'])->middleware('permission:permissions.stats,web');
Route::get('/tree', [PermissionController::class, 'tree'])->middleware('permission:permissions.tree,web');
Route::get('/', [PermissionController::class, 'index'])->middleware('permission:permissions.index,web');
Route::get('/{permission}', [PermissionController::class, 'show'])->middleware('permission:permissions.show,web');
Route::post('/', [PermissionController::class, 'store'])->middleware('permission:permissions.store,web');
Route::put('/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update,web');
Route::patch('/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update,web');
Route::delete('/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.destroy,web');
