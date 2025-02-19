<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $user = User::find($this->causer_id);
        $changes = $this->changes;
        return [
            'id' => $this->id,
            'resource' => $this->log_name,
            'type_of_changes' => $this->description,
            'who' => empty($user) ? '' : $user->full_name,
            'date_time' => $this->created_at,
            'before_changed' => $changes['old'] ?? null,
            'after_changed' => $changes['attributes']
        ];
    }
}
