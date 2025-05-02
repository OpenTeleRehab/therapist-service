<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Helpers\RocketChatHelper;
use App\Http\Resources\TherapistResource;
use App\Http\Resources\UserResource;
use App\Models\Forwarder;
use App\Models\Transfer;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');
define("KEYCLOAK_EXECUTE_EMAIL", '/execute-actions-email?client_id=' . env('KEYCLOAK_BACKEND_CLIENT') . '&redirect_uri=' . env('REACT_APP_BASE_URL'));

class TherapistController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/therapist",
     *     tags={"Therapist"},
     *     summary="Lists all therapists",
     *     operationId="therapistList",
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page_size",
     *         in="query",
     *         description="Limit",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
    public function index(Request $request)
    {
        $data = $request->all();
        $info = [];

        if (isset($data['id'])) {
            $users = User::where('id', $data['id'])->get();
        } else {
            $query = User::query()->where('email', '!=', env('KEYCLOAK_BACKEND_USERNAME'));

            if ($request->has('clinic_id')) {
                $query->where('clinic_id', $request->get('clinic_id'));
            }

            if ($request->has('country_id')) {
                $query->where('country_id', $request->get('country_id'));
            }

            if (isset($data['user_type']) && $data['user_type'] === User::ADMIN_GROUP_ORGANIZATION_ADMIN) {
                if (isset($data['search_value'])) {
                    $query->where(function ($query) use ($data) {
                        $query->where('identity', 'like', '%' . $data['search_value'] . '%');
                    });
                }
            } else {
                if (isset($data['search_value'])) {
                    $query->where(function ($query) use ($data) {
                        $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                            ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                            ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
                            ->orWhere('email', 'like', '%' . $data['search_value'] . '%');
                    });
                }
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
                        } elseif ($filterObj->columnName === 'limit_patient') {
                            $query->where('limit_patient', $filterObj->value);
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
     * @OA\Post(
     *     path="/api/therapist",
     *     tags={"Therapist"},
     *     summary="Create therapist",
     *     operationId="createTherapist",
     *     @OA\Parameter(
     *         name="email",
     *         in="query",
     *         description="Email",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Country_id",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="limit_patient",
     *         in="query",
     *         description="Limit patient",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="clinic",
     *         in="query",
     *         description="clinic_id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language_id",
     *         in="query",
     *         description="Language id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="profession",
     *         in="query",
     *         description="Profession id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
     * @return array|void
     */
    public function store(Request $request)
    {
        $authUser = Auth::user();
        $email = $request->get('email');
        $clinic = $request->get('clinic');

        $users = User::where('clinic_id', $clinic)->get();

        if (User::where('email', $email)->exists()) {
            return ['success' => false, 'message' => 'error_message.email_exists'];
        }

        DB::beginTransaction();

        $phone = $request->get('phone');
        $dialCode = $request->get('dial_code');
        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $country = $request->get('country');
        $limitPatient = $request->get('limit_patient');
        $language = $request->get('language_id');
        $profession = $request->get('profession');
        $countryIdentity = $request->get('country_identity');
        $clinicIdentity = $request->get('clinic_identity');
        $languageCode = $request->get('language_code');

        $therapist = User::create([
            'email' => $email,
            'phone' => $phone,
            'dial_code' => $dialCode,
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

        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE))->get(env('GADMIN_SERVICE_URL') . '/get-organization', ['sub_domain' => env('APP_NAME')]);

        if ($response->successful()) {
            $organization = $response->json();
        } else {
            return ['success' => false, 'message' => 'error_message.organization_not_found'];
        }

        try {
            $userKeycloakUuid = $this->createKeycloakTherapist($therapist, $languageCode);

            // Create unique identity.
            $orgIdentity = str_pad($organization['id'], 4, '0', STR_PAD_LEFT);
            $identity = 'T' . $orgIdentity . $countryIdentity . $clinicIdentity .
                str_pad($therapist->id, 5, '0', STR_PAD_LEFT);

            // Create chat user.
            $updateData = $this->createChatUser($identity, $email, $lastName . ' ' . $firstName);

            $updateData['identity'] = $identity;
            $therapist->fill($updateData);
            $therapist->save();
            $this->sendEmailToNewUser($userKeycloakUuid);

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }

        DB::commit();

        return ['success' => true, 'message' => 'success_message.user_add'];
    }

    /**
     * @OA\Put(
     *     path="/api/therapist/{id}",
     *     tags={"Therapist"},
     *     summary="Update therapist",
     *     operationId="updateTherapist",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language_code",
     *         in="query",
     *         description="Language code",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="first_name",
     *         in="query",
     *         description="First name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="last_name",
     *         in="query",
     *         description="Last name",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="limit_patient",
     *         in="query",
     *         description="Limit patient",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="language_id",
     *         in="query",
     *         description="Language id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="profession",
     *         in="query",
     *         description="Profession id",
     *         required=false,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
                'phone' => $data['phone'],
                'dial_code' => $data['dial_code'],
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
            if (isset($data['language_code'])) {
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
     * @OA\Post(
     *     path="/api/therapist/updateStatus/{user}",
     *     tags={"Therapist"},
     *     summary="Update user status",
     *     operationId="updateUserStatus",
     *     @OA\Parameter(
     *         name="user",
     *         in="path",
     *         description="User id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="enabled",
     *         in="query",
     *         description="Enabled",
     *         required=true,
     *         @OA\Schema(
     *             type="boolean"
     *         )
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

            // Create rocketchat room.
            User::where('clinic_id', $user->clinic_id)
                ->where('id', '!=', $user->id)
                ->where('enabled', 1)
                ->get()
                ->map(function ($therapist) use ($user) {
                    try {
                        RocketChatHelper::createChatRoom($user->identity, $therapist->identity);
                    } catch (\Exception $e) {
                        return $e->getMessage();
                    }
                });

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
     * @param Request $request
     *
     * @return array
     */
    public function getCallAccessToken(Request $request)
    {
        $twilioAccountSid = env('TWILIO_ACCOUNT_SID');
        $twilioApiKey = env('TWILIO_API_KEY');
        $twilioApiSecret = env('TWILIO_API_KEY_SECRET');

        $user = Auth::user();

        // Create access token, which we will serialize and send to the client.
        $token = new AccessToken(
            $twilioAccountSid,
            $twilioApiKey,
            $twilioApiSecret,
            3600,
            $user['identity']. '_' . $user['country_id'],
        );

        // Create Video grant.
        $videoGrant = new VideoGrant();
        $videoGrant->setRoom($request->room_id);

        // Add grant to token.
        $token->addGrant($videoGrant);

        return ['success' => true, 'token' => $token->toJWT()];
    }

    /**
     * @param \App\Models\User $user
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function deleteByUserId(User $user, Request $request)
    {
        try {
            $countryCode = $request->get('country_code');
            $hardDelete = $request->boolean('hard_delete');

            // Remove all active requests of patient transfer to other therapists
            Transfer::where('from_therapist_id', $user->id)->delete();

            // Decline all active requests of patient transfer from other therapists
            Transfer::where('to_therapist_id', $user->id)->update(['status' => Transfer::STATUS_DECLINED]);

            // Remove patients of therapist.
            Http::withHeaders([
                'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $countryCode),
                'country' => $countryCode,
            ])->post(env('PATIENT_SERVICE_URL') . '/patient/delete/by-therapist', [
                'therapist_id' => $user->id,
                'hard_delete' => $hardDelete,
            ]);

            // Remove own created libraries of therapist.
            Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
                ->post(env('ADMIN_SERVICE_URL') . '/library/delete/by-therapist', [
                    'therapist_id' => $user->id,
                    'hard_delete' => $hardDelete,
                ]);

            // Remove own created treatment preset.
            TreatmentPlan::where('created_by', $user->id)->delete();

            $token = KeycloakHelper::getKeycloakAccessToken();

            $userUrl = KEYCLOAK_USERS . '?email=' . $user->email;
            $response = Http::withToken($token)->get($userUrl);

            if ($response->successful()) {
                $keyCloakUsers = $response->json();

                KeycloakHelper::deleteUser($token, $keyCloakUsers[0]['id']);
                $user->delete();
            }

            return ['success' => true, 'message' => 'success_message.therapist_delete'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteByClinicId(Request $request)
    {
        $clinicId = $request->get('clinic_id');
        $users = User::where('clinic_id', $clinicId)->get();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $token = KeycloakHelper::getKeycloakAccessToken();

                $userUrl = KEYCLOAK_USERS . '?email=' . $user->email;
                $response = Http::withToken($token)->get($userUrl);

                if ($response->successful()) {
                    $keyCloakUsers = $response->json();

                    KeycloakHelper::deleteUser($token, $keyCloakUsers[0]['id']);
                    $user->delete();
                }
            }
        }

        return ['success' => true, 'message' => 'success_message.therapist_delete'];
    }

    /**
     * @param \App\Models\User $therapist
     * @param string $languageCode
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    private static function createKeycloakTherapist($therapist, $languageCode)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        if ($token) {
            try {
                $keycloakUsersResponse = Http::withToken($token)->get(KEYCLOAK_USERS, ['email' => $therapist->email]);
                $userExists = null;
                if ($keycloakUsersResponse->successful()) {
                    $userExists = $keycloakUsersResponse->json();
                }
                $data = [
                    'username' => $therapist->email,
                    'email' => $therapist->email,
                    'enabled' => true,
                    'firstName' => $therapist->first_name,
                    'lastName' => $therapist->last_name,
                    'attributes' => [
                        'locale' => [$languageCode ?: '']
                    ],
                ];

                if ($userExists && count($userExists) > 0) {
                    $userKeycloakUuid = $userExists[0]['id'];

                    $updateResponse = Http::withToken($token)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->put(KEYCLOAK_USERS . '/' . $userKeycloakUuid, $data);

                    if ($updateResponse->successful()) {
                        return $userKeycloakUuid;
                    }
                } else {
                    $response = Http::withToken($token)->withHeaders([
                        'Content-Type' => 'application/json'
                    ])->post(KEYCLOAK_USERS, $data);

                    if ($response->successful()) {
                        $createdUserUrl = $response->header('Location');
                        $lintArray = explode('/', $createdUserUrl);
                        $userKeycloakUuid = end($lintArray);
                        return $userKeycloakUuid;
                    }
                }
                throw new \Exception('Failed to crate Keycloak user');
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        }
        throw new \Exception('no_token');
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
     * @return mixed
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
    public function getById(Request $request)
    {
        $user = User::find($request->get('id'));
        return $user;
    }

    /**
     * @OA\Get(
     *     path="/api/therapist/list/by-clinic-id",
     *     tags={"Therapist"},
     *     summary="Lists all therapists by clinic",
     *     operationId="therapistListByClinic",
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
    public function getByClinicId(Request $request)
    {
        $users = User::where('clinic_id', $request->get('clinic_id'))->where('enabled', 1)->get();

        return ['success' => true, 'data' => TherapistResource::collection($users)];
    }

    /**
     * @OA\Get(
     *     path="/api/therapist/list/by-country-id",
     *     tags={"Therapist"},
     *     summary="Lists all therapists by country",
     *     operationId="therapistListByCountry",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
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
    public function getByCountryId(Request $request)
    {
        $users = User::where('country_id', $request->get('country_id'))->where('enabled', 1)->get();

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

        return $therapists > 0;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteChatRoomById(Request $request)
    {
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

    /**
     * @param Request $request
     * @return int
     */
    public function getTherapistPatientLimit(Request $request)
    {
        $therapist = User::find($request['therapist_id']);
        return $therapist ? $therapist->limit_patient : 0;
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function getPatientByPhoneNumber(Request $request)
    {
        $phone = $request->get('phone');
        $patientId = $request->get('patient_id');

        $existedPhoneOnGlobalDb = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE),
        ])->get(env('PATIENT_SERVICE_URL') . '/patient/count/by-phone-number', [
            'phone' => $phone,
            'patientId' => $patientId
        ]);

        $existedPhoneOnVN = Http::withHeaders([
            'Authorization' => 'Bearer ' . Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, config('settings.vn_country_iso')),
            'country' => config('settings.vn_country_iso')
        ])->get(env('PATIENT_SERVICE_URL') . '/patient/count/by-phone-number', [
            'phone' => $phone,
            'patientId' => $patientId
        ]);

        if (!empty($existedPhoneOnGlobalDb) && $existedPhoneOnGlobalDb->successful()) {
            $patientData = $existedPhoneOnGlobalDb->json();
        }

        if (!empty($existedPhoneOnVN) && $existedPhoneOnVN->successful()) {
            $patientDataVN = $existedPhoneOnVN->json();
        }

        $data = 0;

        if ($patientData > 0 || $patientDataVN > 0) {
            $data = 1;
        }

        return ['success' => true, 'data' => $data];
    }
}
