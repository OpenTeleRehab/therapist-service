<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use Illuminate\Http\Request;
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
                'country' => $country
            ])->get(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function store(Request $request)
    {
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

            foreach ($request->allFiles() as $key => $file) {
                if (str_contains($request->path(), 'education-material')) {
                    $response = $response->attach($key, file_get_contents($file), $file->getClientOriginalName());
                } else {
                    $response = $response->attach($file->getClientOriginalName(), file_get_contents($file), $file->getClientOriginalName());
                }
            }

            return $response->post(env('ADMIN_SERVICE_URL') . $endpoint, $request->input());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            return Http::withToken($access_token)->withHeaders([
                'country' => $country,
            ])->post(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
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
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $service_name = $request->route()->getName();
        $country = $request->header('country');
        $endpoint = str_replace('api/', '/', $request->path());

        if ($service_name !== null && str_contains($service_name, Forwarder::GADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::GADMIN_SERVICE);
            return Http::withToken($access_token)->put(env('GADMIN_SERVICE_URL') . $endpoint, $request->all());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::ADMIN_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);
            $response = Http::withToken($access_token);

            foreach ($request->allFiles() as $key => $file) {
                if (str_contains($request->path(), 'education-material')) {
                    $response = $response->attach($key, file_get_contents($file), $file->getClientOriginalName());
                } else {
                    $response = $response->attach($file->getClientOriginalName(), file_get_contents($file), $file->getClientOriginalName());
                }
            }

            return $response->post(env('ADMIN_SERVICE_URL') . $endpoint, $request->input());
        }

        if ($service_name !== null && str_contains($service_name, Forwarder::PATIENT_SERVICE)) {
            $access_token = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE, $country);
            return Http::withToken($access_token)->withHeaders([
                'country' => $country,
            ])->put(env('PATIENT_SERVICE_URL') . $endpoint, $request->all());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \GuzzleHttp\Promise\PromiseInterface|\Illuminate\Http\Client\Response|\Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $service_name = $request->route()->getName();
        $country = $request->header('country_code');
        $endpoint = str_replace('api/', '/', $request->path());

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
            ])->delete(env('PATIENT_SERVICE_URL') . $endpoint);
        }
    }
}
