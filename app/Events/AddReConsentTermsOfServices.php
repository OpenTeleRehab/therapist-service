<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AddReConsentTermsOfServices
{
    /**
     * @var array
     */
    public $user;

    /**
     * Create a new event instance.
     * @param array
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }
}
