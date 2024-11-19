<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Transfer extends Model
{
    use HasFactory, LogsActivity;

    const STATUS_INVITED = 'invited';
    const STATUS_DECLINED = 'declined';
    const LEAD_THERAPIST = 'lead';
    const SUPPLEMENTARY_THERAPIST = 'supplementary';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'from_therapist_id',
        'to_therapist_id',
        'clinic_id',
        'therapist_type',
        'status'
    ];

    /**
     * Spatie\Activitylog config
     */
    protected static $logAttributes = ['*'];
    protected static $logAttributesToIgnore = ['id', 'created_at', 'updated_at'];
    protected static $logOnlyDirty = true;
    protected static $submitEmptyLogs = false;


    public function from_therapist()
    {
        return $this->belongsTo(User::class, 'from_therapist_id');
    }

    public function to_therapist()
    {
        return $this->belongsTo(User::class, 'to_therapist_id');
    }
}
