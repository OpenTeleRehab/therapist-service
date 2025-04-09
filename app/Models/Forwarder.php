<?php

namespace App\Models;

use App\Helpers\KeycloakHelper;
use Illuminate\Database\Eloquent\Model;

class Forwarder extends Model
{
    const GADMIN_SERVICE = 'global_admin';
    const ADMIN_SERVICE = 'admin';
    const PATIENT_SERVICE = 'patient';
    const THERAPIST_SERVICE = 'therapist';

    /**
     * @param string $service_name
     * @param string|null $host
     *
     * @return mixed
     */
    public static function getAccessToken($service_name, $host = null)
    {
        if ($service_name === self::GADMIN_SERVICE) {
            return KeycloakHelper::getGAdminKeycloakAccessToken();
        }

        if ($service_name === self::ADMIN_SERVICE) {
            return KeycloakHelper::getAdminKeycloakAccessToken();
        }

        if ($service_name === self::PATIENT_SERVICE) {
            return KeycloakHelper::getPatientKeycloakAccessToken($host);
        }
    }
}
