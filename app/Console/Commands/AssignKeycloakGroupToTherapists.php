<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use App\Helpers\KeycloakHelper;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class AssignKeycloakGroupToTherapists extends Command
{
    protected $signature = 'keycloak:assign-group {group}';
    protected $description = 'Assign a Keycloak group to all users without a group';

    public function handle()
    {
        $group = $this->argument('group');
        $token = KeycloakHelper::getKeycloakAccessToken();

        if (!$token) {
            return $this->error('No Keycloak token.');
        }

        $groups = KeycloakHelper::getUserGroup($token);
        if (!isset($groups[$group])) {
            return $this->error("Group '{$group}' not found.");
        }

        $groupId = $groups[$group];

        foreach (User::all() as $user) {
            $kcUser = Http::withToken($token)->get(KEYCLOAK_USERS, ['email' => $user->email])->json()[0] ?? null;

            if ($user->email === env('KEYCLOAK_BACKEND_USERNAME')) {
                continue;
            }

            if (!$kcUser) {
                $this->warn("User not in Keycloak: {$user->email}");
                continue;
            }

            $userUrl = KEYCLOAK_USERS . '/' . $kcUser['id'];
            $hasGroup = !empty(Http::withToken($token)->get("$userUrl/groups")->json());

            if ($hasGroup) {
                $this->line("Skipping (already has group): {$user->email}");
                continue;
            }

            $res = Http::withToken($token)->put("$userUrl/groups/$groupId");

            $res->successful()
                ? $this->info("Assigned: {$user->email}")
                : $this->error("Failed: {$user->email}");
        }
    }
}
