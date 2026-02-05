<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class TreatmentPlan extends Model
{
    use LogsActivity;

    const STATUS_PLANNED = 'planned';
    const STATUS_ON_GOING = 'on-going';
    const STATUS_FINISHED = 'finished';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'patient_id',
        'start_date',
        'end_date',
        'status',
        'total_of_weeks',
        'created_by',
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
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('name');
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function activities()
    {
        return $this->hasMany(Activity::class);
    }
}
