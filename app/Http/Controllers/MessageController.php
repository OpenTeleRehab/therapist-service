<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Twilio\Rest\Client;

class MessageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/message",
     *     tags={"Message"},
     *     summary="Lists all messages",
     *     operationId="messageList",
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         description="Patient id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $messages = Message::where('patient_id', $data['patient_id'])
            ->where('therapist_id', Auth::id())
            ->get();
        return  ['success' => true, 'data' => MessageResource::collection($messages)];
    }

    /**
     * @return int
     */
    public function getTherapistMessage() {
        $monday = strtotime('monday this week');
        $sunday = strtotime('sunday this week');
        $startDate = date('Y-m-d', $monday);
        $endDate = date('Y-m-d', $sunday);

        $messages = Message::where('therapist_id', Auth::id())
            ->whereDate('sent_at', '>=', $startDate)
            ->whereDate('sent_at', '<=', $endDate)
            ->count();

        return  ['success' => true, 'data' => $messages];
    }

    /**
     * @OA\Post(
     *     path="/api/message",
     *     tags={"Message"},
     *     summary="Create message",
     *     operationId="createMessage",
     *     @OA\Parameter(
     *         name="patient_id",
     *         in="query",
     *         description="Patient id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="message",
     *         in="query",
     *         description="Message",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="draft",
     *         in="query",
     *         description="Mark message as draft",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void
     */
    public function store(Request $request)
    {
        Message::where('draft', true)
        ->where('patient_id', $request->get('patient_id'))
        ->where('therapist_id', Auth::id())
        ->delete();

        if (!empty($request->get('message'))) {
            $sent = null;
            if ($request->get('draft') === null) {
                $twilio = new Client(env('SMS_SID'), env('SMS_TOKEN'));
                $message = $twilio->messages->create("+" . $request->get('phone'),
                    ["body" => $request->get('message'), "from" => env('SMS_PHONE_NUMBER')]);
                $sent = $message->errorMessage ? null : Carbon::now();
            }

            Message::create([
                'patient_id' => $request->get('patient_id'),
                'therapist_id' => Auth::id(),
                'message' => $request->get('message'),
                'sent_at' => $sent,
                'draft' => $request->get('draft'),
            ]);
        }

        return ['success' => true, 'message' => 'success_message.message_add'];
    }
}
