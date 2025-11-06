<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

/**
 * HELPER COMMENT START
 * 
 * This class is used to bring your plugin to life. 
 * All the other registered classed bring features which are
 * controlled and managed by this class.
 * 
 * Within the add_hooks() function, you can register all of 
 * your WordPress related actions and filters as followed:
 * 
 * add_action( 'my_action_hook_to_call', array( $this, 'the_action_hook_callback', 10, 1 ) );
 * or
 * add_filter( 'my_filter_hook_to_call', array( $this, 'the_filter_hook_callback', 10, 1 ) );
 * or
 * add_shortcode( 'my_shortcode_tag', array( $this, 'the_shortcode_callback', 10 ) );
 * 
 * Once added, you can create the callback function, within this class, as followed: 
 * 
 * public function the_action_hook_callback( $some_variable ){}
 * or
 * public function the_filter_hook_callback( $some_variable ){}
 * or
 * public function the_shortcode_callback( $attributes = array(), $content = '' ){}
 * 
 * 
 * HELPER COMMENT END
 */

/**
 * Class Ai_Clinic_Search_Run
 *
 * Thats where we bring the plugin to life
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_Run
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Search_Run
{

	/**
	 * Our Ai_Clinic_Search_Run constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.0.0
	 */
	function __construct()
	{
		$this->add_hooks();
		$this->includes();
	}

	/**
	 * ######################
	 * ###
	 * #### WORDPRESS HOOKS
	 * ###
	 * ######################
	 */

	/**
	 * Registers all WordPress and plugin related hooks
	 *
	 * @access	private
	 * @since	1.0.0
	 * @return	void
	 */
	private function add_hooks()
	{

		add_action('admin_menu', array($this, 'aiclinic_add_admin_menu_items'), 10, 1);
		add_action('admin_bar_menu', array($this, 'aiclinic_add_admin_bar_menu_items'), 100, 1);

		add_action('plugin_action_links_' . CLINIC_SEARCH_PLUGIN_BASE, array($this, 'aiclinic_add_plugin_action_link'), 20);
		add_action('admin_enqueue_scripts', array($this, 'aiclinic_enqueue_backend_scripts_and_styles'), 20);
		add_action('wp_enqueue_scripts', array($this, 'aiclinic_enqueue_frontend_scripts_and_styles'), 21);

		add_filter('admin_footer_text', [$this, 'aiclinic_custom_admin_footer']);
		add_filter('update_footer', [$this, 'aiclinic_remove_wp_version_footer'], 11);
	}

	/**
	 * Add a new menu item to the WordPress topbar
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @param	object $admin_bar The WP_Admin_Bar object
	 *
	 * @return	void
	 */
	public function aiclinic_add_admin_bar_menu_items($admin_bar)
	{

		$admin_bar->add_menu(array(
			'id'		=> 'ai-clinic-search-id',
			'title'		=> CLINIC_SEARCH_NAME,
			'parent'	=> false,
			'href'		=> admin_url('admin.php?page=aiclinic-customers'),
			'group'		=> false,
			'meta'		=> array(
				'title'		=> CLINIC_SEARCH_NAME,
				'html'		=> false,
				'rel'		=> false,
				'onclick'	=> false,
				'tabindex'	=> false,
			),
		));
	}

	/**
	 * Add a new menu item to the main menu
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @return	void
	 */
	public function aiclinic_add_admin_menu_items()
	{
		add_menu_page(
			CLINIC_SEARCH_NAME,
			CLINIC_SEARCH_NAME,
			'manage_options',
			'aiclinic-customers',
			'aiclinic_customers_page',
			'dashicons-admin-generic',
			6
		);

		add_submenu_page(
			'aiclinic-customers',
			__('Customers', 'ai-clinic-search'),
			__('Customers', 'ai-clinic-search'),
			'manage_options',
			'aiclinic-customers',
			'aiclinic_customers_page'
		);
		add_submenu_page(
			'aiclinic-customers',
			__('General Settings', 'ai-clinic-search'),
			__('General Settings', 'ai-clinic-search'),
			'manage_options',
			'aiclinic-settings',
			'aiclinic_settings_page'
		);
		add_submenu_page(
			'aiclinic-customers',
			__('Logs', 'ai-clinic-search'),
			__('Logs', 'ai-clinic-search'),
			'manage_options',
			'aiclinic-logs',
			'aiclinic_logs_page'
		);
	}

	/** Identify plugin admin pages */
	protected function aiclinic_is_plugin_page()
	{
		$plugin_pages = [
			'aiclinic-customers',
			'aiclinic-settings',
			'aiclinic-logs'
		];

		return isset($_GET['page']) && in_array($_GET['page'], $plugin_pages);
	}

	public function aiclinic_custom_admin_footer($footer_text)
	{
		if ($this->aiclinic_is_plugin_page()) {
			return sprintf('%s <a href="%s" target="_blank">%s</a></strong>', __('Designed and developed by', 'ai-clinic-search'), 'https://github.com/NhanHoVan', 'Van Nhan');
		}
		return $footer_text;
	}

	public function aiclinic_remove_wp_version_footer($default)
	{
		if ($this->aiclinic_is_plugin_page()) {
			return 'v' . CLINIC_SEARCH_VERSION;
		}
		return $default;
	}

	/**
	 * Adds action links to the plugin list table
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @param	array	$links An array of plugin action links.
	 *
	 * @return	array	An array of plugin action links.
	 */
	public function aiclinic_add_plugin_action_link($links)
	{

		$settings_link = sprintf('<a href="%s" title="Settings">%s</a>', admin_url('admin.php?page=aiclinic-settings'), __('Settings', 'ai-clinic-search'));

		array_unshift($links, $settings_link);

		return $links;
	}

	/**
	 * Enqueue the backend related scripts and styles for this plugin.
	 * All of the added scripts andstyles will be available on every page within the backend.
	 *
	 * @access	public
	 * @since	1.0.0
	 *
	 * @return	void
	 */
	public function aiclinic_enqueue_backend_scripts_and_styles()
	{
		// wp_enqueue_media();
		// wp_enqueue_style( 'aiclinic-backend-styles', AICLINIC_PLUGIN_URL . 'core/includes/assets/css/backend-styles.css', array(), AICLINIC_VERSION, 'all' );
		// wp_enqueue_script( 'aiclinic-backend-js', AICLINIC_PLUGIN_URL . 'core/includes/assets/js/backend-scripts.js', array('jquery'), AICLINIC_VERSION, true );
	}

	/**
	 * Enqueue frontend scripts and styles for this plugin.
	 *
	 * @access public
	 * @since 1.0.0
	 * @return void
	 */
	public function aiclinic_enqueue_frontend_scripts_and_styles()
	{
		// wp_enqueue_style( 'aiclinic-frontend-styles', AICLINIC_PLUGIN_URL . 'core/includes/assets/css/frontend-styles.css', array(), AICLINIC_VERSION, 'all' );
		// wp_enqueue_script( 'aiclinic-frontend-js', AICLINIC_PLUGIN_URL . 'core/includes/assets/js/frontend-scripts.js', array('jquery'), AICLINIC_VERSION, true );
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
		require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/admin/ai-clinic-search-customers.php';
		require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/admin/ai-clinic-search-settings.php';
		require_once CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/admin/ai-clinic-search-logs.php';
	}
}
