<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Helpers\RocketChatHelper;
use App\Http\Resources\TherapistResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');
define("KEYCLOAK_EXECUTE_EMAIL", '/execute-actions-email?client_id=' . env('KEYCLOAK_BACKEND_CLIENT') . '&redirect_uri=' . env('REACT_APP_BASE_URL'));

class TherapistController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $info = [];

        if (isset($data['id'])) {
            $users = User::where('id', $data['id'])->get();
        } else {
            $query = User::query();

            if ($request->has('clinic_id')) {
                $query->where('clinic_id', $request->get('clinic_id'));
            }

            if (isset($data['user_type']) && $data['user_type'] === User::ADMIN_GROUP_GLOBAL_ADMIN) {
                $query->where(function ($query) use ($data) {
                    $query->where('identity', 'like', '%' . $data['search_value'] . '%');
                });
            } else {
                $query->where(function ($query) use ($data) {
                    $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                        ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                        ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
                        ->orWhere('email', 'like', '%' . $data['search_value'] . '%');
                });
            }

            if (isset($data['filters'])) {
                $filters = $request->get('filters');
                $query->where(function ($query) use ($filters) {
                    foreach ($filters as $filter) {
                        $filterObj = json_decode($filter);
                        $excludedColumns = ['assigned_patients'];
                        if (in_array($filterObj->columnName, $excludedColumns)) {
                            continue;
                        } elseif ($filterObj->columnName === 'status') {
                            $query->where('enabled', $filterObj->value);
                        } elseif ($filterObj->columnName === 'last_login') {
                            $dates = explode(' - ', $filterObj->value);
                            $startDate = date_create_from_format('d/m/Y', $dates[0]);
                            $endDate = date_create_from_format('d/m/Y', $dates[1]);
                            $startDate->format('Y-m-d');
                            $endDate->format('Y-m-d');
                            $query->whereDate('last_login', '>=', $startDate)
                                ->whereDate('last_login', '<=', $endDate);
                        } elseif ($filterObj->columnName === 'therapist_country' && $filterObj->value !== '') {
                            $query->where('country_id', $filterObj->value);
                        } elseif ($filterObj->columnName === 'therapist_clinic' && $filterObj->value !== '') {
                            $query->where('clinic_id', $filterObj->value);
                        } elseif ($filterObj->columnName === 'id') {
                            $query->where('identity', 'like', '%' .  $filterObj->value . '%');
                        } elseif ($filterObj->columnName === 'profession') {
                            $query->where('profession_id', $filterObj->value);
                        } else {
                            $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                        }
                    }
                });
            }

            $users = $query->paginate($data['page_size']);
            $info = [
                'current_page' => $users->currentPage(),
                'total_count' => $users->total()
            ];
        }
        return ['success' => true, 'data' => UserResource::collection($users), 'info' => $info];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void
     */
    public function store(Request  $request)
    {
        $email = $request->get('email');
        $userExist = User::where('email', $email)->first();
        if ($userExist) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.email_exists');
        }

        DB::beginTransaction();
        $keycloakTherapistUuid = null;

        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $country = $request->get('country');
        $limitPatient = $request->get('limit_patient');
        $clinic = $request->get('clinic');
        $language = $request->get('language_id');
        $profession = $request->get('profession');
        $countryIdentity = $request->get('country_identity');
        $clinicIdentity = $request->get('clinic_identity');
        $languageCode = $request->get('language_code');

        $therapist = User::create([
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'country_id' => $country,
            'limit_patient' => $limitPatient,
            'clinic_id' => $clinic,
            'language_id' => $language,
            'profession_id' => $profession
        ]);

        if (!$therapist) {
            return ['success' => false, 'message' => 'error_message.user_add'];
        }

        try {
            $this->createKeycloakTherapist($therapist, $email, true, 'therapist', $languageCode);

            // Create unique identity.
            $identity = 'T' . $countryIdentity . $clinicIdentity .
                str_pad($therapist->id, 4, '0', STR_PAD_LEFT);

            // Create chat user.
            $updateData = $this->createChatUser($identity, $email, $lastName . ' ' . $firstName);

            $updateData['identity'] = $identity;
            $therapist->fill($updateData);
            $therapist->save();
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        DB::commit();
        return ['success' => true, 'message' => 'success_message.user_add'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return array
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $data = $request->all();
            $dataUpdate = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ];

            if (isset($data['language_id'])) {
                $dataUpdate['language_id'] = $data['language_id'];
            }
            if (isset($data['profession'])) {
                $dataUpdate['profession_id'] = $data['profession'];
            }
            if (isset($data['limit_patient'])) {
                $dataUpdate['limit_patient'] = $data['limit_patient'];
            }
            if ($data['language_code']) {
                try {
                    $this->updateUserLocale($user->email, $data['language_code']);
                } catch (\Exception $e) {
                    return ['success' => false, 'message' => $e->getMessage()];
                }
            }

            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
    }

    /**
     * @param Request $request
     * @param \App\Models\User $user
     * @return array
     */
    public function updateStatus(Request $request, User $user)
    {
        try {
            $enabled = $request->boolean('enabled');
            $token = KeycloakHelper::getKeycloakAccessToken();
            $userUrl = KEYCLOAK_USERS . '?email=' . $user->email;
            $user->update(['enabled' => $enabled]);

            $response = Http::withToken($token)->get($userUrl);
            $keyCloakUsers = $response->json();
            $url = KEYCLOAK_USERS . '/' . $keyCloakUsers[0]['id'];

            $userUpdated = Http::withToken($token)
                ->put($url, ['enabled' => $enabled]);

            if ($userUpdated) {
                return ['success' => true, 'message' => 'success_message.user_update'];
            }
            return ['success' => false, 'message' => 'error_message.user_update'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param integer $id
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            $token = KeycloakHelper::getKeycloakAccessToken();

            $userUrl = KEYCLOAK_USERS . '?email=' . $user->email;
            $response = Http::withToken($token)->get($userUrl);

            if ($response->successful()) {
                $keyCloakUsers = $response->json();

                KeycloakHelper::deleteUser($token, $keyCloakUsers[0]['id']);
                $user->delete();

                return ['success' => true, 'message' => 'success_message.therapist_delete'];
            }

            return ['success' => false, 'message' => 'error_message.therapist_delete'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param User $therapist
     * @param string $password
     * @param boolean $isTemporaryPassword
     * @param string $userGroup
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    private static function createKeycloakTherapist($therapist, $password, $isTemporaryPassword, $userGroup, $languageCode)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        if ($token) {
            try {
                $response = Http::withToken($token)->withHeaders([
                    'Content-Type' => 'application/json'
                ])->post(KEYCLOAK_USERS, [
                    'username' => $therapist->email,
                    'email' => $therapist->email,
                    'enabled' => true,
                    'firstName' => $therapist->first_name,
                    'lastName' => $therapist->last_name,
                    'attributes' => [
                        'locale' => [$languageCode ? $languageCode : '']
                    ],
                ]);

                if ($response->successful()) {
                    $createdUserUrl = $response->header('Location');
                    $lintArray = explode('/', $createdUserUrl);
                    $userKeycloakUuid = end($lintArray);
                    $isCanSetPassword = true;
                    if ($password) {
                        $isCanSetPassword = KeycloakHelper::resetUserPassword(
                            $token,
                            $createdUserUrl,
                            $password,
                            $isTemporaryPassword
                        );
                    }

                    $isCanAssignUserToGroup = self::assignUserToGroup($token, $createdUserUrl, $userGroup);
                    if ($isCanSetPassword && $isCanAssignUserToGroup) {
                        self::sendEmailToNewUser($userKeycloakUuid);
                        return $userKeycloakUuid;
                    }
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
        throw new \Exception('no_token');
    }

    /**
     * @param string $token
     * @param string $userUrl
     * @param string $userGroup
     * @param false $isUnassigned
     *
     * @return bool
     */
    private static function assignUserToGroup($token, $userUrl, $userGroup, $isUnassigned = false)
    {
        $userGroups = KeycloakHelper::getUserGroup($token);
        $url = $userUrl . '/groups/' . $userGroups[$userGroup];
        if ($isUnassigned) {
            $response = Http::withToken($token)->delete($url);
        } else {
            $response = Http::withToken($token)->put($url);
        }
        if ($response->successful()) {
            return true;
        }

        return false;
    }

    /**
     * @param string $username
     * @param string $email
     * @param string $name
     *
     * @return array
     * @throws \Exception
     */
    private static function createChatUser($username, $email, $name)
    {
        $password = $username . 'PWD';
        $chatUser = [
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'joinDefaultChannels' => false,
            'verified' => true,
            'active' => false
        ];
        $chatUserId = RocketChatHelper::createUser($chatUser);
        if (is_null($chatUserId)) {
            throw new \Exception('error_message.create_chat_user');
        }
        return [
            'chat_user_id' => $chatUserId,
            'chat_password' => hash('sha256', $password)
        ];
    }

    /**
     * @param int $userId
     *
     * @return \Illuminate\Http\Client\Response
     */
    public static function sendEmailToNewUser($userId)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $url = KEYCLOAK_USER_URL . '/'. $userId . KEYCLOAK_EXECUTE_EMAIL;
        $response = Http::withToken($token)->put($url, ['UPDATE_PASSWORD']);

        return $response;
    }

    /**
     * @param User $user
     *
     * @return \Illuminate\Http\Client\Response
     */
    public function resendEmailToUser(User $user)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        $response = Http::withToken($token)->withHeaders([
            'Content-Type' => 'application/json'
        ])->get(KEYCLOAK_USERS, [
            'username' => $user->email,
        ]);

        if ($response->successful()) {
            $userUid = $response->json()[0]['id'];
            $isCanSend = self::sendEmailToNewUser($userUid);

            if ($isCanSend) {
                return ['success' => true, 'message' => 'success_message.resend_email'];
            }

        }

        return ['success' => false, 'message' => 'error_message.cannot_resend_email'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $users = User::whereIn('id', json_decode($request->get('ids', [])))->get();
        return ['success' => true, 'data' => TherapistResource::collection($users)];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByClinicId(Request $request)
    {
        $users = User::where('clinic_id', $request->get('clinic_id'))->where('enabled', 1)->get();

        return ['success' => true, 'data' => TherapistResource::collection($users)];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getUsedProfession(Request $request)
    {
        $professionId = $request->get('profession_id');
        $therapists = User::where('profession_id', $professionId)->count();

        return $therapists > 0 ? true : false;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteChatRoomById(Request $request) {
        $chatRoomId = $request->get('chat_room_id');
        $therapistId = $request->get('therapist_id');

        $therapist = User::where('id', $therapistId)->first();
        $chatRooms = $therapist['chat_rooms'];
        if (($key = array_search($chatRoomId, $chatRooms)) !== false) {
            unset($chatRooms[$key]);
        }

        $updateData['chat_rooms'] = $chatRooms;
        $therapist->fill($updateData);
        $therapist->save();

        return ['success' => true, 'message' => 'success_message.deleted_chat_rooms'];
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

                $response = Http::withToken($token)->put($url, [
                    'attributes' => [
                        'locale' => [$languageCode]
                    ]
                ]);

                return $response->successful();
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }

        throw new \Exception('no_token');
    }
}
