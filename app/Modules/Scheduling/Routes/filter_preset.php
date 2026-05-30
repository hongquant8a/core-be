<?php

use App\Modules\Scheduling\Controllers\FilterPresetController;
use Illuminate\Support\Facades\Route;

Route::apiResource('filter-presets', FilterPresetController::class);
