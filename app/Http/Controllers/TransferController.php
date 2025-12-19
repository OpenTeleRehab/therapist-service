<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransferResource;
use App\Models\Forwarder;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class TransferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        $transfers = Transfer::where('from_therapist_id', Auth::id())
            ->orWhere('to_therapist_id', Auth::id())
            ->get();

        return ['success' => true, 'data' => TransferResource::collection($transfers)];
    }

    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function retrieve(Request $request)
    {
        $userId = $request->input('user_id');
        $status = $request->input('status');
        $therapist_type = $request->input('therapist_type');

        $transfers = Transfer::where(function ($query) use ($userId) {
                $query->where('from_therapist_id', $userId)
                    ->orWhere('to_therapist_id', $userId);
            })
            ->where('status', $status)
            ->when($therapist_type, function ($query, $therapist_type) {
                $query->where('therapist_type', $therapist_type);
            })
            ->get();

        return ['success' => true, 'data' => TransferResource::collection($transfers)];
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function store(Request $request)
    {
        Transfer::updateOrCreate([
            'patient_id' => $request->get('patient_id'),
            'to_therapist_id' => $request->get('to_therapist_id'),
            'therapist_type' => $request->get('therapist_type'),
        ], [
            'patient_id' => $request->get('patient_id'),
            'clinic_id' => $request->get('clinic_id'),
            'phc_service_id' => $request->get('phc_service_id'),
            'from_therapist_id' => $request->get('from_therapist_id'),
            'to_therapist_id' => $request->get('to_therapist_id'),
            'therapist_type' => $request->get('therapist_type'),
            'status' => Transfer::STATUS_INVITED,
        ]);

        return ['success' => true, 'message' => 'success_message.transfer_invited'];
    }

    /**
     * @param Request $request
     * @param Transfer $transfer
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function accept(Request $request, Transfer $transfer)
    {
        $user = Auth::user();
        $patientId = $request->get('patient_id');
        $toTherapist = $transfer->to_therapist;
        $fromTherapist = $transfer->from_therapist;

        // Validate therapist and patient match
        if ($toTherapist->id !== Auth::id()) {
            return response()->json([
                'error' => 'Unauthorized: You are not assigned as the receiving therapist for this transfer.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($patientId != $transfer->patient_id) {
            return response()->json([
                'error' => 'Invalid patient ID for this transfer.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $request->header('country')),
            'country' => $request->header('country'),
        ])->post(env('PATIENT_SERVICE_URL') . '/patient/transfer-to-therapist/' . $patientId, [
            'therapist_id' => $toTherapist->id,
            'new_chat_rooms' => $toTherapist->chat_rooms ?? [],
            'chat_rooms' => $fromTherapist->chat_rooms ?? [],
            'therapist_type' => $transfer['therapist_type'],
            'auth_user_type' => $user->type,
        ]);

        if ($response->successful()) {
            $transfer->delete();

            return ['success' => true, 'message' => 'success_message.transfer_accepted'];
        }

        return ['success' => true, 'message' => 'success_message.transfer_fail'];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function decline(Transfer $transfer)
    {
        $transfer->update(['status' => Transfer::STATUS_DECLINED]);

        return ['success' => true, 'message' => 'success_message.transfer_rejected'];
    }

    /**
     * @param Transfer $transfer
     * @return array
     */
    public function destroy(Transfer $transfer)
    {
        $transfer->delete();
        return ['success' => true, 'message' => 'success_message.transfer_deleted'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function deleteByPatient(Request $request)
    {
        Transfer::where('patient_id', $request->get('patient_id'))->delete();

        return ['success' => true, 'message' => 'success_message.transfer_deleted'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getNumberOfActiveTransfers(Request $request)
    {
        $therapistId = $request->get('therapist_id');

        $count = Transfer::where('status', Transfer::STATUS_INVITED)
            ->where(function ($query) use ($therapistId) {
                $query->where('from_therapist_id', $therapistId)
                    ->orWhere('to_therapist_id', $therapistId);
            })
            ->count();

        return ['success' => true, 'data' => $count];
    }
}
