<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class ProfileController extends Controller
{
    /**
     * @return \App\Http\Resources\UserResource
     */
    public function getUserProfile()
    {
        $user = Auth::user();
        // Update enabled to true when first login.
        if (!$user->last_login) {
            $user->update(['enabled' => true]);
        }

        return new UserResource($user);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $password = $request->get('current_password');
        $userResponse = KeycloakHelper::getLoginUser($user->email, $password);
        if ($userResponse->successful()) {
            // TODO: use own user token.
            $token = KeycloakHelper::getKeycloakAccessToken();
            $userUrl = KEYCLOAK_USERS . '/' . KeycloakHelper::getUserUuid();
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

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function updateUserProfile(Request $request)
    {
        try {
            $user = Auth::user();
            $data = $request->all();
            $dataUpdate = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'language_id' => $data['language_id'],
                'profession_id' => $data['profession_id']
            ];
            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function addNewChatRoom(Request $request)
    {
        try {
            $user = User::where('id', $request['therapist_id'])->first();
            $newChatRoom = $request->get('chat_room_id');
            $chatRooms = is_null($user->chat_rooms) ? [] : $user->chat_rooms;
            array_push($chatRooms, $newChatRoom);
            $user->chat_rooms = $chatRooms;
            $user->save();
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_add_chat_room'];
    }

    /**
     * @return array
     */
    public function updateLastAccess()
    {
        try {
            $user = Auth::user();
            $user->update([
                'last_login' => now(),
                'enabled' => true,
            ]);
            return ['success' => true, 'message' => 'Successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
