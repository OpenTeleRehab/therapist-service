<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateKeycloakUserAttributes;
use App\Models\JobTracker;
use Illuminate\Http\Request;

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
        $data = $request->validate([
            'country_ids' => 'sometimes|array|nullable',
            'clinic_ids' => 'sometimes|array|nullable',
            'attributes' => 'required|array',
        ]);

        $jobId = uniqid('therapist_');

        JobTracker::updateOrCreate(
            ['job_id' => $jobId],
            ['status' => JobTracker::PENDING]
        );

        UpdateKeycloakUserAttributes::dispatch($data, $jobId);

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
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
