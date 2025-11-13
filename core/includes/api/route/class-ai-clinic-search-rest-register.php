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
    const NAMESPACE = 'ai-clinic-search';
    const VERSION   = 'v1';
    const GROUP_PUBLIC = 'public';
    const GROUP_ADMIN  = 'admin';
    const GROUP_PROXY  = 'proxy';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_all_routes']);
    }

    public function register_all_routes()
    {
        // Public routes
        require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/api/route/class-ai-clinic-search-rest-public.php';
        (new Ai_Clinic_REST_Public())->register_routes();

        // Admin routes
        // TODO
    }

    /**
     * Helper to build route base
     */
    public static function get_base($group)
    {
        return self::NAMESPACE . '/' . $group . '/' . self::VERSION;
    }
}
