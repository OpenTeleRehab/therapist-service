<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TherapistController;
use App\Http\Controllers\UserController;
use \App\Http\Controllers\TreatmentPlanController;
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

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities']);
    Route::apiResource('treatment-plan', TreatmentPlanController::class);
    Route::get('user/profile', [ProfileController::class, 'getUserProfile']);
    Route::put('user/update-password', [ProfileController::class, 'updatePassword']);
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile']);
    Route::put('user/add-new-chatroom', [ProfileController::class, 'addNewChatRoom']);
});

// Todo: apply for Admin, Therapist APPs
Route::apiResource('therapist', TherapistController::class);
Route::post('therapist/updateStatus/{user}', [TherapistController::class, 'updateStatus']);
