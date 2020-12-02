<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentPlanResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'patient_id' => $this->patient_id,
            'start_date' => $this->start_date ? $this->start_date->format(config('settings.date_format')) : '',
            'end_date' => $this->end_date ? $this->end_date->format(config('settings.date_format')) : '',
        ];
    }
}
