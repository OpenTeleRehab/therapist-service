<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransferResource;
use App\Models\Forwarder;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\Request;
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
        $user = Auth::user();

        $transfers = Transfer::where('from_therapist_id', $user['id'])
            ->orWhere('to_therapist_id', $user['id'])
            ->get();

        return ['success' => true, 'data' => TransferResource::collection($transfers)];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
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
            'from_therapist_id' => $request->get('from_therapist_id'),
            'to_therapist_id' => $request->get('to_therapist_id'),
            'therapist_type' => $request->get('therapist_type'),
            'status' => Transfer::STATUS_INVITED,
        ]);

        return ['success' => true, 'message' => 'success_message.transfer_invited'];
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function accept(Request $request, Transfer $transfer)
    {
        $patientId = $request->get('patient_id');
        $chatRooms = $request->get('chat_rooms');

        $therapist = User::find($transfer->to_therapist_id);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $request->header('country')),
            'country' => $request->header('country'),
        ])->post(env('PATIENT_SERVICE_URL') . '/patient/transfer-to-therapist/' . $patientId, [
            'therapist_id' => $therapist->id,
            'therapist_identity' => $therapist->identity,
            'new_chat_rooms' => [],
            'chat_rooms' => $chatRooms,
            'therapist_type' => $transfer['therapist_type'],
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
     * @param  \Illuminate\Http\Request  $request
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
