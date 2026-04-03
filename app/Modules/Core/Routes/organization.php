<?php

use App\Modules\Core\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/export', [OrganizationController::class, 'export'])->middleware('permission:organizations.export,web');
Route::post('/import', [OrganizationController::class, 'import'])->middleware('permission:organizations.import,web');
Route::get('/import-template', [OrganizationController::class, 'importTemplate'])->middleware('permission:organizations.import,web');
Route::post('/bulk-delete', [OrganizationController::class, 'bulkDestroy'])->middleware('permission:organizations.bulkDestroy,web');
Route::patch('/bulk-status', [OrganizationController::class, 'bulkUpdateStatus'])->middleware('permission:organizations.bulkUpdateStatus,web');
Route::get('/stats', [OrganizationController::class, 'stats'])->middleware('permission:organizations.stats,web');
Route::get('/tree', [OrganizationController::class, 'tree'])->middleware('permission:organizations.tree,web');
Route::get('/', [OrganizationController::class, 'index'])->middleware('permission:organizations.index,web');
Route::get('/{organization}', [OrganizationController::class, 'show'])->middleware('permission:organizations.show,web');
Route::post('/', [OrganizationController::class, 'store'])->middleware('permission:organizations.store,web');
Route::put('/{organization}', [OrganizationController::class, 'update'])->middleware('permission:organizations.update,web');
Route::patch('/{organization}', [OrganizationController::class, 'update'])->middleware('permission:organizations.update,web');
Route::delete('/{organization}', [OrganizationController::class, 'destroy'])->middleware('permission:organizations.destroy,web');
Route::patch('/{organization}/status', [OrganizationController::class, 'changeStatus'])->middleware('permission:organizations.changeStatus,web');
