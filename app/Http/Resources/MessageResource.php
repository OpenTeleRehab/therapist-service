<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'patient_id' => $this->patient_id,
            'therapist_id' => $this->therapist_id,
            'sentAt' => $this->sent_at ? $this->sent_at->format('Y-m-d h:i:s') : '',
            'draft' => $this->draft,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d h:i:s') : '',
            'message' => $this->message,
        ];
    }
}
