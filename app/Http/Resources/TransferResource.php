<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'clinic_id' => $this->clinic_id,
            'from_therapist' => $this->from_therapist->only('id', 'first_name', 'last_name'),
            'from_therapist_id' => $this->from_therapist_id,
            'to_therapist' => $this->to_therapist ? $this->to_therapist->only('id', 'first_name', 'last_name') : null, // In case therapist is deleted.
            'to_therapist_id' => $this->to_therapist_id,
            'therapist_type' => $this->therapist_type,
            'status' => $this->status,
        ];
    }
}
