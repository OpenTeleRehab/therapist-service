<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class TherapistListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $resource = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'profession_id' => $this->profession_id,
            'limit_patient' => $this->limit_patient,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
        ];

        if ($request->get('user_type') === User::ADMIN_GROUP_CLINIC_ADMIN) {
            $resource = array_merge($resource, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
                'dial_code' => $this->dial_code,
                'email' => $this->email,
                'enabled' => $this->enabled,
                'language_id' => $this->language_id,
            ]);
        }

        return $resource;
    }
}
