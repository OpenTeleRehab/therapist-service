<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MfaProgressStatus implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    protected string $broadcastChannel;
    public string $jobId;
    public int $rowId;
    public string $status;
    public ?string $message;
    public ?bool $isDeleting;

    /**
     * Create a new event instance.
     */
    public function __construct(string $broadcastChannel, string $jobId, int $rowId, string $status, ?bool $isDeleting = false, ?string $message = null)
    {
        $this->broadcastChannel = $broadcastChannel;
        $this->jobId = $jobId;
        $this->rowId = $rowId;
        $this->status = $status;
        $this->message = $message;
        $this->isDeleting = $isDeleting;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->broadcastChannel)];
    }

    /**
     * The name of the event for the frontend.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'progress';
    }
}
