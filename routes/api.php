<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TherapistController;
use App\Http\Controllers\UserController;
use \App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TermAndConditionController;
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
Route::get('therapist/by-ids', [TherapistController::class, 'getByIds']);
Route::get('chart/get-data-for-global-admin', [ChartController::class, 'getDataForGlobalAdmin']);
Route::get('chart/get-data-for-country-admin', [ChartController::class, 'getDataForCountryAdmin']);
Route::get('chart/get-data-for-clinic-admin', [ChartController::class, 'getDataForClinicAdmin']);
Route::get('treatment-plan/count/by-therapist', [TreatmentPlanController::class, 'countTherapistTreatmentPlan']);
Route::get('therapist/get-used-profession', [TherapistController::class, 'getUsedProfession']);
Route::post('therapist/new-patient-notification', [NotificationController::class, 'newPatientNotification']);
Route::post('therapist/delete-chat-room/by-id', [TherapistController::class, 'deleteChatRoomById']);
Route::get('therapist/get-patient-limit', [TherapistController::class, 'getTherapistPatientLimit']);
Route::get('activities/list/by-ids', [ActivityController::class, 'getByIds']);

Route::group(['middleware' => 'auth:api'], function () {
    Route::get('treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities']);
    Route::apiResource('treatment-plan', TreatmentPlanController::class);
    Route::get('user/profile', [ProfileController::class, 'getUserProfile']);
    Route::put('user/update-password', [ProfileController::class, 'updatePassword']);
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile']);
    Route::put('user/add-new-chatroom', [ProfileController::class, 'addNewChatRoom']);
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess']);
});

// Todo: apply for Admin, Therapist APPs
Route::apiResource('therapist', TherapistController::class);
Route::post('therapist/updateStatus/{user}', [TherapistController::class, 'updateStatus']);
Route::get('therapist/list/by-clinic-id', [TherapistController::class, 'getByClinicId']);
Route::post('therapist/resend-email/{user}', [TherapistController::class, 'resendEmailToUser']);
Route::get('term-condition/send-re-consent', [TermAndConditionController::class, 'addReConsentTermsOfServicesToUsers']);
Route::post('therapist/delete/by-clinic', [TherapistController::class, 'deleteByClinicId']);
Route::post('therapist/delete/by-id/{user}', [TherapistController::class, 'deleteByUserId']);

Route::get('patient/by-phone-number', [TherapistController::class, 'getPatientByPhoneNumber']);
