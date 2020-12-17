<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'identity' => $this->identity,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'limit_patient' => $this->limit_patient,
            'enabled' => $this->enabled,
            'last_login' => $this->created_at,
            'profession_id' => $this->profession_id,
            'language_id' => $this->language_id
        ];
    }
}
