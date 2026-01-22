<?php

namespace App\Http\Controllers;

use App\Events\NewPatientNotification;
use App\Models\User;
use App\Notifications\NewPatient;
use App\Notifications\PatientCounterReferral;
use App\Notifications\PatientReferral;
use App\Notifications\PatientReferralAssignment;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function newPatientNotification(Request $request)
    {
        foreach ($request->get('therapist_ids') as $therapistId) {
            $therapist = User::find($therapistId);
            $patientFirstName = $request->get('patient_first_name');
            $patientLastName = $request->get('patient_last_name');
            if ($therapist) {
                $therapist->notify(new NewPatient($patientFirstName, $patientLastName));
                event(new NewPatientNotification($therapistId, $patientFirstName, $patientLastName));
            }
        }

        return ['success' => true];
    }

    public function patientReferral(Request $request)
    {
        $request->validate([
            'phc_worker_id' => 'required|exists:users,id',
            'status' => 'required|string|in:invited,accepted,declined',
        ]);

        $user = User::find($request->integer('phc_worker_id'));

        $user->notify(new PatientReferral());
    }

    public function patientReferralAssignment(Request $request)
    {
        $request->validate([
            'therapist_id' => 'nullable|exists:users,id',
            'phc_worker_id' => 'nullable|exists:users,id',
            'status' => 'required|string|in:invited,accepted,declined',
        ]);

        $therapistId = $request->integer('therapist_id');
        $phcWorkerId = $request->integer('phc_worker_id');
        $status = $request->get('status');

        if ($therapistId) {
            User::find($therapistId)->notify(new PatientReferralAssignment($status));
        }

        if ($phcWorkerId) {
            User::find($phcWorkerId)->notify(new PatientReferralAssignment($status));
        }
    }

    public function patientCounterReferral(Request $request)
    {
        $request->validate([
            'phc_worker_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->integer('phc_worker_id'));

        $user->notify(new PatientCounterReferral());
    }
}
