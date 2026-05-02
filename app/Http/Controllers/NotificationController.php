<?php

namespace App\Http\Controllers;

use App\Notifications\NewPatient;
use App\Events\NewPatientNotification;
use App\Models\Forwarder;
use App\Models\User;
use App\Notifications\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void|null
     */
    public function pushNotification(Request $request)
    {
        $identity = $request->get('identity');
        $id = $request->get('_id');
        $rid = $request->get('rid');
        $title = $request->get('title');
        $body = $request->get('body');
        $translatable = $request->boolean('translatable');

        if (preg_match('/^PHC[0-9]/', $identity)) {
            $user = User::where('identity', $request->get('identity'))->firstOrFail();

            try {
                $user->notify(new Chat($id, $rid, $title, $body, $translatable));
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }

        if (preg_match('/^P[0-9]/', $identity)) {
            Http::withToken(Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE))->get(env('PATIENT_SERVICE_URL') . '/push-notification', $request->all());
        }
    }

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
