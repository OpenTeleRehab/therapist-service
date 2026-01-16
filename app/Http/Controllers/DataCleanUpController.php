<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUserDeletion;
use App\Models\User;
use Illuminate\Http\Request;

class DataCleanUpController extends Controller
{
    /**
     * Delete all therapist users belonging to a specific entity.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUsersByEntity(Request $request)
    {
        $validatedData = $request->validate([
            'entity_name' => 'required|in:country,region,province,rehab_service,phc_service',
            'entity_id' => 'required|integer',
        ]);

        $entityName = $validatedData['entity_name'];
        $entityId = $validatedData['entity_id'];

        ProcessUserDeletion::dispatch($entityName, $entityId);

        return response()->json([
            'message' => "Deletion job for therapist users in {$entityName} {$entityId} has been queued."
        ]);
    }

    /**
     * Count all therapist users belonging to a specific entity.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function countUsersByEntity(Request $request)
    {
        $validatedData = $request->validate([
            'entity_name' => 'required|in:country,region,province,rehab_service,phc_service',
            'entity_id' => 'required|integer',
            'user_type' => 'required|in:therapist,phc_worker'
        ]);

        $entityName = $validatedData['entity_name'];
        $entityId = $validatedData['entity_id'];

        $entityColumnMap = [
            'country'       => 'country_id',
            'region'        => 'region_id',
            'province'      => 'province_id',
            'rehab_service' => 'clinic_id',
            'phc_service'   => 'phc_service_id',
        ];

        $column = $entityColumnMap[$entityName];

        $userCount = User::where($column, $entityId)->where('type', $validatedData['user_type'])->count();

        return response()->json(['data' => $userCount]);
    }
}
