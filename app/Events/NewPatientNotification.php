<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewPatientNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var string
     */
    private $patientFirstName;

    /**
     * @var string
     */
    private $patientLastName;

    /**
     * @var integer
     */
    private $therapistId;

    /**
     * Create a new event instance.
     *
     * @param int $therapistId
     * @param string $patientFirstName
     * @param string $patientLastName
     */
    public function __construct($therapistId, $patientFirstName, $patientLastName)
    {
        $this->therapistId = $therapistId;
        $this->patientFirstName = $patientFirstName;
        $this->patientLastName = $patientLastName;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('new-patient.' . $this->therapistId);
    }

    /**
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'patientFirstName' => $this->patientFirstName,
            'patientLastName' => $this->patientLastName,
        ];
    }

    /**
     * @return string
     */
    public function broadcastAs()
    {
        return 'new-patient-notification';
    }
}
