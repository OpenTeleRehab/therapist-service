<?php

namespace App\Providers;

use App\Events\AddReConsentTermsOfServices;
use App\Listeners\AddReConsentTermsOfServicesToUsers;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\AddLogToAdminServiceEvent;
use App\Listeners\AddLogToAdminServiceListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        AddReConsentTermsOfServices::class => [
            AddReConsentTermsOfServicesToUsers::class
        ],
        AddLogToAdminServiceEvent::class => [
            AddLogToAdminServiceListener::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
    }
}
