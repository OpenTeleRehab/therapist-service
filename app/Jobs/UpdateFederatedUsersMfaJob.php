<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
use App\Models\User;
use App\Models\JobTracker;
use Illuminate\Support\Str;
use App\Helpers\KeycloakHelper;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateFederatedUsersMfaJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $mfaSetting;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct($mfaSetting)
    {
        $this->mfaSetting = $mfaSetting;
        $this->jobId = Str::uuid()->toString();

        JobTracker::create([
            'job_id' => $this->jobId,
            'status' => JobTracker::PENDING
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));

            $internalUsers = User::query()
                ->where(function ($query) use ($federatedDomains) {
                    foreach ($federatedDomains as $domain) {
                        $query->whereRaw('LOWER(email) NOT LIKE ?', ['%' . strtolower($domain)]);
                    }
                })->get();

            foreach ($internalUsers as $user) {
                if ($this->mfaSetting['mfa_enforcement'] === 'skip') {
                    KeycloakHelper::deleteUserCredentialByType($user->email, 'otp');
                }

                $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($user->email);


                $existingAttributes = $keycloakUser['attributes'] ?? [];

                $payload = [
                    'mfaEnforcement' => $this->mfaSetting['mfa_enforcement'] ?? null,
                    'trustedDeviceMaxAge' => $this->mfaSetting['mfa_expiration_duration'] ?? null,
                    'skipMfaMaxAge' => $this->mfaSetting['skip_mfa_setup_duration'] ?? null,
                ];

                if (isset($existingAttributes['skipMfaUntil'])) {
                    $date = Carbon::parse($existingAttributes['skipMfaUntil'][0]);

                    $now = Carbon::now();

                    $futureDate = $now->copy()->addSeconds($this->mfaSetting['skip_mfa_setup_duration']);

                    $isoString = $futureDate->format('Y-m-d\TH:i:s.u\Z');

                    if (!$date->isPast()) {
                        $payload['skipMfaUntil'] = $isoString;
                    }
                }

                KeycloakHelper::setUserAttributes(
                    $user->email,
                    $payload,
                );
            }

            // end queue
            JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::COMPLETED]);
        } catch (Throwable $e) {
            JobTracker::where('job_id', $this->jobId)->update([
                'status' => JobTracker::FAILED,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
