<?php

namespace App\Http\Controllers;

use App\Models\DownloadTracker;
use App\Models\Forwarder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ExportController extends Controller
{
    public function export(Request $request)
    {
        //TODO: Should improve this to be more generic.
        $user = Auth::user();
        $userId = $user->id;
        $jobId = $userId . now();
        $lang = $request->get('lang', 'en');
        $type = $request->get('type');
        $country = $request->header('country');
        $payload = [
            'job_id' => $jobId,
            'lang' => $lang,
            'type' => $type,
            'therapist_id' => $userId,
            'source' => Forwarder::THERAPIST_SERVICE,
        ];

        $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
        $response = Http::withToken($access_token)->withHeaders([
            'country' => $country
        ])->get(env('PATIENT_SERVICE_URL') . '/export', $payload);

        if ($response->ok()) {
            DownloadTracker::create([
                'type' => $type,
                'job_id' => $jobId,
                'author_id' => $userId,
            ]);
            return ['success' => true, 'message' => 'success_message.export', 'data' => $jobId];
        } else {
            return ['success' => false, 'message' => 'error_message.export'];
        }
    }

}
