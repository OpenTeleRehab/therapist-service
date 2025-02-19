<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Events\AddLogToAdminServiceEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     tags={"Profile"},
     *     summary="User profile",
     *     operationId="userProfile",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @return \App\Http\Resources\UserResource
     */
    public function getUserProfile()
    {
        $user = Auth::user();
        // Update enabled to true when first login.
        if (!$user->last_login) {
            $user->update([
                'last_login' => now(),
                'enabled' => true,
            ]);
        }

        return new UserResource($user);
    }

    /**
     * @OA\Put(
     *     path="/api/user/update-password",
     *     tags={"Profile"},
     *     summary="Update password",
     *     operationId="updatePassword",
     *     @OA\Parameter(
     *         name="current_password",
     *         in="query",
     *         description="Current password",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="new_password",
     *         in="query",
     *         description="New password",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
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
     * @OA\Put(
     *     path="/api/user/update-information",
     *     tags={"Profile"},
     *     summary="Update profile",
     *     operationId="updateProfile",
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="language_id",
     *         in="query",
     *         description="Language id",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="profession_id",
     *         in="query",
     *         description="Profession id",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="show_guidance",
     *         in="query",
     *         description="Show guidance",
     *         required=true,
     *          @OA\Schema(
     *              type="integer",
     *              enum={0,1}
     *          ),
     *     ),
     *     @OA\Parameter(
     *         name="language_code",
     *         in="query",
     *         description="Language code",
     *         required=true,
     *          @OA\Schema(
     *              type="string"
     *          ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
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
                'profession_id' => $data['profession_id'],
                'show_guidance' => $data['show_guidance']
            ];
            $user->update($dataUpdate);
             // Activity log
            $lastLoggedActivity = Activity::all()->last();
            event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));

            if ($data['language_code']) {
                try {
                    $this->updateUserLocale($user->email, $data['language_code']);
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }
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
            $user = User::find($request['therapist_id']);
            $newChatRoom = $request->get('chat_room_id');
            $chatRooms = $user->chat_rooms ?? [];

            if (array_search($newChatRoom, $chatRooms) !== 0) {
                $chatRooms[] = $newChatRoom;
                $user->chat_rooms = $chatRooms;
                $user->save();
                // Activity log
                $lastLoggedActivity = Activity::all()->last();
                event(new AddLogToAdminServiceEvent($lastLoggedActivity, Auth::user()));
            }
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
            // Activity log
            $lastLoggedActivity = Activity::all()->last();
            event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));
            return ['success' => true, 'message' => 'Successful'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param string $email
     * @param string $languageCode
     *
     * @return bool
     * @throws \Exception
     */
    private function updateUserLocale($email, $languageCode)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        if ($token) {
            try {
                $userUrl = KEYCLOAK_USERS . '?email=' . $email;

                $response = Http::withToken($token)->get($userUrl);
                $keyCloakUsers = $response->json();
                $url = KEYCLOAK_USERS . '/' . $keyCloakUsers[0]['id'];

                $response = Http::withToken($token)->put($url, ['attributes' => ['locale' => [$languageCode]]]);

                return $response->successful();
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }

        throw new \Exception('no_token');
    }
}
