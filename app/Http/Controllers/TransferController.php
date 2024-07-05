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
        ], [
            'patient_id' => $request->get('patient_id'),
            'clinic_id' => $request->get('clinic_id'),
            'from_therapist_id' => $request->get('from_therapist_id'),
            'to_therapist_id' => $request->get('to_therapist_id'),
            'therapist_type' => Transfer::LEAD_THERAPIST,
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
    public function accept(Request $request)
    {
        $patientId = $request->get('patient_id');
        $patientChatUserId = $request->get('patient_chat_user_id');
        $chatRooms = $request->get('chat_rooms');

        $transfer = Transfer::where('patient_id', $patientId)->first();

        $fromTherapist = User::find($transfer->from_therapist_id);
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

            $chatRoom = $fromTherapist->chat_user_id . $patientChatUserId;
            $chatRooms = array_values(array_diff($fromTherapist->chat_rooms, [$chatRoom]));

            $fromTherapist->update(['chat_rooms' => $chatRooms]);

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
    public function decline(int $id)
    {
        Transfer::where('patient_id', $id)->update(['status' => Transfer::STATUS_DECLINED]);

        return ['success' => true, 'message' => 'success_message.transfer_rejected'];
    }
}
