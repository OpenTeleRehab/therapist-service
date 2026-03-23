<?php

namespace App\Jobs;

use App\Helpers\KeycloakHelper;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessUserDeletion implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected string $entityName;
    protected int $entityId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $entityName, int $entityId)
    {
        $this->entityName = $entityName;
        $this->entityId = $entityId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $entityColumnMap = [
            'country' => 'country_id',
            'region' => 'region_id',
            'province' => 'province_id',
            'rehab_service' => 'clinic_id',
            'phc_service' => 'phc_service_id',
        ];

        $column = $entityColumnMap[$this->entityName];

        $users = User::where($column, $this->entityId)->get();
        $deletedCount = 0;

        $users->each(function ($user) use (&$deletedCount) {
            $token = KeycloakHelper::getKeycloakAccessToken();

            $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $user->email;
            $response = Http::withToken($token)->get($userUrl);

            if ($response->successful()) {
                $keyCloakUsers = $response->json();
                if (!empty($keyCloakUsers)) {
                    KeycloakHelper::deleteUser($token, $keyCloakUsers[0]['id']);
                } else {
                    Log::warning("No user found in Keycloak for email: {$user->email}");
                }

                $user->delete();
            }

            $deletedCount++;
        });

        Log::info('Successfully deleted ' . $deletedCount . ' therapists belonging to ' . $this->entityName . ' id ' . $this->entityId);
    }
}
