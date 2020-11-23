<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class TherapistController extends Controller
{

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $users = User::all();

        return ['success' => true, 'data' => UserResource::collection($users)];
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

        try {
            $firstName = $request->get('first_name');
            $lastName = $request->get('last_name');
            $email = $request->get('email');
            $country = $request->get('country');
            $limitPatient = $request->get('limit_patient');
            $clinic = $request->get('clinic');
            $language = $request->get('language');
            $profession = $request->get('profession');

            $availableEmail = User::where('email', $email)->count();
            if ($availableEmail) {
                return abort(409);
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

            // Todo create function in model to generate this identity.
            $identity = $therapist->country_id . $therapist->clinic_id . str_pad($therapist->id, 4, '0', STR_PAD_LEFT);
            $therapist->fill(['identity' => $identity]);
            $therapist->save();

            // Create keycloak therapist.
            $keycloakTherapistUuid = $this->createKeycloakTherapist($therapist, 'therapist');

            if (!$therapist || !$keycloakTherapistUuid) {
                DB::rollBack();
                return abort(500);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return ['error message' => $e->getMessage()];
        }

        DB::commit();
        return ['success' => true, 'message' => 'success_message.user_add'];
    }

    /**
     * @param User $therapist
     * @param string $therapistGroup
     *
     * @return false|mixed|string
     */
    private function createKeycloakTherapist($therapist, $therapistGroup)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        if ($token) {
            $response = Http::withToken($token)->withHeaders([
                'Content-Type' => 'application/json'
            ])->post(KEYCLOAK_USERS, [
                'username' => $therapist->email,
                'email' => $therapist->email,
                'enabled' => true,
            ]);

            if ($response->successful()) {
                $createdTherapistUrl = $response->header('Location');
                $lintArray = explode('/', $createdTherapistUrl);
                $therapistKeycloakUuid = end($lintArray);
                $isCanSetPassword = true;
                $isCanAssignTherapistToGroup = self::assignUserToGroup($token, $createdTherapistUrl, $therapistGroup);
                if ($isCanSetPassword && $isCanAssignTherapistToGroup) {
                    return $therapistKeycloakUuid;
                }
            }
        }
        return false;
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
        $userGroups = KeycloakHelper::getTherapistGroups($token);
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
}
