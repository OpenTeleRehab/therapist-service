<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Activity extends Model
{
    use HasFactory, LogsActivity;

    const ACTIVITY_TYPE_EXERCISE = 'exercise';
    const ACTIVITY_TYPE_MATERIAL = 'material';
    const ACTIVITY_TYPE_QUESTIONNAIRE = 'questionnaire';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['treatment_plan_id', 'week', 'day', 'activity_id', 'type', 'sets', 'reps', 'additional_information'];

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
        $user = Auth::user();
        $activity->causer_id = $user->id;
        $activity->full_name = $user->last_name . ' ' . $user->first_name; 
        $activity->clinic_id = $user->clinic_id;
        $activity->country_id = $user->country_id;
        $activity->group = User::GROUP_THERAPIST;
    }

}
