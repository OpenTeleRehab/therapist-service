<?php

namespace App\Http\Controllers;

use App\Models\JobTracker;
use Illuminate\Http\Request;
use App\Jobs\UpdateFederatedUsersMfaJob;

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
            'phc_service_ids' => 'nullable|array',
            'role' => 'required|in:therapist,phc_worker',
            'mfa_enforcement' => 'required|in:skip,recommend,force',
            'mfa_expiration_duration' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration' => 'nullable|integer|min:0',
            'mfa_expiration_unit' => 'nullable|string',
            'skip_mfa_setup_unit' => 'nullable|string',
            'mfa_expiration_duration_in_seconds' => 'nullable|integer|min:0',
            'skip_mfa_setup_duration_in_seconds' => 'nullable|integer|min:0',
        ]);

        try {
            UpdateFederatedUsersMfaJob::dispatchSync($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'MFA update job has been queued successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
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
