<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;

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
        $responseData = [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'profession_id' => $this->profession_id,
        ];

        if ($request->get('user_type') !== User::ADMIN_GROUP_GLOBAL_ADMIN ) {
            $responseData = array_merge($responseData, [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'limit_patient' => $this->limit_patient,
                'enabled' => $this->enabled,
                'last_login' => $this->last_login,
                'profession_id' => $this->profession_id,
                'language_id' => $this->language_id,
                'chat_user_id' => $this->chat_user_id,
                'chat_password' => $this->chat_password,
                'chat_rooms' => $this->chat_rooms ?: [],
            ]);
        }
        return $responseData;
    }
}
