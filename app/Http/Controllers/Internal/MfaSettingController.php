<?php

namespace App\Http\Controllers\Internal;

use Illuminate\Http\Request;
use App\Jobs\ApplyMfaSettings;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class MfaSettingController extends Controller
{
    /**
     * Queue a job to update admin attributes in Keycloak.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'role' => 'required|in:therapist,phc_worker',
            'broadcast_channel' => 'required|string',
            'job_id' => 'required|string',
            'row_id' => 'required|integer',
            'is_deleting' => 'required|boolean',
        ]);

        try {
            ApplyMfaSettings::dispatch(
                $validatedData['role'],
                $validatedData['broadcast_channel'],
                $validatedData['job_id'],
                $validatedData['row_id'],
                $validatedData['is_deleting']
            );

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
}
