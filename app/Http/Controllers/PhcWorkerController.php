<?php

namespace App\Http\Controllers;

use App\Helpers\CryptHelper;
use App\Helpers\KeycloakHelper;
use App\Helpers\RocketChatHelper;
use App\Http\Resources\PatientPhcWorkerResource;
use App\Http\Resources\PhcWorkerChatroomResource;
use App\Http\Resources\PhcWorkerListResource;
use App\Http\Resources\PhcWorkerOptionResource;
use App\Models\Forwarder;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VideoGrant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PhcWorkerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/phc-workers",
     *     tags={"PhcWorker"},
     *     summary="Lists all phc workers",
     *     operationId="phcWorkerList",
     *     @OA\Parameter(
     *         name="phc_service_id",
     *         in="query",
     *         description="PHC Service id",
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

        if (isset($data['id'])) {
            $users = User::where('id', $data['id'])->get();
        } else {
            $query = User::query()->where('email', '!=', env('KEYCLOAK_BACKEND_USERNAME'))->where('type', User::TYPE_PHC_WORKER);

            if ($request->has('country_id')) {
                $query->where('country_id', $request->get('country_id'));
            }

            if ($request->has('province_id')) {
                $query->where('province_id', $request->get('province_id'));
            }

            if ($request->has('phc_service_id')) {
                $query->where('phc_service_id', $request->get('phc_service_id'));
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
                        } elseif ($filterObj->columnName === 'phc_country' && $filterObj->value !== '') {
                            $query->where('country_id', $filterObj->value);
                        } elseif ($filterObj->columnName === 'id') {
                            $query->where('identity', 'like', '%' . $filterObj->value . '%');
                        } elseif ($filterObj->columnName === 'profession') {
                            $query->where('profession_id', $filterObj->value);
                        } elseif ($filterObj->columnName === 'limit_patient') {
                            $query->where('limit_patient', $filterObj->value);
                        } else {
                            $query->where($filterObj->columnName, 'like', '%' . $filterObj->value . '%');
                        }
                    }
                });
            }

            $users = $query->paginate($data['page_size']);
        }

        return response()->json(['data' => PhcWorkerListResource::collection($users), 'current_page' => $users->currentPage(), 'total_count' => $users->total()]);
    }

    /**
     * @OA\Post(
     *     path="/api/phc-workers",
     *     tags={"PhcWorker"},
     *     summary="Create phc worker",
     *     operationId="createPhcWorker",
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
     *         name="profession_id",
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
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'limit_patient' => 'required|integer',
        ],
        [
            'email.unique' => 'error_message.email_exists',
        ]);

        $email = $request->get('email');
        $phone = $request->get('phone');
        $dialCode = $request->get('dial_code');
        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $limitPatient = $request->get('limit_patient');
        $languageId = $request->get('language_id');
        $professionId = $request->get('profession_id');
        $languageCode = $request->get('language_code');
        $user = Auth::user();
        DB::beginTransaction();
        $phcWorker = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'dial_code' => $dialCode,
            'country_id' => $user->country_id,
            'region_id' => $user->region_id,
            'province_id' => $user->province_id,
            'limit_patient' => $limitPatient,
            'phc_service_id' => $user->phc_service_id,
            'type' => User::TYPE_PHC_WORKER,
            'profession_id' => $professionId,
            'language_id' => $languageId,
        ]);

        if (!$phcWorker) {
            return response()->json(['message' => 'error_message.phc_worker_create']);
        }

        $response = Http::withToken(Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE))->get(env('GADMIN_SERVICE_URL') . '/get-organization', ['sub_domain' => env('APP_NAME')]);

        if ($response->successful()) {
            $organization = $response->json();
        } else {
            return response()->json(['message' => 'error_message.organization_not_found']);
        }

        try {
            $userKeycloakUuid = $this->createKeycloakPhcWorker($phcWorker, $languageCode, User::GROUP_PHC_WORKER);
            $countryIdentity = $request->get('country_identity');
            $phcServiceIdentity = $request->get('phc_service_identity');
            // Create unique identity.
            $orgIdentity = str_pad($organization['id'], 4, '0', STR_PAD_LEFT);
            $identity = 'PHC' . $orgIdentity . $countryIdentity . $phcServiceIdentity .
                str_pad($phcWorker->id, 5, '0', STR_PAD_LEFT);

            // Create chat user.
            $updateData = $this->createChatUser($identity, $email, $lastName . ' ' . $firstName);

            $updateData['identity'] = $identity;
            $phcWorker->fill($updateData);
            $phcWorker->save();
            $federatedDomains = array_map(fn($d) => strtolower(trim($d)), explode(',', env('FEDERATED_DOMAINS', '')));
            $lowerCaseEmail = strtolower($email);
            if (Str::endsWith($lowerCaseEmail, $federatedDomains)) {
                $emailSendingData = [
                    'subject' => 'Welcome to OpenTeleRehab',
                    'name' => $lastName . ' ' . $firstName,
                    'link' => env('REACT_APP_BASE_URL')
                ];

                Mail::send('federatedUser.mail', $emailSendingData, function ($message) use ($email, $emailSendingData) {
                    $message->to($email)
                        ->subject($emailSendingData['subject']);
                });
            } else {
                $this->sendEmailToNewUser($userKeycloakUuid);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()]);
        }

        DB::commit();

        return response()->json(['message' => 'success_message.phc_worker_create'], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/phc-workers/{id}",
     *     tags={"Phc Worker"},
     *     summary="Update phc worker",
     *     operationId="updatePhcWorker",
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
     *         name="profession_id",
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
            $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'limit_patient' => 'required|integer',
            ]);
            $dataUpdate = [
                'first_name' => $request->get('first_name'),
                'last_name' => $request->get('last_name'),
                'limit_patient' => $request->get('limit_patient'),
                'phone' => $request->get('phone'),
                'dial_code' => $request->get('dial_code'),
                'profession_id' => $request->get('profession_id'),
                'language_id' => $request->get('language_id'),
            ];
            if ($request->has('language_code')) {
                try {
                    $this->updateUserLocale($user->email, $request->get('language_code'));
                } catch (\Exception $e) {
                    return response()->json(['message' => $e->getMessage()], 500);
                }
            }

            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json(['message' => 'success_message.phc_worker_update'], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/phc-workers/updateStatus/{user}",
     *     tags={"PhcWorker"},
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
            $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $user->email;
            $user->update(['enabled' => $enabled]);

            // Create rocketchat room.
            User::where('phc_service_id', $user->phc_service_id)
                ->where('id', '!=', $user->id)
                ->where('enabled', 1)
                ->get()
                ->map(function ($phcWorker) use ($user) {
                    try {
                        RocketChatHelper::createChatRoom($user->identity, $phcWorker->identity);
                    } catch (\Exception $e) {
                        return $e->getMessage();
                    }
                });

            $response = Http::withToken($token)->get($userUrl);
            $keyCloakUsers = $response->json();
            $url = KeycloakHelper::getUserUrl() . '/' . $keyCloakUsers[0]['id'];

            $userUpdated = Http::withToken($token)
                ->put($url, ['enabled' => $enabled]);

            if ($userUpdated) {
                return response()->json(['message' => 'success_message.phc_worker_update_status'], 200);
            }
            return response()->json(['message' => 'error_message.phc_worker_update_status'], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
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
            $user['identity'] . '_' . $user['country_id'],
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
     *
     * @return array
     */
    public function deleteByUserId(User $user)
    {
        try {
            //TODO: Remove patients of that phc worker.
            // Remove own created treatment preset.
            TreatmentPlan::where('created_by', $user->id)->delete();

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

            return response()->json(['message' => 'success_message.phc_worker_delete'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteByPhcServiceId(Request $request)
    {
        $phcServiceId = $request->get('phc_service_id');
        $users = User::where('phc_service_id', $phcServiceId)->get();
        if (count($users) > 0) {
            foreach ($users as $user) {
                $token = KeycloakHelper::getKeycloakAccessToken();

                $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $user->email;
                $response = Http::withToken($token)->get($userUrl);

                if ($response->successful()) {
                    $keyCloakUsers = $response->json();

                    KeycloakHelper::deleteUser($token, $keyCloakUsers[0]['id']);
                    $user->delete();
                }
            }
        }

        return ['success' => true, 'message' => 'success_message.phc_worker_delete'];
    }

    /**
     * @param \App\Models\User $phcWorker
     * @param string $languageCode
     *
     * @return false|mixed|string
     * @throws \Exception
     */
    private static function createKeycloakPhcWorker($phcWorker, $languageCode, $group)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();

        if ($token) {
            try {
                $keycloakUsersResponse = Http::withToken($token)->get(KeycloakHelper::getUserUrl(), ['email' => $phcWorker->email, 'exact' => 'true']);
                $userExists = null;
                if ($keycloakUsersResponse->successful()) {
                    $userExists = $keycloakUsersResponse->json();
                }

                $data = [
                    'username' => $phcWorker->email,
                    'email' => $phcWorker->email,
                    'enabled' => true,
                    'firstName' => $phcWorker->first_name,
                    'lastName' => $phcWorker->last_name,
                    'attributes' => [
                        'locale' => [$languageCode ?: '']
                    ],
                ];

                if ($userExists && count($userExists) > 0) {
                    $userKeycloakUuid = $userExists[0]['id'];

                    $updateResponse = Http::withToken($token)
                        ->withHeaders(['Content-Type' => 'application/json'])
                        ->put(KeycloakHelper::getUserUrl() . '/' . $userKeycloakUuid, $data);

                    if ($updateResponse->successful()) {
                        return $userKeycloakUuid;
                    }
                } else {
                    $response = Http::withToken($token)->withHeaders([
                        'Content-Type' => 'application/json'
                    ])->post(KeycloakHelper::getUserUrl(), $data);


                    if ($response->successful()) {
                        $createdUserUrl = $response->header('Location');
                        $lintArray = explode('/', $createdUserUrl);
                        $userKeycloakUuid = end($lintArray);
                        $isCanAssignUserToGroup = self::assignUserToGroup($token, $createdUserUrl, $group);
                        if ($isCanAssignUserToGroup) {
                            return $userKeycloakUuid;
                        }
                    }
                }
                throw new \Exception('Failed to create Keycloak user');
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
        $password = bin2hex(random_bytes(16));
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
            'chat_password' => CryptHelper::encrypt($password)
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

        $url = KeycloakHelper::getUserUrl() . '/' . $userId . KeycloakHelper::getExecuteEmailUrl();
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
        ])->get(KeycloakHelper::getUserUrl(), [
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
        return ['success' => true, 'data' => PhcWorkerListResource::collection($users)];
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
     *     path="/api/phc-workers/list/by-phc-service-id",
     *     tags={"PhcWorker"},
     *     summary="Lists all phc workers by phc service",
     *     operationId="phcWorkerListByPhcService",
     *     @OA\Parameter(
     *         name="phc_service_id",
     *         in="query",
     *         description="PHC service id",
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
    public function getByPhcServiceId(Request $request)
    {
        $phcServiceId = $request->get('phc_service_id') ?? Auth::user()->phc_service_id;
        $users = User::where('phc_service_id', $phcServiceId)->where('enabled', 1)->get();

        return ['success' => true, 'data' => PhcWorkerOptionResource::collection($users)];
    }


    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function listForChatroom(Request $request)
    {
        $phcServiceId = $request->get('phc_service_id') ?? Auth::user()->phc_service_id;
        $users = User::where('phc_service_id', $phcServiceId)->where('enabled', 1)->get();

        return ['success' => true, 'data' => PhcWorkerChatroomResource::collection($users)];
    }

    /**
     * @OA\Get(
     *     path="/api/phc-workers/list/by-country-id",
     *     tags={"PhcWorker"},
     *     summary="Lists all phc workers by country",
     *     operationId="phcWorkerListByCountry",
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
        $users = User::where('country_id', $request->get('country_id'))->where('enabled', 1)->where('type', User::TYPE_PHC_WORKER)->get();

        return ['success' => true, 'data' => PhcWorkerListResource::collection($users)];
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getUsedProfession(Request $request)
    {
        $professionId = $request->get('profession_id');
        $phcWorkers = User::where('profession_id', $professionId)->count();

        return $phcWorkers > 0;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function deleteChatRoomById(Request $request)
    {
        $chatRoomId = $request->get('chat_room_id');
        $phcWorkerId = $request->get('phc_worker_id');

        $phcWorker = User::where('id', $phcWorkerId)->first();
        $chatRooms = $phcWorker['chat_rooms'];
        if (($key = array_search($chatRoomId, $chatRooms)) !== false) {
            unset($chatRooms[$key]);
        }

        $updateData['chat_rooms'] = $chatRooms;
        $phcWorker->fill($updateData);
        $phcWorker->save();

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
                $userUrl = KeycloakHelper::getUserUrl() . '?email=' . $email;
                $response = Http::withToken($token)->get($userUrl);
                $keyCloakUsers = $response->json();
                 if (empty($keyCloakUsers)) {
                    throw new \Exception("User not found in Keycloak");
                }
                $user = $keyCloakUsers[0];
                $url = KeycloakHelper::getUserUrl() . '/' . $user['id'];
                $user['attributes']['locale'] = [$languageCode];
                $response = Http::withToken($token)->put($url, $user);

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
    public function getPhcWorkerPatientLimit(Request $request)
    {
        $phcWorker = User::find($request['phc_worker_id']);
        return $phcWorker ? $phcWorker->limit_patient : 0;
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

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getPatientPhcWorkerByIds(Request $request)
    {
        $ids = json_decode($request->get('ids', []));
        $users = User::whereIn('id', $ids)->get();

        return PatientPhcWorkerResource::collection($users);
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function countPhcWorkerByPhcService(Request $request)
    {
        $phcServiceId = $request->get('phc_service_id');
        $phcWorkerTotal = User::where('phc_service_id', $phcServiceId)->where('enabled', 1)->where('type', User::TYPE_PHC_WORKER)->count();

        return [
            'data' => $phcWorkerTotal
        ];
    }
}
