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

define("GADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('GADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("ADMIN_KEYCLOAK_TOKEN_URL", env('KEYCLOAK_URL') . '/auth/realms/' . env('ADMIN_KEYCLOAK_REAMLS_NAME') . '/protocol/openid-connect/token');
define("PATIENT_LOGIN_URL", env('PATIENT_SERVICE_URL') . '/auth/login');

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
    public static function hasRealmRole($role)
    {
        $decodedToken = json_decode(Auth::token(), true);
        $authRoles = $decodedToken['realm_access']['roles'];
        if (in_array($role, $authRoles)) {
            return true;
        }
        return false;
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
        $url = KEYCLOAK_USERS . '/' . $userUuid;
        $response = Http::withToken($token)->delete($url);

        return $response->successful();
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
}
