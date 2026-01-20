<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PhcWorkerListResource extends JsonResource
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
            'country_id' => $this->country_id,
            'phc_service_id' => $this->phc_service_id,
            'profession_id' => $this->profession_id,
            'limit_patient' => $this->limit_patient,
            'province_id' => $this->province_id,
            'region_id' => $this->region_id,
            'chat_rooms' => $this->chat_rooms,
        ];

        if ($request->get('user_type') === User::ADMIN_GROUP_PHC_SERVICE_ADMIN ) {
            $resource = array_merge($resource, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'phone' => $this->phone,
                'dial_code' => $this->dial_code,
                'email' => $this->email,
                'enabled' => $this->enabled,
                'language_id' => $this->language_id,
                'last_login' => $this->last_login,
            ]);
        }

        return $resource;
    }
}
