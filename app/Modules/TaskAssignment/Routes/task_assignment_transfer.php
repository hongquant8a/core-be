<?php

use App\Modules\TaskAssignment\Controllers\TaskAssignmentTransferController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TaskAssignmentTransferController::class, 'index'])->middleware('permission:task-assignment-item-transfers.index|my-assigned-tasks.transfer|my-received-tasks.transfer,web');
Route::post('/', [TaskAssignmentTransferController::class, 'store'])->middleware('permission:task-assignment-item-transfers.store|my-assigned-tasks.transfer|my-received-tasks.transfer,web');
