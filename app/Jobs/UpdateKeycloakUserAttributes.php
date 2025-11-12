<?php

namespace App\Jobs;

use App\Helpers\KeycloakHelper;
use App\Models\JobTracker;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateKeycloakUserAttributes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;
    public string $jobId;

    /**
     * Max attempts and timeout
     */
    public $tries = 3;
    public $timeout = 600;

    public function __construct(array $data, string $jobId)
    {
        $this->data = $data;
        $this->jobId = $jobId;
    }

    public function handle(): void
    {
        $countryIds = $this->data['country_ids'] ?? [];
        $clinicIds = $this->data['clinic_ids'] ?? [];
        $attributes = $this->data['attributes'] ?? [];

        JobTracker::updateOrCreate(
            ['job_id' => $this->jobId],
            ['status' => JobTracker::IN_PROGRESS]
        );

        try {
            $usersQuery = User::query();

            if (!empty($countryIds)) {
                $usersQuery->whereIn('country_id', $countryIds);
            }

            if (!empty($clinicIds)) {
                $usersQuery->whereIn('clinic_id', $clinicIds);
            }

            $externalDomains = explode(',', env('FEDERATED_DOMAINS', ''));

            if (!empty($externalDomains)) {
                $usersQuery->where(function($query) use ($externalDomains) {
                    foreach ($externalDomains as $domain) {
                        $domain = trim($domain);
                        if ($domain) {
                            $query->orWhere('email', 'NOT LIKE', "%$domain");
                        }
                    }
                });
            }

            $users = $usersQuery->get();

            foreach ($users as $user) {
                $userData = KeycloakHelper::getKeycloakUserByUsername($user->email);
                if (!$userData) {
                    continue;
                }

                $existingAttributes = $userData['attributes'] ?? [];

                $newEnforcement = $attributes[User::MFA_KEY_ENFORCEMENT] ?? null;
                $oldEnforcement = isset($existingAttributes[User::MFA_KEY_ENFORCEMENT])
                    ? (is_array($existingAttributes[User::MFA_KEY_ENFORCEMENT])
                        ? $existingAttributes[User::MFA_KEY_ENFORCEMENT][0]
                        : $existingAttributes[User::MFA_KEY_ENFORCEMENT])
                    : null;

                if (
                    $oldEnforcement !== null &&
                    in_array($newEnforcement, [
                        User::MFA_DISABLE,
                        User::MFA_RECOMMEND,
                        User::MFA_ENFORCE
                    ], true) &&
                    in_array($oldEnforcement, [
                        User::MFA_DISABLE,
                        User::MFA_RECOMMEND,
                        User::MFA_ENFORCE
                    ], true) &&
                    (User::ENFORCEMENT_LEVEL[$newEnforcement] ?? 0) >
                    (User::ENFORCEMENT_LEVEL[$oldEnforcement] ?? 0)
                ) {
                    continue;
                }

                foreach ($attributes as $key => $value) {
                    if (
                        $key === User::MFA_KEY_ENFORCEMENT &&
                        $attributes[$key] === User::MFA_DISABLE
                    ) {
                        KeycloakHelper::deleteUserCredentialByType($user->email, 'otp');
                    }

                    $existingAttributes[$key] = is_array($value) ? $value : [$value];
                }

                KeycloakHelper::updateUserAttributesById($userData['id'], $existingAttributes);
            }

            JobTracker::where('job_id', $this->jobId)->update(['status' => JobTracker::COMPLETED]);
        } catch (\Throwable $e) {
            JobTracker::where('job_id', $this->jobId)->update([
                'status' => JobTracker::FAILED,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        JobTracker::where('job_id', $this->jobId)->update([
            'status' => JobTracker::FAILED,
            'message' => $exception->getMessage(),
        ]);
    }
}
