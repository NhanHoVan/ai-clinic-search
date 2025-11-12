<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

/**
 * HELPER COMMENT START
 * 
 * This is the main class that is responsible for registering
 * the core functions, including the files and setting up all features. 
 * 
 * To add a new class, here's what you need to do: 
 * 1. Add your new class within the following folder: core/includes/classes
 * 2. Create a new variable you want to assign the class to (as e.g. public $helpers)
 * 3. Assign the class within the instance() function ( as e.g. self::$instance->helpers = new Ai_Clinic_Search_Helpers();)
 * 4. Register the class you added to core/includes/classes within the includes() function
 * 
 * HELPER COMMENT END
 */

if (! class_exists('Ai_Clinic_Search')) :

	/**
	 * Main Ai_Clinic_Search Class.
	 *
	 * @package		AICLINIC
	 * @subpackage	Classes/Ai_Clinic_Search
	 * @since		1.0.0
	 * @author		Van Nhan
	 */
	final class Ai_Clinic_Search
	{

		/**
		 * The real instance
		 *
		 * @access	private
		 * @since	1.0.0
		 * @var		object|Ai_Clinic_Search
		 */
		private static $instance;

		/**
		 * AICLINIC helpers object.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @var		object|Ai_Clinic_Search_Helpers
		 */
		public $helpers;

		/**
		 * AICLINIC settings object.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @var		object|Ai_Clinic_Search_Settings
		 */
		public $settings;

		/**
		 * AICLINIC database object.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @var		object|Ai_Clinic_Search_Db
		 */
		public $database;

		/**
		 * AICLINIC public object.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @var		object|Ai_Clinic_Search_Public
		 */
		public $public;

		/**
		 * AICLINIC AI Generator object.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @var		object|Ai_Clinic_Search_Generator_Post
		 */
		public $ai_generator;

		/**
		 * Throw error on object clone.
		 *
		 * Cloning instances of the class is forbidden.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @return	void
		 */
		public function __clone()
		{
			_doing_it_wrong(__FUNCTION__, __('You are not allowed to clone this class.', 'ai-clinic-search'), '1.0.0');
		}

		/**
		 * Disable unserializing of the class.
		 *
		 * @access	public
		 * @since	1.0.0
		 * @return	void
		 */
		public function __wakeup()
		{
			_doing_it_wrong(__FUNCTION__, __('You are not allowed to unserialize this class.', 'ai-clinic-search'), '1.0.0');
		}

		/**
		 * Main Ai_Clinic_Search Instance.
		 *
		 * Insures that only one instance of Ai_Clinic_Search exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @access		public
		 * @since		1.0.0
		 * @static
		 * @return		object|Ai_Clinic_Search	The one true Ai_Clinic_Search
		 */
		public static function instance()
		{
			if (! isset(self::$instance) && ! (self::$instance instanceof Ai_Clinic_Search)) {
				self::$instance					= new Ai_Clinic_Search;
				self::$instance->base_hooks();
				self::$instance->includes();
				self::$instance->helpers		= new Ai_Clinic_Search_Helpers();
				self::$instance->database		= new Ai_Clinic_Search_DB();

				//Fire the plugin logic
				new Ai_Clinic_Search_Run();
				new Ai_Clinic_REST_Register();
				new Ai_Clinic_Proxy_Controller();
				new AI_Clinic_Search_Shortcode();
				new Ai_Clinic_Controller_Clinic();
				new Ai_Clinic_Search_Uninstaller();

				/**
				 * Fire a custom action to allow dependencies
				 * after the successful plugin setup
				 */
				do_action('AICLINIC/plugin_loaded');
			}

			return self::$instance;
		}

		/**
		 * Include required files.
		 *
		 * @access  private
		 * @since   1.0.0
		 * @return  void
		 */
		private function includes()
		{
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/api/class-ai-clinic-search-permissions.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/api/controllers/class-ai-clinic-search-controller-clinic.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/api/route/class-ai-clinic-search-proxy.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/api/route/class-ai-clinic-search-rest-register.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/api/services/class-ai-clinic-search-service-location.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/classes/class-ai-clinic-search-api.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/classes/class-ai-clinic-search-db.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/classes/class-ai-clinic-search-helpers.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/classes/class-ai-clinic-search-run.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/classes/class-ai-clinic-search-uninstaller.php';
			require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/classes/class-ai-clinic-search-shortcode.php';
		}

		/**
		 * Add base hooks for the core functionality
		 *
		 * @access  private
		 * @since   1.0.0
		 * @return  void
		 */
		private function base_hooks()
		{
			add_action('plugins_loaded', array(self::$instance, 'load_textdomain'));
			register_activation_hook(CLINIC_SEARCH_PLUGIN_FILE, function () {
				$this->database->aiclinic_create_table();
			});
		}

		/**
		 * Loads the plugin language files.
		 *
		 * @access  public
		 * @since   1.0.0
		 * @return  void
		 */
		public function load_textdomain()
		{
			load_plugin_textdomain('ai-clinic-search', FALSE, dirname(plugin_basename(CLINIC_SEARCH_PLUGIN_FILE)) . '/languages/');
		}
	}

endif; // End if class_exists check.