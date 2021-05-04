<?php

namespace App\Http\Controllers;

use App\Events\NewPatientNotification;
use App\Models\User;
use App\Notifications\NewPatient;
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
}
