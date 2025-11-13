<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Permissions
 *
 * Define permission check methods for REST API routes.
 *
 * @package     AICLINIC
 * @subpackage  Classes/Ai_Clinic_Permissions
 * @author      Van Nhan
 * @since       1.0.0
 */
class Ai_Clinic_Permissions
{
    /**
     * Allow public access (no restriction)
     */
    public static function aiclinic_public_access()
    {
        return true;
    }

    /**
     * Allow logged-in users
     */
    public static function aiclinic_logged_in_only()
    {
        return is_user_logged_in();
    }

    /**
     * Allow users who can edit posts
     */
    public static function aiclinic_can_edit_posts()
    {
        return current_user_can('edit_posts');
    }

    /**
     * Allow admins only
     */
    public static function aiclinic_admin_only()
    {
        return current_user_can('manage_options');
    }

    /**
     * Allow only requests from proxies (with valid X-APP-KEY)
     */
    public static function check_internal_api_key(WP_REST_Request $request)
    {
        $api_key = $request->get_header('X-APP-KEY');
        $valid_key = defined('AI_CLINIC_SEARCH_INTERNAL_API_KEY') 
            ? AI_CLINIC_SEARCH_INTERNAL_API_KEY 
            : get_option('ai_clinic_search_internal_api_key', '');

        if (empty($valid_key) || $api_key !== $valid_key) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid or missing API key',
                ['status' => 401]
            );
        }

        return true;
    }
}
