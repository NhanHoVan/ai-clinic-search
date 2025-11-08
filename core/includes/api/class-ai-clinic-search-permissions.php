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
    public static function public_access()
    {
        return true;
    }

    /**
     * Allow logged-in users
     */
    public static function logged_in_only()
    {
        return is_user_logged_in();
    }

    /**
     * Allow users who can edit posts
     */
    public static function can_edit_posts()
    {
        return current_user_can('edit_posts');
    }

    /**
     * Allow admins only
     */
    public static function admin_only()
    {
        return current_user_can('manage_options');
    }
}
