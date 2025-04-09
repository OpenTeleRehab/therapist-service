<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DownloadTracker extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'job_id',
        'status',
        'file_path',
        'author_id',
    ];
}
