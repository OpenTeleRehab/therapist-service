<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
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
        $query = User::where(function ($query) use ($data) {
            $query->where('identity', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('first_name', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('last_name', 'like', '%' . $data['search_value'] . '%')
                ->orWhere('email', 'like', '%' . $data['search_value'] . '%');
        });

        if (isset($data['filters'])) {
            $filters = $request->get('filters');
            $query->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);
                    $excludedColumns = ['country', 'clinic', 'assigned_patients'];
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
                        $query->where('created_at', '>=', $startDate)
                            ->where('created_at', '<=', $endDate);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        $users = $query->paginate($data['page_size']);
        $info = [
            'current_page' => $users->currentPage(),
            'total_count' => $users->total(),
        ];
        return ['success' => true, 'data' => UserResource::collection($users), 'info' => $info];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void
     */
    public function store(Request  $request)
    {
        DB::beginTransaction();
        $keycloakTherapistUuid = null;

        $firstName = $request->get('first_name');
        $lastName = $request->get('last_name');
        $email = $request->get('email');
        $country = $request->get('country');
        $limitPatient = $request->get('limit_patient');
        $clinic = $request->get('clinic');
        $language = $request->get('language');
        $profession = $request->get('profession');
        $countryIdentity = $request->get('country_identity');
        $clinicIdentity = $request->get('clinic_identity');

        $availableEmail = User::where('email', $email)->count();
        if ($availableEmail) {
            // Todo: message will be replaced.
            return abort(409, 'error_message.email_exists');
        }

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
            // Todo create function in model to generate this identity.
            $identity = 'T' . $countryIdentity . $clinicIdentity .
                str_pad($therapist->id, 4, '0', STR_PAD_LEFT);
            $therapist->fill(['identity' => $identity]);
            $therapist->save();

            $this->createKeycloakTherapist($therapist, $email, true, 'therapist');
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

            if (isset($data['language'])) {
                $dataUpdate['language_id'] = $data['language'];
            }
            if (isset($data['profession'])) {
                $dataUpdate['profession_id'] = $data['profession'];
            }
            if (isset($data['limit_patient'])) {
                $dataUpdate['limit_patient'] = $data['limit_patient'];
            }

            $user->update($dataUpdate);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'success_message.user_update'];
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
    private static function createKeycloakTherapist($therapist, $password, $isTemporaryPassword, $userGroup)
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
}
