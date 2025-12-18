<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DownloadTrackerController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupersetController;
use App\Http\Controllers\TherapistController;
use App\Http\Controllers\ForwarderController;
use \App\Http\Controllers\TreatmentPlanController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TermAndConditionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuditLogController as TherapistAuditLogController;
use App\Http\Controllers\MfaSettingController;
use App\Http\Controllers\PhcWorkerController;

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

Route::group(['middleware' => ['auth:api', 'verify.data.access']], function () {
    // Patient
    Route::get('patient/by-phone-number', [TherapistController::class, 'getPatientByPhoneNumber'])->middleware('role:view_patient');
    Route::get('patient/therapist-by-ids', [TherapistController::class, 'getPatientTherapistByIds'])->middleware('role:access_all');
    Route::get('patient/phc-worker-by-ids', [PhcWorkerController::class, 'getPatientPhcWorkerByIds'])->middleware('role:access_all');

    // Term Condition
    Route::get('term-condition/send-re-consent', [TermAndConditionController::class, 'addReConsentTermsOfServicesToUsers'])->middleware('role:access_all');

    // Therapist
    Route::get('therapist/by-ids', [TherapistController::class, 'getByIds'])->middleware('role:access_all');
    Route::get('therapist/by-id', [TherapistController::class, 'getById'])->middleware('role:access_all');
    Route::get('therapist/get-used-profession', [TherapistController::class, 'getUsedProfession'])->middleware('role:access_all');
    Route::get('therapist/get-patient-limit', [TherapistController::class, 'getTherapistPatientLimit'])->middleware('role:access_all');
    Route::get('therapist/list/by-clinic-id', [TherapistController::class, 'getByClinicId'])->middleware('role:view_clinic_therapist');
    Route::get('therapist/list/by-country-id', [TherapistController::class, 'getByCountryId'])->middleware('role:view_country_therapist');
    Route::get('therapist/get-call-access-token', [TherapistController::class, 'getCallAccessToken'])->middleware('role:get_call_token');
    Route::post('therapist/new-patient-notification', [NotificationController::class, 'newPatientNotification'])->middleware('role:push_patient_notification');
    Route::post('therapist/updateStatus/{user}', [TherapistController::class, 'updateStatus'])->middleware('role:access_all');
    Route::post('therapist/resend-email/{user}', [TherapistController::class, 'resendEmailToUser'])->middleware('role:access_all');
    Route::post('therapist/delete-chat-room/by-id', [TherapistController::class, 'deleteChatRoomById'])->middleware('role:delete_chat_room');
    Route::post('therapist/delete/by-id/{user}', [TherapistController::class, 'deleteByUserId'])->middleware('role:access_all');
    Route::post('therapist/delete/by-clinic', [TherapistController::class, 'deleteByClinicId'])->middleware('role:access_all');
    Route::get('therapist/list-for-chatroom', [TherapistController::class, 'listForChatroom'])->middleware('role:view_clinic_therapist');
    Route::apiResource('therapist', TherapistController::class)->middleware('role:access_all');
    Route::get('therapist/option/list', [TherapistController::class, 'getUserOptionList'])->middleware('role:view_clinic_therapist,view_phc_service_phc_worker');

    // PHC Worker
    Route::get('phc-workers', [PhcWorkerController::class, 'index'])->middleware('role:access_all');
    Route::get('phc-workers/count-by-phc-service', [PhcWorkerController::class, 'countPhcWorkerByPhcService'])->middleware('role:access_all');
    Route::post('phc-workers', [PhcWorkerController::class, 'store'])->middleware('role:access_all');
    Route::put('phc-workers/{phcWorker}', [PhcWorkerController::class, 'update'])->middleware('role:access_all');
    Route::post('phc-workers/delete/by-id/{user}', [PhcWorkerController::class, 'deleteByUserId'])->middleware('role:access_all');
    Route::post('phc-workers/updateStatus/{user}', [PhcWorkerController::class, 'updateStatus'])->middleware('role:access_all');
    Route::post('phc-workers/resend-email/{user}', [PhcWorkerController::class, 'resendEmailToUser'])->middleware('role:access_all');
    Route::get('phc-workers/list/by-phc-service', [PhcWorkerController::class, 'getByPhcServiceId'])->middleware('role:view_phc_service_phc_worker');
    Route::get('phc-workers/list-for-chatroom', [PhcWorkerController::class, 'listForChatroom'])->middleware('role:view_phc_service_phc_worker');
    Route::post('phc-workers/delete-chat-room/by-id', [PhcWorkerController::class, 'deleteChatRoomById'])->middleware('role:delete_chat_room');
    Route::get('phc-workers/all', [PhcWorkerController::class, 'getAll'])->middleware('role:access_all');

    // Dashboard
    Route::get('chart/get-data-for-global-admin', [ChartController::class, 'getDataForGlobalAdmin']); // deprecated
    Route::get('chart/get-data-for-country-admin', [ChartController::class, 'getDataForCountryAdmin']); // deprecated
    Route::get('chart/get-data-for-clinic-admin', [ChartController::class, 'getDataForClinicAdmin']); // deprecated

    // Activities
    Route::get('activities/list/by-ids', [ActivityController::class, 'getByIds'])->middleware('role:view_activity_list');

    // Treatment Plan
    Route::get('treatment-plan/count/by-therapist', [TreatmentPlanController::class, 'countTherapistTreatmentPlan'])->middleware('role:manage_treatment_plan');
    Route::get('treatment-plan/get-treatment-plan-detail', [TreatmentPlanController::class, 'getActivities'])->middleware('role:manage_treatment_plan');
    Route::apiResource('treatment-plan', TreatmentPlanController::class)->middleware('role:manage_treatment_plan');

    // Message
    Route::get('message/get-therapist-message', [MessageController::class, 'getTherapistMessage'])->middleware('role:manage_message');
    Route::apiResource('message', MessageController::class)->middleware('role:manage_message');

    // User
    Route::get('user/profile', [ProfileController::class, 'getUserProfile'])->middleware('role:manage_own_profile');
    Route::put('user/update-password', [ProfileController::class, 'updatePassword'])->middleware('role:manage_own_profile');
    Route::put('user/update-information', [ProfileController::class, 'updateUserProfile'])->middleware('role:manage_own_profile');
    Route::put('user/add-new-chatroom', [ProfileController::class, 'addNewChatRoom'])->middleware('role:manage_own_profile');
    Route::put('user/update-last-access', [ProfileController::class, 'updateLastAccess'])->middleware('role:manage_own_profile');

    // Transfer
    Route::get('transfer/accept/{transfer}', [TransferController::class, 'accept'])->middleware('role:manage_transfer');
    Route::get('transfer/decline/{transfer}', [TransferController::class, 'decline'])->middleware('role:manage_transfer');
    Route::get('transfer/retrieve', [TransferController::class, 'retrieve'])->middleware('role:manage_transfer');
    Route::apiResource('transfer', TransferController::class)->middleware('role:manage_transfer');
    Route::delete('transfer/delete/by-patient', [TransferController::class, 'deleteByPatient'])->middleware('role:manage_transfer');
    Route::get('transfer/number/by-therapist', [TransferController::class, 'getNumberOfActiveTransfers'])->middleware('role:access_all');

    // Mfa Settings
    Route::get('mfa-settings/{jobId}', [MfaSettingController::class, 'jobStatus'])->middleware('role:access_all');
    Route::post('mfa-settings', [MfaSettingController::class, 'store'])->middleware('role:access_all');

    // Global Resource
    Route::name('global_admin.')->group(function () {
        Route::apiResource('category', ForwarderController::class)->middleware('role:manage_category');
        Route::apiResource('category-tree', ForwarderController::class)->middleware('role:manage_category');
        Route::post('survey/skip', [ForwarderController::class, 'store'])->middleware('role:manage_survey');
        Route::post('survey/submit', [ForwarderController::class, 'store'])->middleware('role:manage_survey');
    });

    // Admin Service
    Route::name('admin.')->group(function () {
        Route::get('library/count/by-therapist', [ForwarderController::class, 'index'])->middleware('role:view_therapist_library');
        Route::get('country', [ForwarderController::class, 'index'])->middleware('role:view_therapist_library');
        Route::get('country/list/defined-country', [ForwarderController::class, 'index'])->middleware('role:view_country');
        Route::get('clinic', [ForwarderController::class, 'index'])->middleware('role:view_country');
        Route::get('disease', [ForwarderController::class, 'index'])->middleware('role:view_disease');
        Route::get('profession', [ForwarderController::class, 'index'])->middleware('role:view_profession');
        Route::get('settings', [ForwarderController::class, 'index'])->middleware('role:view_setting');
        Route::get('guidance-page', [ForwarderController::class, 'index'])->middleware('role:view_guidance_page');
        Route::get('health-condition-group', [ForwarderController::class, 'index'])->middleware('role:manage_health_condition,view_health_condition');
        Route::get('health-condition', [ForwarderController::class, 'index'])->middleware('role:manage_health_condition,view_health_condition');

        // Exercises
        Route::post('exercise/suggest', [ForwarderController::class, 'store'])->middleware('role:manage_exercise');
        Route::get('exercise/list/by-ids', [ForwarderController::class, 'index'])->middleware('role:anage_exercise');
        Route::post('exercise/updateFavorite/by-therapist/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_exercise');
        Route::apiResource('exercise', ForwarderController::class)->middleware('role:manage_exercise');

        // Education Materials
        Route::post('education-material/suggest', [ForwarderController::class, 'store'])->middleware('role:manage_education_material');
        Route::get('education-material/list/by-ids', [ForwarderController::class, 'index'])->middleware('role:manage_education_material');
        Route::post('education-material/updateFavorite/by-therapist/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_education_material');
        Route::apiResource('education-material', ForwarderController::class)->middleware('role:manage_education_material');

        // Questionnaires
        Route::post('questionnaire/suggest', [ForwarderController::class, 'store'])->middleware('role:manage_questionnaire');
        Route::get('questionnaire/list/by-ids', [ForwarderController::class, 'index'])->middleware('role:manage_questionnaire');
        Route::post('questionnaire/updateFavorite/by-therapist/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_questionnaire');
        Route::apiResource('questionnaire', ForwarderController::class)->middleware('role:manage_questionnaire');

        // Assistive Technologies
        Route::get('assistive-technologies/list/get-all', [ForwarderController::class, 'index'])->middleware('role:view_assistive_technology');

        // Regions
        Route::get('regions', [ForwarderController::class, 'index'])->middleware('role:view_region_list');

        // Province
        Route::get('provinces-by-user-country', [ForwarderController::class, 'index'])->middleware('role:view_country_provinces');
        Route::get('provinces', [ForwarderController::class, 'index'])->middleware('role:view_province_list');

        // PHC Services
        Route::get('phc-services', [ForwarderController::class, 'index'])->middleware('role:view_phc_service_list');
    });

    // Patient Service
    Route::name('patient.')->group(function () {
        Route::get('patient/list/by-therapist-id', [ForwarderController::class, 'index'])->middleware('role:manage_patient');
        Route::get('patient/list/by-ids', [ForwarderController::class, 'index'])->middleware('role:manage_patient');
        Route::get('patient/id/{id}', [ForwarderController::class, 'index'])->middleware('role:manage_patient');
        Route::post('patient/activateDeactivateAccount/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_patient');
        Route::post('patient/deleteAccount/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_patient');
        Route::post('patient/delete-chat-room/by-id', [ForwarderController::class, 'store'])->middleware('role:manage_patient');
        Route::apiResource('patient', ForwarderController::class)->middleware('role:manage_patient');
        Route::get('patient/list-for-chatroom', [ForwarderController::class, 'index'])->middleware('role:view_patient');

        Route::post('appointment/updateStatus/{id}', [ForwarderController::class, 'store'])->middleware('role:manage_appointment');
        Route::apiResource('appointment', ForwarderController::class)->middleware('role:manage_appointment');

        Route::get('patient-activities/list/by-ids', [ForwarderController::class, 'index'])->middleware('role:view_patient_activity');
        Route::get('patient-treatment-plan/get-treatment-plan-detail', [ForwarderController::class, 'index'])->middleware('role:manage_patient_treatment_plan');
        Route::apiResource('patient-treatment-plan', ForwarderController::class)->middleware('role:manage_patient_treatment_plan');

        Route::apiResource('patient-assistive-technologies', ForwarderController::class)->middleware('role:manage_patient_assistive_technology');

        Route::get('treatment-plan/export/{id}', [ForwarderController::class, 'index'])->middleware('role:export_treatment_plan');

        Route::get('push-notification', [ForwarderController::class, 'index'])->middleware('role:view_notification');

        Route::get('download-trackers', [DownloadTrackerController::class, 'index'])->middleware('role:manage_download_tracker');
        Route::put('download-trackers', [DownloadTrackerController::class, 'updateProgress'])->middleware('role:manage_download_tracker');
        Route::delete('download-trackers', [DownloadTrackerController::class, 'destroy'])->middleware('role:manage_download_tracker');

        Route::get('export', [ExportController::class, 'export'])->middleware('role:export');

        Route::get('download-file', [ForwarderController::class, 'index'])->middleware('role:download_file');

        // Referrals
        Route::apiResource('patient-referrals', ForwarderController::class)->middleware('role:manage_patient_referral');

        Route::get('patient-referral-assignments/count', [ForwarderController::class, 'show'])->middleware('role:manage_patient_referral_assignment');
        Route::put('patient-referral-assignments/{id}/accept', [ForwarderController::class, 'update'])->middleware('role:manage_patient_referral_assignment');
        Route::put('patient-referral-assignments/{id}/decline', [ForwarderController::class, 'update'])->middleware('role:manage_patient_referral_assignment');
        Route::apiResource('patient-referral-assignments', ForwarderController::class)->middleware('role:manage_patient_referral_assignment');
    });

    // Superset
    Route::get('/superset-guest-token', [SupersetController::class, 'index'])->middleware('role:view_dashboard');
});

// Authentication
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

// Audit logs
Route::post('/audit-logs', [TherapistAuditLogController::class, 'store']);
