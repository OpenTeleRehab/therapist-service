<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class UserController extends Controller
{
    /**
     * @param string $username
     *
     * @return \App\Http\Resources\UserResource
     */
    public function getUserProfile($username)
    {
        // TODO: validate with keycloak auth.
        $user = User::where('email', $username)->firstOrFail();
        return new UserResource($user);
    }

    /**
     * @param string $username
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updatePassword($username, Request $request)
    {
        $password = $request->get('current_password');
        $userResponse = KeycloakHelper::getLoginUser($username, $password);
        if ($userResponse->successful()) {
            // TODO: use own user token
            // $token = $userResponse->json('access_token');!
            $token = KeycloakHelper::getKeycloakAccessToken();
            $userUrl = KEYCLOAK_USERS . '/' . $request->get('user_id');
            $newPassword = $request->get('new_password');
            $isCanSetPassword = KeycloakHelper::resetUserPassword(
                $token,
                $userUrl,
                $newPassword,
                false
            );

            if ($isCanSetPassword) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'error_message.cannot_change_password'];
        }

        return ['success' => false, 'message' => 'error_message.wrong_password'];
    }
}
