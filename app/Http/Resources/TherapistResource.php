<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TherapistResource extends JsonResource
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
            'enabled' => $this->enabled,
            'chat_user_id' => $this->chat_user_id,
            'profession_id' => $this->profession_id,
            'chat_rooms' => $this->chat_rooms,
        ];
    }
}
