<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class CountryHelper
{
    public static function getById($countryId)
    {
        $cacheKey = 'countries_list';

        if (Cache::has($cacheKey)) {
            $allCountries = Cache::get($cacheKey);
        } else {
            $response = Http::get(env('ADMIN_SERVICE_URL') . '/country');

            if ($response->failed()) {
                return null;
            }

            $allCountries = $response->json('data', []);

            Cache::put($cacheKey, $allCountries, Carbon::now()->addMinutes(10));
        }

        $country = collect($allCountries)->firstWhere('id', $countryId);

        return $country ?? null;
    }

    public static function getIsoCodeById($countryId)
    {
        $country = self::getById($countryId);

        return $country['iso_code'] ?? null;
    }
}
