<?php

namespace App\Models;

use App\Helpers\RocketChatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const ADMIN_GROUP_ORGANIZATION_ADMIN = 'organization_admin';
    const ADMIN_GROUP_GLOBAL_ADMIN = 'global_admin';
    const ADMIN_GROUP_COUNTRY_ADMIN = 'country_admin';
    const ADMIN_GROUP_CLINIC_ADMIN = 'clinic_admin';

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
