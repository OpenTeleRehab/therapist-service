<?php

namespace App\Jobs;

use App\Events\MfaProgressStatus;
use Throwable;
use App\Services\MfaSettingService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ApplyMfaSettings implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $mfaSettingRole;
    protected $broadcastChannel;
    protected $jobId;
    protected $rowId;
    protected $isDeleting;

    /**
     * Create a new job instance.
     */
    public function __construct(string $mfaSettingRole, string $broadcastChannel, $jobId, $rowId, $isDeleting)
    {
        $this->mfaSettingRole = $mfaSettingRole;
        $this->broadcastChannel = $broadcastChannel;
        $this->jobId = $jobId;
        $this->rowId = $rowId;
        $this->isDeleting = $isDeleting;
    }

    /**
     * Execute the job.
     */
    public function handle(MfaSettingService $mfaSettingService): void
    {
        $users = $mfaSettingService->getUsers();

        $mfaSettingService->removeMfaForUsers($users);

        $allMfaSettings = $mfaSettingService->getMfaSettings($this->isDeleting ? $this->rowId : null);

        foreach ($users as $user) {
            $mfaSettings = $mfaSettingService->getMfaSettingsByUserType($allMfaSettings, $user->type);

            $mfa = $mfaSettingService->resolve($mfaSettings, $user);

            if (!$mfa) {
                continue;
            }

            if (!$mfaSettingService->apply($user->email, $mfa)) {
                continue;
            }
        }

        $this->complete($mfaSettingService);
    }

    private function complete(MfaSettingService $mfaSettingService)
    {
        if ($this->isDeleting) {
            $mfaSettingService->deleteMfaSetting($this->rowId);
        }

        $mfaSettingService->jobTrackerUpdate($this->jobId, 'completed');

        $this->broadcastStatus('completed', $this->isDeleting);
    }

    public function failed(Throwable $exception): void
    {
        $mfaSettingService = app(MfaSettingService::class);

        $mfaSettingService->jobTrackerUpdate($this->jobId, 'failed', $exception->getMessage());
        $this->broadcastStatus('failed');
    }

    private function broadcastStatus(string $status, ?bool $isDeleting = false): void
    {
        broadcast(new MfaProgressStatus(
            $this->broadcastChannel,
            $this->jobId,
            $this->rowId,
            $status,
            $isDeleting
        ));
    }
}
