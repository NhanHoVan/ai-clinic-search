<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_REST_Register
 *
 * Register all REST API routes and map to controllers
 * 
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_REST_Register
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_REST_Register
{
    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes()
    {
        $clinic_controller = new AI_Clinic_Controller_Clinic();

        register_rest_route('ai-clinic/v1', '/clinics', [
            'methods' => 'GET',
            'callback' => [$clinic_controller, 'get_clinics'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'public_access'],
        ]);

        register_rest_route('ai-clinic/v1', '/clinics', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'create_clinic'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'can_edit_posts'],
        ]);
    }
}
