<?php

namespace App\Http\Controllers;

use App\Models\JobTracker;
use Illuminate\Http\Request;
use App\Jobs\UpdateFederatedUsersMfaJob;
use App\Jobs\UpdateKeycloakUserAttributes;

class MfaSettingController extends Controller
{
    /**
     * Queue a job to update admin attributes in Keycloak.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'country_ids' => 'nullable|array',
            'clinic_ids' => 'nullable|array',
            'mfa_enforcement' => 'required|in:skip,recommend,force',
            'mfa_expiration_duration' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration' => 'nullable|integer|min:0',
        ]);

        UpdateFederatedUsersMfaJob::dispatchSync($validatedData);

        return response()->json([
            'success' => true
        ]);
    }

    public function jobStatus($jobId)
    {
        $job = JobTracker::where('job_id', $jobId)->firstOrFail();
        return response()->json([
            'status' => $job->status,
            'message' => $job->message,
        ]);
    }
}
