<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

class TherapistListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'profession_id' => $this->profession_id,
            'limit_patient' => $this->limit_patient,
            'show_guidance' => $this->show_guidance,
        ];

        if ($request->get('user_type') !== User::ADMIN_GROUP_ORGANIZATION_ADMIN ) {
            $responseData = array_merge($responseData, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
                'dial_code' => $this->dial_code,
                'email' => $this->email,
                'enabled' => $this->enabled,
                'last_login' => $this->last_login,
                'language_id' => $this->language_id,
            ]);
        }
        return $responseData;
    }
}
