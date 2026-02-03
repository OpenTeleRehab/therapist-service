<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class AppointmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'requester_id' => $this->requester_id,
            'recipient_id' => $this->recipient_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_at' => Carbon::parse($this->created_at)->toDateTimeString(),
            'recipient' => $this->recipient->only('id', 'first_name', 'last_name'),
            'requester' => $this->requester->only('id', 'first_name', 'last_name'),
            'requester_status' => $this->requester_status,
            'recipient_status' => $this->recipient_status,
            'note' => $this->note,
            'with_user_type' => $this->requester_id === Auth::user()->id ? $this->recipient->type : $this->requester->type,
            'type' => $this->type,
        ];
    }
}
