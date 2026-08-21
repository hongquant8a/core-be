<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentTransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskAssignmentTransferController::class, 'index'])->middleware('permission:my-assigned-tasks.transfer|my-received-tasks.transfer,web');
Route::post('/', [TaskAssignmentTransferController::class, 'store'])->middleware('permission:my-assigned-tasks.transfer|my-received-tasks.transfer,web');
