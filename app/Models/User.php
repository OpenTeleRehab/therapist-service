<?php

namespace App\Models;

use App\Helpers\RocketChatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    const ADMIN_GROUP_ORGANIZATION_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const GROUP_THERAPIST = 'therapist';

    // MFA constants configurations
    const MFA_ENFORCE = 'force';
    const MFA_RECOMMEND = 'recommend';
    const MFA_DISABLE = 'skip';
    const MFA_KEY_ENFORCEMENT = 'mfa_enforcement';
    const ROLE_LEVEL = [
        'organization_admin' => 1,
        'country_admin' => 2,
        'clinic_admin' => 3,
        'therapist' => 4,
    ];
    const ENFORCEMENT_LEVEL = [
        'force' => 1,
        'recommend' => 2,
        'skip' => 3,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'country_id', 'clinic_id', 'limit_patient', 'language_id', 'profession_id',
        'identity', 'enabled', 'chat_user_id', 'chat_password', 'chat_rooms', 'last_login', 'show_guidance',
        'phone', 'dial_code',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'chat_rooms' => 'array',
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
            ->logExcept(['id', 'chat_user_id', 'chat_password' , 'chat_rooms', 'created_at', 'updated_at', 'email_verified_at', 'remember_token', 'password']);
    }

    /**
     * Modify the activity properties before it is saved.
     *
     * @param \Spatie\Activitylog\Models\Activity $activity
     * @return void
     */
    public function tapActivity(ActivityLog $activity)
    {
        $request = request();
        $activity->causer_id = $request['user_id'] ? $request['user_id'] : $this->id;
        $activity->full_name = $request['user_name'] ? $request['user_name'] : $this->last_name . ' ' . $this->first_name;
        $activity->group = $request['group'] ? $request['group'] : User::GROUP_THERAPIST;
        $activity->clinic_id = $request->has('group')
            ? ($request['group'] === self::ADMIN_GROUP_ORGANIZATION_ADMIN ? null : $request->input('clinic_id')) ?? $this->clinic_id
            : $this->clinic_id;

        $activity->country_id = $request->has('group')
            ? ($request['group'] === self::ADMIN_GROUP_ORGANIZATION_ADMIN ? null : $request->input('country_id')) ?? $this->country_id
            : $this->country_id;
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default order by status (active/inactive), last name, and first name.
        static::addGlobalScope('order', function (Builder $builder) {
            $builder->orderBy('enabled', 'desc');
            $builder->orderBy('last_name');
            $builder->orderBy('first_name');
        });

        self::updated(function ($user) {
            try {
                RocketChatHelper::updateUser($user->chat_user_id, [
                    'active' => boolval($user->enabled),
                    'name' => $user->last_name . ' ' . $user->first_name
                ]);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });

        self::deleted(function ($user) {
            try {
                RocketChatHelper::deleteUser($user->chat_user_id);
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
