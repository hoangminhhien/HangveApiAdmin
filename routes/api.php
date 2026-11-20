<?php

use App\Http\Controllers\MockController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Models\OrderAdvanceMoney;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['prefix' => 'mock'], function() {
    Route::get('/handle', [MockController::class, 'handle']);
    Route::get('/package/{order}', [MockController::class, 'orderPackage']);
    Route::patch('/package/{id}', [MockController::class, 'orderPackageUpdateStatus']);
    Route::patch('/order/{order}', [MockController::class, 'orderUpdateStatus']);
    Route::patch('/complain/{complain}', [MockController::class, 'complainUpdateStatus']);
    Route::patch('/deposit/{customerId}', [MockController::class, 'depositMoney']);
    Route::patch('/consignment/{id}', [MockController::class, 'consignmentStatus']);
    Route::patch('/withdrawal/{id}', [MockController::class, 'withdrawalStatus']);
});

Route::post('/app/charge-money', [CustomerController::class, 'chargeFromApp']);
Route::post('/app/push-money-9pay-mrm010426', [CustomerController::class, 'pushMoney9Pay']);
Route::post('/app/push-log-9pay-mrm010426', [CustomerController::class, 'pushLog9Pay']);
Route::get('/udpate-14-06', function(){
    // OrderAdvanceMoney::query()->update(['organization_id' =>  1]);
    dd('done');
});
