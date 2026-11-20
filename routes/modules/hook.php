<?php

use Illuminate\Support\Facades\Route;
use ThuongDo\Buyder\src\App\Http\Controllers\HookController as ThuongdoHookController;

Route::post('/update-platform-orders-data', [ThuongdoHookController::class, 'updatePlatformOrders']);
