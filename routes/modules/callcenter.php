<?php

use App\Http\Controllers\CallcenterController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Gọi cho khách hàng
    Route::post('/make-call-out',[CallcenterController::class, 'makeCallOut']);
});
