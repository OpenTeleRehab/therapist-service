<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

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
        'phc_service_id',
        'therapist_type',
        'status'
    ];

    /**
     * Get the options for activity logging.
     *
     * @return \Spatie\Activitylog\LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['id', 'created_at', 'updated_at']);
    }

    /**
     * Modify the activity properties before it is saved.
     *
     * @param \Spatie\Activitylog\Models\Activity $activity
     * @return void
     */
    public function tapActivity(ActivityLog $activity)
    {
        $authUser = Auth::user();
        if ($authUser->email === env('KEYCLOAK_BACKEND_CLIENT')) {
            $activity->causer_id = $this->from_therapist_id;
        } else {
            $activity->causer_id = $authUser->id;
        }
    }

    public function from_therapist()
    {
        return $this->belongsTo(User::class, 'from_therapist_id');
    }

    public function to_therapist()
    {
        return $this->belongsTo(User::class, 'to_therapist_id');
    }
}
