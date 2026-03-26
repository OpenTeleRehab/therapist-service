<?php

use App\Http\Controllers\Internal\UserController;
use Illuminate\Support\Facades\Route;

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

Route::group(['middleware' => ['auth:api', 'role:access_all', 'verify.data.access']], function () {
    Route::get('user/by-ids', [UserController::class, 'getByIds']);
    Route::get('user/by-name', [UserController::class, 'getByName']);
    Route::get('user/by-type', [UserController::class, 'getByType']);
});
