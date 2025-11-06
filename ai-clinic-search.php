<?php

/**
 * AI Clinic Search
 *
 * @package       CLINIC_SEARCH
 * @author        Van Nhan
 * @version       1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:   AI Clinic Search
 * Description:   Plugin integrates smart clinic search system using AI.
 * Version:       1.0.0
 * Author:        Van Nhan
 * Author URI:    https://github.com/NhanHoVan
 * Text Domain:   ai-clinic-search
 * Domain Path:   /languages
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

/**
 * HELPER COMMENT START
 * 
 * This file contains the main information about the plugin.
 * It is used to register all components necessary to run the plugin.
 * 
 * The comment above contains all information about the plugin 
 * that are used by WordPress to differenciate the plugin and register it properly.
 * It also contains further PHPDocs parameter for a better documentation
 * 
 * The function CLINIC_SEARCH() is the main function that you will be able to 
 * use throughout your plugin to extend the logic. Further information
 * about that is available within the sub classes.
 * 
 * HELPER COMMENT END
 */

// Plugin name
define('CLINIC_SEARCH_NAME',            'AI Clinic Search');

// Plugin version
define('CLINIC_SEARCH_VERSION',         '1.0.0');

// Plugin Root File
define('CLINIC_SEARCH_PLUGIN_FILE',      __FILE__);

// Plugin base
define('CLINIC_SEARCH_PLUGIN_BASE',      plugin_basename(CLINIC_SEARCH_PLUGIN_FILE));

// Plugin Folder Path
define('CLINIC_SEARCH_PLUGIN_DIR',       plugin_dir_path(CLINIC_SEARCH_PLUGIN_FILE));

// Plugin Folder URL
define('CLINIC_SEARCH_PLUGIN_URL',       plugin_dir_url(CLINIC_SEARCH_PLUGIN_FILE));

// Get Site URL (without paths, schema, etc.)
define('CLINIC_SEARCH_SITEURL',          parse_url(get_site_url(), PHP_URL_HOST));

define('CUSTOMER',                      'customer');
define('USER',                          'user');
define('SYSTEM',                        'system');
define('ASSISTANT',                     'assistant');

/**
 * Load the main class for the core functionality
 */
require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/class-ai-clinic-search.php';

/**
 * The main function to load the only instance
 * of our master class.
 *
 * @author  Van Nhan
 * @since   1.0.0
 * @return  object|Ai_Clinic_Search
 */
function CLINIC_SEARCH()
{
    return Ai_Clinic_Search::instance();
}

CLINIC_SEARCH();
