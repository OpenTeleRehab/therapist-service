<?php

use App\Http\Controllers\TherapistController;
use App\Http\Controllers\UserController;
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

Route::apiResource('therapist', TherapistController::class);
Route::get('user/profile/{username}', [UserController::class, 'getUserProfile']);
Route::put('user/update-password/{username}', [UserController::class, 'updatePassword']);
