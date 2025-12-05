<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ForwarderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Client\Response
     */
    public function index(Request $request)
    {
        $service_name = $request->route()->getName();
        $country = $request->header('country');
        $endpoint = str_replace('api/', '/', $request->path());
        $params = $request->all();
        $user = Auth::user();

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->get(env('GADMIN_SERVICE_URL') . $endpoint, $params);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            $params['country_id'] ??= $user->country_id;

            return Http::withToken($access_token)->withHeaders([
                'int-country-id' => $user->country_id,
                'int-region-id' => $user?->region_id,
                'int-user-type' => $user?->type,
            ])->get(env('ADMIN_SERVICE_URL') . $endpoint, $params);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            $response = Http::withToken($access_token)->withHeaders([
                'country' => $country,
                'int-user-type' => $user?->type,
                'int-therapist-user-id' => $user?->id,
            ])->get(env('PATIENT_SERVICE_URL') . $endpoint, $params);
            return response($response->body(), $response->status())
                ->withHeaders([
                    'Content-Type' => $response->header('Content-Type'),
                    'Content-Disposition' => $response->header('Content-Disposition'),
                ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $service_name = $request->route()->getName();
        $country = $request->header('country');
        $endpoint = str_replace('api/', '/', $request->path());

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->post(env('GADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            $response = Http::withToken($access_token);

            $multipart = [];

            // Handle regular form inputs (including arrays)
            foreach ($request->all() as $key => $value) {
                // Skip files
                if ($request->hasFile($key)) {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $subValue) {
                        $multipart[] = [
                            'name' => "{$key}[]",
                            'contents' => (string)$subValue,
                        ];
                    }
                } else {
                    $multipart[] = [
                        'name' => (string)$key,
                        'contents' => (string)$value,
                    ];
                }
            }

            // Handle uploaded files
            foreach ($request->allFiles() as $key => $file) {
                // Support multiple files (array inputs)
                if (is_array($file)) {
                    foreach ($file as $subFile) {
                        $multipart[] = [
                            'name' => "{$key}[]",
                            'contents' => fopen($subFile->getRealPath(), 'r'),
                            'filename' => $subFile->getClientOriginalName(),
                        ];
                    }
                } else {
                    $multipart[] = [
                        'name' => (string)$key,
                        'contents' => fopen($file->getRealPath(), 'r'),
                        'filename' => $file->getClientOriginalName(),
                    ];
                }
            }

            return $response->asMultipart()->post(env('ADMIN_SERVICE_URL') . $endpoint, $multipart);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            return Http::withToken($access_token)->withHeaders([
                'country' => $country,
                'int-country-id' => $user->country_id,
                'int-region-id' => $user?->region_id,
                'int-province-id' => $user?->province_id,
                'int-clinic-id' => $user?->clinic_id,
                'int-phc-service-id' => $user?->phc_service_id,
                'int-user-type' => $user?->type,
                'int-therapist-user-id' => $user?->id,
            ])->post(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $service_name = $request->route()->getName();
        $country = $request->header('country');
        $endpoint = str_replace('api/', '/', $request->path());

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->get(env('GADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            return Http::withToken($access_token)->get(env('ADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            return Http::withToken($access_token)->withHeaders([
                'country' => $country,
            ])->get(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $service_name = $request->route()->getName();
        $country = $request->header('country');
        $endpoint = str_replace('api/', '/', $request->path());
        $user = Auth::user();

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->put(env('GADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            $response = Http::withToken($access_token);
            $multipart = [];

            // Handle regular form inputs (including arrays)
            foreach ($request->all() as $key => $value) {
                // Skip files
                if ($request->hasFile($key)) {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $subValue) {
                        $multipart[] = [
                            'name' => "{$key}[]",
                            'contents' => (string)$subValue,
                        ];
                    }
                } else {
                    $multipart[] = [
                        'name' => (string)$key,
                        'contents' => (string)$value,
                    ];
                }
            }

            // Handle uploaded files
            foreach ($request->allFiles() as $key => $file) {
                // Support multiple files (array inputs)
                if (is_array($file)) {
                    foreach ($file as $subFile) {
                        $multipart[] = [
                            'name' => "{$key}[]",
                            'contents' => fopen($subFile->getRealPath(), 'r'),
                            'filename' => $subFile->getClientOriginalName(),
                        ];
                    }
                } else {
                    $multipart[] = [
                        'name' => (string)$key,
                        'contents' => fopen($file->getRealPath(), 'r'),
                        'filename' => $file->getClientOriginalName(),
                    ];
                }
            }

            return $response->asMultipart()->post(env('ADMIN_SERVICE_URL') . $endpoint, $multipart);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            return Http::withToken($access_token)->withHeaders([
                'country' => $country,
                'int-therapist-user-id' => $user->id,
            ])->put(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $service_name = $request->route()->getName();
        $country = $request->header('country');
        $endpoint = str_replace('api/', '/', $request->path());
        $user = Auth::user();

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->delete(env('GADMIN_SERVICE_URL') . $endpoint);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            return Http::withToken($access_token)->delete(env('ADMIN_SERVICE_URL') . $endpoint);
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            return Http::withToken($access_token)->withHeaders([
                'country' => $country,
                'int-therapist-user-id' => $user->id,
            ])->delete(env('PATIENT_SERVICE_URL') . $endpoint);
        }
    }
}
