<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Helpers\CryptHelper;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $password = CryptHelper::hash($this->chat_password);
        return [
            'id' => $this->id,
            'identity' => $this->identity,
            'clinic_id' => $this->clinic_id,
            'country_id' => $this->country_id,
            'profession_id' => $this->profession_id,
            'limit_patient' => $this->limit_patient,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'dial_code' => $this->dial_code,
            'enabled' => $this->enabled,
            'last_login' => $this->last_login,
            'language_id' => $this->language_id,
            'chat_user_id' => $this->chat_user_id,
            'chat_password' => $password,
            'chat_rooms' => $this->chat_rooms ?: [],
            'show_guidance' => $this->show_guidance,
        ];
    }
}
