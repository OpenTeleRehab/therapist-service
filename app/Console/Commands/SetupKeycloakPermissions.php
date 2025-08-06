<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\KeycloakHelper;

class SetupKeycloakPermissions extends Command
{
    protected $signature = 'hi:keycloak-setup-permissions';
    protected $description = 'Create Keycloak roles and assign them to groups';

    public function handle()
    {
        $roles = [
            'view_patient',
            'view_transfered_patient',
            'view_clinic_therapist',
            'view_country_therapist',
            'get_call_token',
            'push_patient_notification',
            'delete_chat_room',
            'view_activity_list',
            'manage_treatment_plan',
            'manage_message',
            'manage_own_profile',
            'manage_transfer',
            'manage_category',
            'view_therapist_library',
            'view_country',
            'view_disease',
            'view_profession',
            'view_setting',
            'view_guidance_page',
            'manage_exercise',
            'manage_education_material',
            'manage_questionnaire',
            'view_assistive_technology',
            'manage_patient',
            'manage_appointment',
            'view_patient_activity',
            'manage_patient_treatment_plan',
            'manage_patient_assistive_technology',
            'export_treatment_plan',
            'view_notification',
            'manage_download_tracker',
            'export',
            'download_file',
            'view_dashboard',
        ];
        $groupRoles = [
            'therapist' => [
                'view_patient',
                'view_transfered_patient',
                'view_clinic_therapist',
                'view_country_therapist',
                'get_call_token',
                'push_patient_notification',
                'delete_chat_room',
                'view_activity_list',
                'manage_treatment_plan',
                'manage_message',
                'manage_own_profile',
                'manage_transfer',
                'manage_category',
                'view_therapist_library',
                'view_country',
                'view_disease',
                'view_profession',
                'view_setting',
                'view_guidance_page',
                'manage_exercise',
                'manage_education_material',
                'manage_questionnaire',
                'view_assistive_technology',
                'manage_patient',
                'manage_appointment',
                'view_patient_activity',
                'manage_patient_treatment_plan',
                'manage_patient_assistive_technology',
                'export_treatment_plan',
                'view_notification',
                'manage_download_tracker',
                'export',
                'download_file',
                'view_dashboard',
            ],
        ];

        $this->line("Creating roles...");

        foreach ($roles as $role) {
            $created = KeycloakHelper::createRealmRole($role, "Role: $role");
            if ($created) {
                $this->info(" - Role '{$role}': Created");
            } else {
                $this->warn(" - Role '{$role}': Already exists or failed");
            }
        }

        $this->line("Assigning roles to groups...");

        foreach ($groupRoles as $group => $roles) {
            foreach ($roles as $role) {
                try {
                    $success = KeycloakHelper::assignRealmRoleToGroup($group, $role);
                    if ($success) {
                        $this->info(" - Group '{$group}' - Role '{$role}': Assigned");
                    } else {
                        $this->warn(" - Group '{$group}' - Role '{$role}': Failed");
                    }
                } catch (\Exception $e) {
                    $this->error("Error assigning role '{$role}' to group '{$group}': " . $e->getMessage());
                }
            }
        }

        $this->line("Permission setup complete.");
    }
}
