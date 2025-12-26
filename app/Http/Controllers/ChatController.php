<?php

namespace App\Http\Controllers;

use App\Helpers\RocketChatHelper;
use App\Http\Resources\UserChatResource;
use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function getTherapists(Request $request)
    {
        $user = Auth::user();
        $query = User::where('enabled', true);

        // Therapist
        if ($user->type === User::TYPE_THERAPIST) {
            if (empty($user->clinic_id)) {
                return ['success' => true, 'data' => []];
            }

            $query->where('clinic_id', $user->clinic_id)->whereNot('identity', $user->identity);

            return [
                'success' => true,
                'data' => UserChatResource::collection($query->get()),
            ];
        }

        // PHC worker
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $request->header('country')),
            'country' => $request->header('country'),
        ])->get(env('PATIENT_SERVICE_URL') . '/patient/therapist-ids/by-phc-worker-id/' . $user->id);

        if (!$response->successful()) {
            return ['success' => true, 'data' => []];
        }

        $phcUserIds = data_get($response->json(), 'data', []);
        $query->whereIn('id', $phcUserIds);

        return [
            'success' => true,
            'data' => UserChatResource::collection($query->get()),
        ];
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function getPhcWorkers(Request $request)
    {
        $user = Auth::user();
        $query = User::where('enabled', true);

        // PHC worker
        if ($user->type === User::TYPE_PHC_WORKER) {
            if (empty($user->phc_service_id)) {
                return ['success' => true, 'data' => []];
            }

            $query->where('phc_service_id', $user->phc_service_id)->whereNot('identity', $user->identity);

            return [
                'success' => true,
                'data' => UserChatResource::collection($query->get()),
            ];
        }

        // Therapist
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $request->header('country')),
            'country' => $request->header('country'),
        ])->get(env('PATIENT_SERVICE_URL') . '/patient/phc-worker-ids/by-therapist-id/' . $user->id);

        if (!$response->successful()) {
            return ['success' => true, 'data' => []];
        }

        $phcUserIds = data_get($response->json(), 'data', []);
        $query->whereIn('id', $phcUserIds);

        return [
            'success' => true,
            'data' => UserChatResource::collection($query->get()),
        ];
    }

    /**
     * @param Request $request
     * @return array|true[]
     */
    public function createRoomForUsers(Request $request)
    {
        $validatedData = $request->validate([
            'therapist_id'   => 'required|exists:users,id',
            'phc_worker_id' => 'required|exists:users,id',
        ]);

        $therapistIdentity = User::findOrFail($validatedData['therapist_id'])->identity;
        $phcWorkerIdentity = User::findOrFail($validatedData['phc_worker_id'])->identity;

        try {
            RocketChatHelper::createChatRoom($therapistIdentity, $phcWorkerIdentity);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
