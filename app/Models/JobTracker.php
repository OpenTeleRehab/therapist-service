<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobTracker extends Model
{
    const PENDING = 'PENDING';
    const IN_PROGRESS = 'IN_PROGRESS';
    const COMPLETED = 'COMPLETED';
    const FAILED = 'FAILED';

    protected $fillable = [
        'job_id',
        'status',
        'message',
    ];
}