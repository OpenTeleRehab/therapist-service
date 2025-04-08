<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DownloadTrackerResource extends JsonResource
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
            'type' => $this->type,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'created_at' => $this->created_at,
            'job_id' => $this->job_id,
        ];
    }
}
