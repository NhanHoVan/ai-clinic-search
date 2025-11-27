<?php
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_REST_Public
 *
 * Public-facing REST API endpoints
 */
class Ai_Clinic_REST_Public
{

    public function register_routes()
    {
        $base = Ai_Clinic_REST_Register::get_base(Ai_Clinic_REST_Register::GROUP_PUBLIC);
        $clinic_controller = new AI_Clinic_Controller_Clinic();

        // get_provinces
        register_rest_route($base, '/provinces', [
            'methods' => 'GET',
            'callback' => [$clinic_controller, 'get_provinces'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // get_districts
        register_rest_route($base, '/districts', [
            'methods' => 'GET',
            'callback' => [$clinic_controller, 'get_districts'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // get_specialties
        register_rest_route($base, '/specialties', [
            'methods' => 'GET',
            'callback' => [$clinic_controller, 'get_specialties'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // search_clinics with AI search
        register_rest_route($base, '/clinics/search', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'search_clinics'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // get_refer_clinics
        register_rest_route($base, '/clinics', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'get_refer_clinics'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);
    }
}
