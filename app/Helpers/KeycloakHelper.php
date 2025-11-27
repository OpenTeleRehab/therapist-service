<?php

namespace App\Helpers;

use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("KEYCLOAK_USER_URL", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');
define("KEYCLOAK_GROUPS_URL", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/groups');
define('KEYCLOAK_ROLES_URL', env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/roles');

define("GADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('GADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("ADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('ADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("PATIENT_LOGIN_URL", env('PATIENT_SERVICE_URL') . '/auth/login');
define("WEBHOOK_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/webhooks');

/**
 * Class KeycloakHelper
 * @package App\Helpers
 */
class KeycloakHelper
{
    const GADMIN_ACCESS_TOKEN = 'gadmin_access_token';
    const ADMIN_ACCESS_TOKEN = 'admin_access_token';
    const THERAPIST_ACCESS_TOKEN = 'therapist_access_token';
    const PATIENT_ACCESS_TOKEN = 'patient_access_token';
    const VN_PATIENT_ACCESS_TOKEN = 'vn_patient_access_token';

    /**
     * @return string
     */
    public static function getUserUrl(): string
    {
        return config('keycloak.user_url');
    }

    /**
     * @return string
     */
    public static function getExecuteEmailUrl(): string
    {
        return config('keycloak.execute_email');
    }

    /**
     * @return string
     */
    public static function getGroupsUrl(): string
    {
        return config('keycloak.groups_url');
    }

    /**
     * @return mixed|null
     */
    public static function getKeycloakAccessToken()
    {
        $access_token = Cache::get(self::THERAPIST_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = (int) $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        return self::generateKeycloakToken(KEYCLOAK_TOKEN_URL, env('KEYCLOAK_BACKEND_SECRET'), self::THERAPIST_ACCESS_TOKEN);
    }

    /**
     * @return mixed|null
     */
    public static function getGAdminKeycloakAccessToken()
    {
        $access_token = Cache::get(self::GADMIN_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = (int) $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        return self::generateKeycloakToken(GADMIN_KEYCLOAK_TOKEN_URL, env('GADMIN_KEYCLOAK_BACKEND_SECRET'), self::GADMIN_ACCESS_TOKEN);
    }

    /**
     * @return mixed|null
     */
    public static function getAdminKeycloakAccessToken()
    {
        $access_token = Cache::get(self::ADMIN_ACCESS_TOKEN);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = (int) $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        return self::generateKeycloakToken(ADMIN_KEYCLOAK_TOKEN_URL, env('ADMIN_KEYCLOAK_BACKEND_SECRET'), self::ADMIN_ACCESS_TOKEN);
    }

    /**
     * @param string|null $host
     *
     * @return mixed|null
     */
    public static function getPatientKeycloakAccessToken($host)
    {
        $cache_key = $host === strtoupper(config('settings.vn_country_iso')) ? self::VN_PATIENT_ACCESS_TOKEN : self::PATIENT_ACCESS_TOKEN;
        $access_token = Cache::get($cache_key);

        if ($access_token) {
            $token_arr = explode('.', $access_token);
            $token_obj = json_decode(JWT::urlsafeB64Decode($token_arr[1]), true);
            $token_exp_at = (int) $token_obj['exp'];
            $current_timestamp = Carbon::now()->timestamp;

            if ($current_timestamp < $token_exp_at) {
                return $access_token;
            }
        }

        $response = Http::withHeaders(['country' => $host])->post(PATIENT_LOGIN_URL, [
            'email' => env('KEYCLOAK_BACKEND_CLIENT'),
            'pin' => env('PATIENT_BACKEND_PIN'),
        ]);

        if ($response->successful()) {
            $result = $response->json();

            Cache::forever($cache_key, $result['data']['token']);

            return $result['data']['token'];
        }

        return null;
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function getLoginUser($username, $password)
    {
        return Http::asForm()->post(KEYCLOAK_TOKEN_URL, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => env('KEYCLOAK_BACKEND_SECRET'),
            'username' => $username,
            'password' => $password,
        ]);
    }


    /**
     * @param string $token
     * @param string $url
     * @param string $password
     * @param bool $isTemporary
     *
     * @return bool
     */
    public static function resetUserPassword($token, $url, $password, $isTemporary = true)
    {
        $response = Http::withToken($token)->put($url . '/reset-password', [
            'value' => $password,
            'type' => 'password',
            'temporary' => $isTemporary
        ]);
        if ($response->successful()) {
            return true;
        }
        return false;
    }

    /**
     * @param string $role
     *
     * @return bool
     */
    public static function hasRealmRole($roles)
    {
        $decodedToken = json_decode(Auth::token(), true);

        if (
            !isset($decodedToken['realm_access'])
            || !isset($decodedToken['realm_access']['roles'])
        ) {
            return false;
        }

        $authRoles = $decodedToken['realm_access']['roles'];

        return !empty(array_intersect($roles, $authRoles));
    }

    /**
     * @return string
     */
    public static function getUserUuid()
    {
        $decodedToken = json_decode(Auth::token(), true);
        return $decodedToken['sub'];
    }

    /**
     * @param string $token
     *
     * @return array
     */
    public static function getUserGroup($token)
    {
        $response = Http::withToken($token)->get(KEYCLOAK_GROUPS_URL);
        $userGroups = [];
        if ($response->successful()) {
            $groups = $response->json();
            foreach ($groups as $group) {
                $userGroups[$group['name']] = $group['id'];
            }
        }

        return $userGroups;
    }

    /**
     * @param string $token
     * @param string $userUuid
     * @return bool
     */
    public static function deleteUser($token, $userUuid)
    {
        $url = self::getUserUrl() . '/' . $userUuid;
        $response = Http::withToken($token)->delete($url);

        return $response->successful();
    }

    /**
     * Create a new group in Keycloak.
     *
     * @param string $groupName
     * @return bool
     */
    public static function createGroup($groupName)
    {
        $token = self::getKeycloakAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(self::getGroupsUrl(), ['name' => $groupName]);

        return $response->successful();
    }

    /**
     * Create a realm role in Keycloak.
     *
     * @param string $roleName
     * @param string $description
     * @return bool
     */
    public static function createRealmRole($roleName, $description = '')
    {
        $token = self::getKeycloakAccessToken();

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(KEYCLOAK_ROLES_URL, [
                'name' => $roleName,
                'description' => $description,
            ]);

        return $response->successful();
    }

    /**
     * Assign a realm role to a Keycloak group.
     *
     * @param string $groupName
     * @param string $roleName
     * @return bool
     */
    public static function assignRealmRoleToGroup($groupName, $roleName)
    {
        $token = self::getKeycloakAccessToken();
        $groupId = self::getUserGroups($token)[$groupName] ?? null;

        if (!$groupId) {
            throw new \Exception("Group '{$groupName}' not found.");
        }

        $roleResponse = Http::withToken($token)
            ->get(KEYCLOAK_ROLES_URL . "/{$roleName}");

        if (!$roleResponse->successful()) {
            throw new \Exception("Role '{$roleName}' not found.");
        }

        $role = $roleResponse->json();

        $assignResponse = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post(KEYCLOAK_GROUPS_URL . "/{$groupId}/role-mappings/realm", [
                [
                    'id' => $role['id'],
                    'name' => $role['name']
                ]
            ]);

        return $assignResponse->successful();
    }

        /**
     * @param string $token
     *
     * @return array
     */
    private static function getUserGroups($token)
    {
        $response = Http::withToken($token)->get(KEYCLOAK_GROUPS_URL);
        $userGroups = [];
        if ($response->successful()) {
            $groups = $response->json();
            foreach ($groups as $group) {
                $userGroups[$group['name']] = $group['id'];
            }
        }

        return $userGroups;
    }

    /**
     * @param string $url
     * @param string $client_secret
     * @param string $cache_key
     *
     * @return void
     */
    private static function generateKeycloakToken($url, $client_secret, $cache_key)
    {
        $response = Http::asForm()->post($url, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => $client_secret,
            'username' => env('KEYCLOAK_BACKEND_USERNAME'),
            'password' => env('KEYCLOAK_BACKEND_PASSWORD')
        ]);

        if ($response->successful()) {
            $result = $response->json();

            Cache::forever($cache_key, $result['access_token']);

            return $result['access_token'];
        }

        return null;
    }

    /**
     * @param string $url
     * @param array $eventTypes
     *
     * @return bool
     */
    public static function createWebhook($url, $eventTypes)
    {
        $response = Http::asForm()->post(KEYCLOAK_TOKEN_URL, [
            'grant_type' => 'password',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => env('KEYCLOAK_BACKEND_SECRET'),
            'username' => env('KEYCLOAK_BACKEND_USERNAME'),
            'password' => env('KEYCLOAK_BACKEND_PASSWORD')
        ]);
        $token = null;
        if ($response->successful()) {
            $result = $response->json();
            $token = $result['access_token'];
        }

        if ($token) {
            $response = Http::withToken($token)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post(WEBHOOK_URL, [
                'enabled' => true,
                'url' => $url,
                'secret' => env('KEYCLOAK_BACKEND_SECRET'),
                'eventTypes' => $eventTypes,
            ]);

            if ($response->successful()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a Keycloak user by username.
     *
     * @param string $username
     * @return array|null
     */
    public static function getKeycloakUserByUsername(string $username)
    {
        $token = self::getKeycloakAccessToken();
        if (!$token) {
            return null;
        }

        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->get(KEYCLOAK_USER_URL, [
            'username' => $username,
        ]);

        if ($response->successful()) {
            return $response->json()[0] ?? null;
        }

        return null;
    }

    /**
     * Retrieve the credentials for a specific Keycloak user.
     *
     * @param  string  $userId
     * @return array|null
     */
    public static function getUserCredential(string $userId): ?array
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $endPoint = KEYCLOAK_USER_URL . '/' . $userId . '/credentials';

        $response = Http::withToken($token)->get($endPoint);

        if (!$response->successful()) {
            return null;
        }

        return $response->json();
    }

    /**
     * Delete a specific type of credential (e.g., OTP, password) for a Keycloak user.
     *
     * @param  string  $userId
     * @param  string  $type
     * @return bool
     */
    public static function deleteUserCredentialByType(string $username, string $type): bool
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($username);

        if (!$keycloakUser || !is_array($keycloakUser) || empty($keycloakUser['id'])) {
            return false;
        }

        $userId = $keycloakUser['id'];

        $credentials = self::getUserCredential($userId);

        if (empty($credentials)) {
            return false;
        }

        $credential = collect($credentials)->firstWhere('type', $type);

        if (!$credential || empty($credential['id'])) {
            return false;
        }

        $endpoint = KEYCLOAK_USER_URL . '/' . $userId . '/credentials/' . $credential['id'];

        $response = Http::withToken($token)->delete($endpoint);

        return $response->successful();
    }

    /**
     * Set a Keycloak user attributes.
     *
     * @param string $id Keycloak user UUID
     * @param array $attributes
     * @return bool
     */
    public static function updateUserAttributesById(string $id, array $attributes)
    {
        $token = self::getKeycloakAccessToken();
        if (!$token) {
            return false;
        }

        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->put(KEYCLOAK_USER_URL . '/' . $id, [
            'attributes' => $attributes
        ]);

        return $response->successful();
    }

    /**
     * Update or set custom attributes for a Keycloak user by username.
     *
     * This method fetches the user by username, merges the provided attributes
     * with any existing user attributes, and sends an update request to the
     * Keycloak Admin API. Returns true on success or false if the user
     * is not found or the update request fails.
     *
     * @param  string  $username    The username of the user whose attributes will be updated.
     * @param  array   $attributes  An associative array of attributes to add or update.
     * @return bool  Returns true if the update was successful, false otherwise.
     */
    public static function setUserAttributes(string $username, array $attributes): bool
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $keycloakUser = KeycloakHelper::getKeycloakUserByUsername($username);

        if (!$keycloakUser) {
            return false;
        }

        $url = KEYCLOAK_USER_URL . '/' . $keycloakUser['id'];

        $attributes = array_filter($attributes, fn($value) => $value !== null);

        $existingAttributes = $keycloakUser['attributes'] ?? [];
        $keycloakUser['attributes'] = array_merge($existingAttributes, $attributes);

        $updateResponse = Http::withToken($token)
            ->put($url, $keycloakUser);

        if (!$updateResponse->successful()) {
            return false;
        }

        return true;
    }
}
