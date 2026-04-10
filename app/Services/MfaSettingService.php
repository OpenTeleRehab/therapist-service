<?php

namespace App\Services;

use App\Helpers\KeycloakHelper;
use App\Models\Forwarder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MfaSettingService
{
    /**
     * Fetch users to apply MFA setting
     */
    public function getUsers()
    {
        $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));

        return User::query()
            ->where('email', '!=', 'hi_backend')
            ->where(function ($query) use ($federatedDomains) {
                foreach ($federatedDomains as $domain) {
                    $query->whereRaw('LOWER(email) NOT LIKE ?', ['%' . $domain]);
                }
            })->get();
    }

    /**
     * Get MFA settings that need to use in therapist service.
     */
    public function getMfaSettings($mfaSettingId = null)
    {
        $query = [];

        if ($mfaSettingId) {
            $query['exclude_id'] = $mfaSettingId;
        }

        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/mfa-settings/get-for-therapist-service', $query)
            ->throw();

        return $response->json('data');
    }

    public function getMfaSettingsByUserType($mfaSettings, $userType)
    {
        $settings = collect($mfaSettings);
        $reverseRoleHierarchy = array_reverse(User::roleHierarchy);

        $settings = $settings->filter(function ($item) use ($userType) {
            return $item['role'] === $userType;
        });

        return $settings->sortBy(function ($item) use ($reverseRoleHierarchy) {
            return array_search($item['created_by_role'], $reverseRoleHierarchy);
        })->values();
    }

    public function removeMfaForUsers($users)
    {
        foreach ($users as $user) {
            $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($user->email);

            if (!$keycloakUser) {
                Log::warning("Keycloak user not found for email: {$user->email}");
                continue;
            }

            KeycloakHelper::setUserAttributes(
                $user->email,
                [
                    'mfaEnforcement' => '',
                    'trustedDeviceMaxAge' => '',
                    'skipMfaMaxAge' => '',
                    'skipMfaUntil' => '',
                ],
            );
        }
    }

    public function apply($email, $mfa): bool
    {
        $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($email);

        if (!$keycloakUser) {
            Log::warning("Keycloak user not found for email: {$email}");
            return false;
        }

        $existingAttributes = $keycloakUser['attributes'] ?? [];

        $payload = [
            'mfaEnforcement' => $mfa['mfa_enforcement'] ?? '',
            'trustedDeviceMaxAge' => $mfa['mfa_expiration_duration_in_seconds'] ?? '',
            'skipMfaMaxAge' => $mfa['skip_mfa_setup_duration_in_seconds'] ?? '',
        ];

        if (isset($existingAttributes['skipMfaUntil'])) {
            $date = Carbon::parse($existingAttributes['skipMfaUntil'][0]);

            $now = Carbon::now();

            $futureDate = $now->copy()->addSeconds($mfa['skip_mfa_setup_duration_in_seconds']);

            $isoString = $futureDate->format('Y-m-d\TH:i:s.u\Z');

            if (!$date->isPast()) {
                $payload['skipMfaUntil'] = $isoString;
            }
        }

        KeycloakHelper::setUserAttributes($email, $payload);

        return true;
    }

    public function jobTrackerUpdate($jobId, string $status, ?string $message = null): void
    {
        Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))->put(
            env('ADMIN_SERVICE_URL') . "/internal/job-trackers/{$jobId}",
            ['status' => $status, 'message' => "Failed at therapist service: $message"]
        )->throw();
    }

    public function deleteMfaSetting($mfaSettingId): void
    {
        Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))->delete(
            env('ADMIN_SERVICE_URL') . "/internal/mfa-settings/{$mfaSettingId}"
        )->throw();
    }

    /**
     * Resolve the MFA setting for a user
     */
    public function resolve($mfaSettings, $user)
    {
        $matchedSetting = null;

        foreach ($mfaSettings as $mfa) {
            if ($mfa['role'] !== $user->type) {
                continue;
            }

            if (!empty($mfa['clinic_ids'])) {
                if (in_array($user->clinic_id, $mfa['clinic_ids'])) {
                    return $mfa;
                }
                continue;
            }

            if (!empty($mfa['phc_service_ids'])) {
                if (in_array($user->phc_service_id, $mfa['phc_service_ids'])) {
                    return $mfa;
                }
                continue;
            }

            if (!empty($mfa['region_ids'])) {
                if (in_array($user->region_id, $mfa['region_ids'])) {
                    return $mfa;
                }
                continue;
            }

            if (!empty($mfa['country_ids'])) {
                if (in_array($user->country_id, $mfa['country_ids'])) {
                    return $mfa;
                }
                continue;
            }

            if (
                empty($mfa['clinic_ids']) &&
                empty($mfa['region_ids']) &&
                empty($mfa['country_ids'])
            ) {
                $matchedSetting = $mfa;
            }
        }

        return $matchedSetting;
    }
}
