<?php

use App\Modules\Auth\AuthController;
use App\Modules\Auth\SsoController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,10');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,10');
Route::post('/request-account', [AuthController::class, 'requestAccount'])->middleware('throttle:5,10');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/switch-organization', [AuthController::class, 'switchOrganization'])->middleware('auth:sanctum');

Route::prefix('sso')->group(function () {
    Route::post('exchange', [SsoController::class, 'exchange']);
    Route::post('cbccvc/login', [SsoController::class, 'cbccvcLogin']);
});
