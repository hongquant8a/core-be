<?php

use App\Modules\Beneficiary\Controllers\EnumController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EnumController::class, 'index']);
