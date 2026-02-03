<?php

namespace App\Http\Controllers;

use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Notifications\Appointment as AppointmentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Forwarder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/appointment",
     *     tags={"Appointment"},
     *     summary="Appointment list",
     *     operationId="appointmentList",
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Date",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="now",
     *         in="query",
     *         description="Now",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date"
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
        $date = date_create_from_format(config('settings.date_format'), $request->get('date'));
        $now = $request->get('now');
        $authUser = Auth::user();
        $userId = $authUser->id;

        $appointments = Appointment::where(function ($query) use ($userId) {
            $query->where(function ($q) use ($userId) {
                $q->where('requester_id', $userId)
                ->whereNotIn('requester_status', [
                    Appointment::STATUS_CANCELLED,
                    Appointment::STATUS_INVITED,
                ])
                ->where('recipient_status', '!=', Appointment::STATUS_CANCELLED);
            })
            ->orWhere(function ($q) use ($userId) {
                $q->where('recipient_id', $userId)
                ->where('recipient_status', '!=', Appointment::STATUS_INVITED);
            });
        });

        $newAppointments = Appointment::where('recipient_id', $userId)
            ->where('recipient_status', Appointment::STATUS_INVITED)
            ->where('requester_status', '!=', Appointment::STATUS_CANCELLED);

        $unreadAppointments = Appointment::where('requester_id', $userId)
            ->whereIn('recipient_status', [Appointment::STATUS_REJECTED, Appointment::STATUS_ACCEPTED])
            ->where('unread', true)
            ->get();

        $calendarData = Appointment::where('requester_status', '!=', Appointment::STATUS_CANCELLED)
            ->where(function ($query) use ($userId) {
                $query->where('requester_id', $userId)
                ->orWhere('recipient_id', $userId);
            })
            ->whereYear('start_date', $date->format('Y'))
            ->whereMonth('start_date', $date->format('m'))
            ->get();

        // Count the number of pending appointment requests and the number of unread appointment.
        $upComingAppointments = Appointment::where(function ($query) use ($now, $userId) {
            $query->where('recipient_id', $userId)
                ->where('end_date', '>=', $now)
                ->where('recipient_status', Appointment::STATUS_INVITED)
                ->where('requester_status', '!=', Appointment::STATUS_CANCELLED);
        })->orWhere(function ($query) use ($userId) {
            $query->where('requester_id', $userId)
                ->whereIn('recipient_status', [Appointment::STATUS_REJECTED, Appointment::STATUS_ACCEPTED])
                ->where('unread', true);
        })->count();

        if ($request->get('selected_from_date')) {
            $selectedFromDate = date_create_from_format('Y-m-d H:i:s', $request->get('selected_from_date'));
            $selectedToDate = date_create_from_format('Y-m-d H:i:s', $request->get('selected_to_date'));

            $appointments->where('start_date', '>=', $selectedFromDate)
                ->where('start_date', '<=', $selectedToDate);

            $newAppointments->where('start_date', '>=', $selectedFromDate)
            ->where('start_date', '<=', $selectedToDate);
        } else {
            $appointments->where('end_date', '>', $now);
            $newAppointments->where('end_date', '>', $now);
        }

        $data = [
            'approves' => AppointmentResource::collection($appointments->orderBy('start_date')->get()),
            'newAppointments' => AppointmentResource::collection($newAppointments->orderBy('start_date')->get()),
            'unreadAppointments' => AppointmentResource::collection($unreadAppointments),
            'calendarData' => $calendarData,
            'upcomingAppointments' => $upComingAppointments
        ];
        return ['success' => true, 'data' => $data];
    }

    /**
     * @OA\Post(
     *     path="/api/appointment",
     *     tags={"Appointment"},
     *     summary="Create appointment",
     *     operationId="createAppointment",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="From",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="To",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="recipient_id",
     *         in="query",
     *         description="Recipient id",
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
    public function store(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'from' => 'required|string',
            'to' => 'required|string',
            'type' => 'required|in:online,in_person',
        ]);
        $startDate = date_create_from_format('Y-m-d H:i:s', $request->get('from'));
        $endDate = date_create_from_format('Y-m-d H:i:s', $request->get('to'));
        $requesterId = Auth::user()->id;
        $recipientId = $request->get('recipient_id');

        // Check if overlap with any appointment.
        $overlap = $this->validateOverlap($startDate, $endDate, $requesterId, $recipientId, $request->header('country'));
        if ($overlap) {
            return ['success' => false, 'message' => 'error_message.appointment_overlap'];
        }

        Appointment::create([
            'requester_id' => $requesterId,
            'recipient_id' => $recipientId,
            'requester_status' => Appointment::STATUS_ACCEPTED,
            'recipient_status' => Appointment::STATUS_INVITED,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => $request->get('note'),
            'type' => $request->get('type'),
        ]);

        return ['success' => true, 'message' => 'success_message.appointment_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/appointment/{id}",
     *     tags={"Appointment"},
     *     summary="Update appointment",
     *     operationId="updateAppointment",
     *     @OA\Parameter(
     *         name="from",
     *         in="query",
     *         description="From",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="to",
     *         in="query",
     *         description="To",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="date-time(yyyy-mm-dd hh:mm:ss)"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
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
     * @param \App\Models\Appointment $appointment
     *
     * @return array
     */
    public function update(Request $request, Appointment $appointment)
    {
        $request->validate([
            'from' => 'required|string',
            'to' => 'required|string',
            'type' => 'required|in:online,in_person',
        ]);

        $startDate = date_create_from_format('Y-m-d H:i:s', $request->get('from'));
        $endDate = date_create_from_format('Y-m-d H:i:s', $request->get('to'));

        // Check if overlap with any appointment.
        $overlap = $this->validateOverlap($startDate, $endDate, $appointment->requester_id, $appointment->recipient_id,$request->header('country'), $appointment->id);
        if ($overlap) {
            return ['success' => false, 'message' => 'error_message.appointment_overlap'];
        }

        $updateFile = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'note' => $request->get('note'),
            'type' => $request->get('type'),
        ];

        // Update recipient status if appointment data changed.
        if ($startDate != $appointment->start_date || $endDate != $appointment->end_date) {
            $updateFile['recipient_status'] = Appointment::STATUS_INVITED;
        }
        $appointment->update($updateFile);

        try {
            $appointment->recipient->notify(new AppointmentNotification($appointment, Appointment::STATUS_UPDATED));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return ['success' => true, 'message' => 'success_message.appointment_update'];
    }

    /**
     * @param \Illuminate\Support\Facades\Date $startDate
     * @param \Illuminate\Support\Facades\Date $endDate
     * @param integer $requesterId
     * @param integer $recipientId
     * @param integer|null $appointmentId
     *
     * @return integer
     */
    private function validateOverlap($startDate, $endDate, $requesterId, $recipientId, $country, $appointmentId = null)
    {
        $overlap = Appointment::where(function ($query) use ($requesterId, $recipientId) {
            // Case 1: requesterId as requester
            $query->where(function ($q) use ($requesterId) {
                $q->where('requester_id', $requesterId)
                ->where('requester_status', '!=', Appointment::STATUS_CANCELLED);
            })
            // Case 2: requesterId as recipient
            ->orWhere(function ($q) use ($requesterId) {
                $q->where('recipient_id', $requesterId)
                ->where('recipient_status', '!=', Appointment::STATUS_REJECTED);
            })
            // Case 3: recipientId as requester
            ->orWhere(function ($q) use ($recipientId) {
                $q->where('requester_id', $recipientId)
                ->where('requester_status', '!=', Appointment::STATUS_CANCELLED);
            })
            // Case 4: recipientId as recipient
            ->orWhere(function ($q) use ($recipientId) {
                $q->where('recipient_id', $recipientId)
                ->where('recipient_status', '!=', Appointment::STATUS_REJECTED);
            });
        })
        ->where('start_date', '<', $endDate)
        ->where('end_date', '>', $startDate);

        if ($appointmentId) {
            $overlap->where('id', '!=', $appointmentId);
        }
        // Get count of overlap appointments from patient service by therapist/phc-worker.
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country),
            'country' => $country,
        ])->get(env('PATIENT_SERVICE_URL') . '/appointment/count/overlap', [
            'start_date' => Carbon::parse($startDate)->format('Y-m-d H:i:s'),
            'end_date' => Carbon::parse($endDate)->format('Y-m-d H:i:s'),
            'requester_id' => $requesterId,
            'recipient_id' => $recipientId,
        ]);

        if ($response->successful()) {
            $count = data_get($response->json(), 'data', 0);
        }

        return $overlap->count() + ($count ?? 0);
    }

    /**
     * @param \App\Models\Appointment $appointment
     * @return array
     */
    public function accept(Appointment $appointment)
    {
        $appointment->update([
            'recipient_status' => Appointment::STATUS_ACCEPTED,
            'unread' => true,
        ]);

        try {
            $appointment->requester->notify(new AppointmentNotification($appointment, Appointment::STATUS_ACCEPTED));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'success_message.appointment_update',
            'data' => new AppointmentResource($appointment)
        ];
    }

    /**
     * @OA\Delete(
     *     path="/api/appointment/{id}",
     *     tags={"Appointment"},
     *     summary="Delete appointment",
     *     operationId="deleteAppointment",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
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
     * @param \App\Models\Appointment $appointment
     *
     *  @param \Illuminate\Http\Request $request
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(Appointment $appointment)
    {
        if ($appointment->requester_id === Auth::user()->id) {
            $appointment->update(['requester_status' => Appointment::STATUS_CANCELLED]);

            try {
                $appointment->recipient->notify(new AppointmentNotification($appointment, Appointment::STATUS_CANCELLED));
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }

        return ['success' => true, 'message' => 'success_message.appointment_cancel'];
    }

    /**
     * @param \App\Models\Appointment $appointment
     * @return array
     */
    public function declined(Appointment $appointment)
    {
        $appointment->update([
            'recipient_status' => Appointment::STATUS_REJECTED,
            'unread' => true,
        ]);

        $appointment->requester->notify(new AppointmentNotification($appointment, Appointment::STATUS_REJECTED));

        $message = 'success_message.appointment_update';
        return ['success' => true, 'message' => $message, 'data' => new AppointmentResource($appointment)];
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function updateAsRead(Request $request)
    {
        Appointment::whereIn('id', $request)->update(['unread' => false]);

        return ['success' => true, 'message' => 'success_message.unread_update'];
    }

    /**
     * Count overlap appointments for patient
     *
     * @param Request $request
     * @return array
     */
    public function countOverlapAppointment(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $startDate = date_create_from_format('Y-m-d H:i:s', $request->get('start_date'));
        $endDate = date_create_from_format('Y-m-d H:i:s', $request->get('end_date'));

        $overlap = Appointment::where(function ($query) use ($therapistId) {
            $query->orWhere(function ($q) use ($therapistId) {
                $q->where('requester_id', $therapistId)
                ->where('requester_status', '!=', Appointment::STATUS_CANCELLED);
            });

            $query->orWhere(function ($q) use ($therapistId) {
                $q->where('recipient_id', $therapistId)
                    ->where('recipient_status', '!=', Appointment::STATUS_REJECTED);
                });
            })
            ->where('start_date', '<', $endDate)
            ->where('end_date', '>', $startDate);

        return ['success' => true, 'data' => $overlap->count()];
    }
}
