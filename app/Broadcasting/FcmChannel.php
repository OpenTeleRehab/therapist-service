<?php

namespace App\Broadcasting;

use Illuminate\Notifications\Notification;

class FcmChannel
{
    /**
     * Create a new channel instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Authenticate the user's access to the channel.
     */
    public function send($notifiable, Notification $notification)
    {
        $notification->toFcm($notifiable);
    }
}
