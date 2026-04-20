<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PhcWorkerResource extends JsonResource
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
            'identity' => $this->identity,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'language_id' => $this->language_id,
            'profession_id' => $this->profession_id,
            'devices' => $this->devices,
        ];
    }
}
