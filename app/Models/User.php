<?php

namespace App\Models;

use App\Helpers\RocketChatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity as ActivityLog;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasFactory, Notifiable, LogsActivity;

    const ADMIN_GROUP_SUPER_ADMIN = 'super_admin';
    const ADMIN_GROUP_ORGANIZATION_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_REGIONAL_ADMIN = 'regional_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';
    const ADMIN_GROUP_PHC_SERVICE_ADMIN = 'phc_service_admin';
    const GROUP_THERAPIST = 'therapist';
    const GROUP_PHC_WORKER = 'phc_worker';
    const TYPE_THERAPIST = 'therapist';
    const TYPE_PHC_WORKER = 'phc_worker';

    // MFA constants configurations
    const MFA_ENFORCE = 'force';
    const MFA_RECOMMEND = 'recommend';
    const MFA_DISABLE = 'skip';

    const roleHierarchy = [
        self::ADMIN_GROUP_SUPER_ADMIN,
        self::ADMIN_GROUP_ORGANIZATION_ADMIN,
        self::ADMIN_GROUP_COUNTRY_ADMIN,
        self::ADMIN_GROUP_REGIONAL_ADMIN,
        self::ADMIN_GROUP_CLINIC_ADMIN,
        self::ADMIN_GROUP_PHC_SERVICE_ADMIN,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'country_id',
        'clinic_id',
        'limit_patient',
        'language_id',
        'profession_id',
        'identity',
        'enabled',
        'chat_user_id',
        'chat_password',
        'chat_rooms',
        'last_login',
        'show_guidance',
        'notify_email',
        'notify_in_app',
        'phone',
        'dial_code',
        'region_id',
        'province_id',
        'type',
        'phc_service_id',
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
        $authUser = Auth::user();
        if ($authUser->admin_user_id) {
            $activity->causer_id = $authUser->admin_user_id;
            $activity->country_id = $authUser->country_id ?: null;
            $activity->clinic_id = $authUser->clinic_id ?: null;
            $activity->phc_service_id = $authUser->phc_service_id ?: null;
            $activity->province_id = $authUser->province_id ?: null;
            $activity->region_id = $authUser->region_id ?: null;
            $activity->log_name = ExtendActivity::ADMIN_SERVICE;
        }
    }

    /**
     * Get the devices for the user.
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
