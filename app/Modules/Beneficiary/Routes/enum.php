<?php

use App\Modules\Beneficiary\Controllers\EnumController;
use Illuminate\Support\Facades\Route;

// Dữ liệu tĩnh dùng chung cho nhiều màn hình/permission khác nhau trong module —
// không gắn permission theo resource cụ thể, chỉ cần đăng nhập (giống general views
// của Scheduling, xem app/Modules/Scheduling/Routes/schedule.php).
Route::get('/', [EnumController::class, 'index']);
