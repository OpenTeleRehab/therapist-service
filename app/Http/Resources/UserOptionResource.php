<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserOptionResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'chat_rooms' => $this->chat_rooms,
        ];

        return $responseData;
    }
}
