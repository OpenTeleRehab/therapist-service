<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Forwarder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class VerifyDataAccess
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return \Symfony\Component\HttpFoundation\Response
   */
    public function handle(Request $request, Closure $next): Response
    {
        $countryHeader = $request->header('Country');
        $countryId     = $request->get('country_id') ?? $request->get('country');
        $clinicId      = $request->get('clinic_id') ?? $request->get('clinic');
        $therapistId   = $request->get('therapist_id') ?? $request->get('therapist');

        $user = auth()->user();
         if ($user->email == env('KEYCLOAK_BACKEND_CLIENT')) {
            if (!empty($request->header('int-country-id'))) {
                $user->country_id = (int) $request->header('int-country-id');
            }

            if (!empty($request->header('int-region-id'))) {
                $user->region_id = (int) $request->header('int-region-id');
            }

            if (!empty($request->header('int-province-id'))) {
                $user->province_id = (int) $request->header('int-province-id');
            }

            if (!empty($request->header('int-clinic-id'))) {
                $user->clinic_id = (int) $request->header('int-clinic-id');
            }

            if (!empty($request->header('int-phc-service-id'))) {
                $user->phc_service_id = (int) $request->header('int-phc-service-id');
            }
        }
        $deny = fn() => response()->json(['message' => 'Access denied'], 403);

        // Early exit: skip validation
        if ($user && $user->email === env('KEYCLOAK_BACKEND_CLIENT')) {
            return $next($request);
        }

        // Verify if the auth user belongs to their assigned country
        if ($user && isset($countryId) && (int)$user->country_id !== (int)$countryId) {
            return $deny();
        }

        // Verify if the auth user belongs to their assigned clinic
        if ($user && isset($clinicId) && (int)$user->clinic_id !== (int)$clinicId) {
            return $deny();
        }

        // Verify if the auth user is the same as the requested therapist id
        if ($user && isset($therapistId) && (int)$user->id !== (int)$therapistId) {
            return $deny();
        }

        // Country header check
        if ($countryHeader) {
            $country = Cache::remember("country_iso_code_{$countryHeader}", 86400, function () use ($countryHeader) {
                $accessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
                $response = Http::withToken($accessToken)->get(
                    env('ADMIN_SERVICE_URL') . '/get-country-by-iso-code',
                    ['iso_code' => $countryHeader]
                );
                return $response->successful() ? $response->json('data') : null;
            });

            if (!$country || !isset($country['id'])) {
                return response()->json(['message' => 'Invalid or unrecognized country.'], 404);
            }

            if ($user && (int)$user->country_id !== (int)$country['id']) {
                return $deny();
            }
        }

        return $next($request);
    }
}
