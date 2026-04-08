<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\RemoveKcUserAttributeByEntity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    /**
     * Get users by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $ids = $request->get('ids', []);
        $users = User::whereIn('id', $ids)->select('id', 'first_name', 'last_name', 'type')->get();

        return ['success' => true, 'data' => $users];
    }

    /**
     * Get users by name.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByName(Request $request)
    {
        $name = $request->get('name');
        $users = $users = User::where('first_name', 'like', '%' . $name . '%')
            ->orWhere('last_name', 'like', '%' . $name . '%')
            ->get();

        return ['success' => true, 'data' => $users];
    }

    /**
     * Get users by type.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByType(Request $request)
    {
        $type = $request->get('type');
        $users = User::where('type', $type)->get();

        return ['success' => true, 'data' => $users];
    }

    /**
     * Remove Keycloak user attribute by provided entity
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Illuminate\Http\JsonResponse
     */
    public function removeKcUserAttributeByEntity(Request $request)
    {
        $validatedData = $request->validate([
            'role' => 'required|in:therapist,phc_worker',
            'entity_name' => 'required|in:clinic,phc_service,organization,country,region',
            'entity_ids' => 'array',
        ]);

        $entity = new \stdClass();
        $entity->ids = $validatedData['entity_ids'] ?? null;
        $entity->name = $validatedData['entity_name'];

        RemoveKcUserAttributeByEntity::dispatch($validatedData['role'], $entity);

        return response()->json(['message' => 'The job to remove Keycloak user attributes for the specified entity has been queued successfully.']);
    }
}
