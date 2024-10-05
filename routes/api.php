<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TherapistController;
use App\Http\Controllers\ForwarderController;
use \App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TermAndConditionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TransferController;
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
    // Patient
    Route::get('patient/by-phone-number', [TherapistController::class, 'getPatientByPhoneNumber']);
    Route::get('patient/transfer', [TherapistController::class, 'transferPatient']);

    // Term Condition
    Route::get('term-condition/send-re-consent', [TermAndConditionController::class, 'addReConsentTermsOfServicesToUsers']);

    // Therapist
    Route::get('therapist/by-ids', [TherapistController::class, 'getByIds']);
    Route::get('therapist/get-used-profession', [TherapistController::class, 'getUsedProfession']);
    Route::get('therapist/get-patient-limit', [TherapistController::class, 'getTherapistPatientLimit']);
    Route::get('therapist/list/by-clinic-id', [TherapistController::class, 'getByClinicId']);
    Route::get('therapist/get-call-access-token', [TherapistController::class, 'getCallAccessToken']);
    Route::post('therapist/new-patient-notification', [NotificationController::class, 'newPatientNotification']);
    Route::post('therapist/updateStatus/{user}', [TherapistController::class, 'updateStatus']);
    Route::post('therapist/resend-email/{user}', [TherapistController::class, 'resendEmailToUser']);
    Route::post('therapist/delete-chat-room/by-id', [TherapistController::class, 'deleteChatRoomById']);
    Route::post('therapist/delete/by-id/{user}', [TherapistController::class, 'deleteByUserId']);
    Route::post('therapist/delete/by-clinic', [TherapistController::class, 'deleteByClinicId']);
    Route::apiResource('therapist', TherapistController::class);

    // Dashboard
    Route::get('chart/get-data-for-global-admin', [ChartController::class, 'getDataForGlobalAdmin']);
    Route::get('chart/get-data-for-country-admin', [ChartController::class, 'getDataForCountryAdmin']);
    Route::get('chart/get-data-for-clinic-admin', [ChartController::class, 'getDataForClinicAdmin']);

    // Activities
    Route::get('activities/list/by-ids', [ActivityController::class, 'getByIds']);

    // Treatment Plan
    Route::get('treatment-plan/count/by-therapist', [TreatmentPlanController::class, 'countTherapistTreatmentPlan']);
    Route::get('treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities']);
    Route::apiResource('treatment-plan', TreatmentPlanController::class);

    // Message
    Route::get('message/get-therapist-message', [MessageController::class, 'getTherapistMessage']);
    Route::apiResource('message', MessageController::class);

    // User
    Route::get('user/profile', [ProfileController::class, 'getUserProfile']);
    Route::put('user/update-password', [ProfileController::class, 'updatePassword']);
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile']);
    Route::put('user/add-new-chatroom', [ProfileController::class, 'addNewChatRoom']);
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess']);

    // Transfer
    Route::get('transfer/accept/{transfer}', [TransferController::class, 'accept']);
    Route::get('transfer/decline/{transfer}', [TransferController::class, 'decline']);
    Route::apiResource('transfer', TransferController::class);
    Route::delete('transfer/delete/by-patient', [TransferController::class, 'deleteByPatient']);
    Route::get('transfer/number/by-therapist', [TransferController::class, 'getNumberOfActiveTransfers']);

    // Global Resource
    Route::name('global_admin.')->group(function () {
        Route::apiResource('category', ForwarderController::class);
        Route::apiResource('category-tree', ForwarderController::class);
    });

    // Admin Service
    Route::name('admin.')->group(function () {
        Route::get('library/count/by-therapist', [ForwarderController::class, 'index']);
        Route::get('country', [ForwarderController::class, 'index']);
        Route::get('country/list/defined-country', [ForwarderController::class, 'index']);
        Route::get('clinic', [ForwarderController::class, 'index']);
        Route::get('disease', [ForwarderController::class, 'index']);
        Route::get('profession', [ForwarderController::class, 'index']);
        Route::get('settings', [ForwarderController::class, 'index']);
        Route::get('guidance-page', [ForwarderController::class, 'index']);

        // Exercises
        Route::post('exercise/suggest', [ForwarderController::class, 'store']);
        Route::get('exercise/list/by-ids', [ForwarderController::class, 'index']);
        Route::post('exercise/updateFavorite/by-therapist/{id}', [ForwarderController::class, 'store']);
        Route::apiResource('exercise', ForwarderController::class);

        // Education Materials
        Route::post('education-material/suggest', [ForwarderController::class, 'store']);
        Route::get('education-material/list/by-ids', [ForwarderController::class, 'index']);
        Route::post('education-material/updateFavorite/by-therapist/{id}', [ForwarderController::class, 'store']);
        Route::apiResource('education-material', ForwarderController::class);

        // Questionnaires
        Route::post('questionnaire/suggest', [ForwarderController::class, 'store']);
        Route::get('questionnaire/list/by-ids', [ForwarderController::class, 'index']);
        Route::post('questionnaire/updateFavorite/by-therapist/{id}', [ForwarderController::class, 'store']);
        Route::apiResource('questionnaire', ForwarderController::class);

        // Assistive Technologies
        Route::get('assistive-technologies/list/get-all', [ForwarderController::class, 'index']);
    });

    // Patient Service
    Route::name('patient.')->group(function () {
        Route::get('patient/list/by-therapist-id', [ForwarderController::class, 'index']);
        Route::get('patient/list/by-ids', [ForwarderController::class, 'index']);
        Route::get('patient/id/{id}', [ForwarderController::class, 'index']);
        Route::post('patient/activateDeactivateAccount/{id}', [ForwarderController::class, 'store']);
        Route::post('patient/deleteAccount/{id}', [ForwarderController::class, 'store']);
        Route::post('patient/delete-chat-room/by-id', [ForwarderController::class, 'store']);
        Route::apiResource('patient', ForwarderController::class);

        Route::post('appointment/updateStatus/{id}', [ForwarderController::class, 'store']);
        Route::apiResource('appointment', ForwarderController::class);

        Route::get('patient-activities/list/by-ids', [ForwarderController::class, 'index']);
        Route::get('patient-treatment-plan/get-treatment-plan-detail', [ForwarderController::class, 'index']);
        Route::apiResource('patient-treatment-plan', ForwarderController::class);

        Route::apiResource('patient-assistive-technologies', ForwarderController::class);

        Route::get('treatment-plan/export/{id}', [ForwarderController::class, 'index']);

        Route::get('push-notification', [ForwarderController::class, 'index']);
    });
});
