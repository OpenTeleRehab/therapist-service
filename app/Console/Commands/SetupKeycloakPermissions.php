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
        $groups = ['therapist', 'phc_worker'];
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
            'view_region_list',
            'view_province_list',
            'view_phc_service_list',
            'view_phc_service_phc_worker',
            'view_phc_service_list',
            'setup_exercise',
            'setup_educational_material',
            'setup_questionnaire',
            'view_health_condition',
            'manage_patient_referral',
            'manage_patient_referral_assignment',
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
                'view_region_list',
                'view_province_list',
                'setup_exercise',
                'setup_educational_material',
                'setup_questionnaire',
                'manage_patient_referral_assignment',
            ],
            'phc_worker' => [
                'view_patient',
                'get_call_token',
                'push_patient_notification',
                'delete_chat_room',
                'view_activity_list',
                'manage_treatment_plan',
                'manage_message',
                'manage_own_profile',
                'view_country',
                'view_disease',
                'view_profession',
                'view_setting',
                'view_guidance_page',
                'view_assistive_technology',
                'manage_patient',
                'manage_appointment',
                'view_patient_activity',
                'manage_patient_treatment_plan',
                'manage_patient_assistive_technology',
                'manage_transfer',
                'export_treatment_plan',
                'view_notification',
                'download_file',
                'view_dashboard',
                'manage_exercise',
                'manage_education_material',
                'manage_questionnaire',
                'manage_category',
                'view_region_list',
                'view_province_list',
                'view_phc_service_list',
                'view_phc_service_phc_worker',
                'view_health_condition',
                'manage_patient_referral',
            ],
        ];

        $this->line("Creating groups...");

        foreach ($groups as $groupName) {
            $created = KeycloakHelper::createGroup($groupName);
            if ($created) {
                $this->info(" - Group '{$groupName}': Created");
            } else {
                $this->warn(" - Group '{$groupName}': Already exists or failed");
            }
        }

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
