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
}
