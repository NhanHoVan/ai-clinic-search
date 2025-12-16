<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Search_Setting
 *
 * Thats where we bring the plugin to life
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_Setting
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Search_Setting
{
	/**
	 * The options
	 *
	 * @var		array
	 * @since   1.0.0
	 */
	private $options;

	/**
	 * The plugin name
	 *
	 * @var		array
	 * @since   1.0.0
	 */
	private $tooltips;

	function __construct()
	{
		$this->add_hooks();

		$this->options = [
			'flag_custom_login' => 'Tùy chỉnh giao diện đăng nhập:',
			'custom_login_logo' => 'URL logo đăng nhập:',
		];

		$this->tooltips = [
			'flag_custom_login' => 'Bật tùy chỉnh giao diện trang đăng nhập.',
			'custom_login_logo' => 'Nhập URL hoặc tải lên ảnh để đặt logo tùy chỉnh trên trang đăng nhập.',
		];
	}

	/**
	 * Registers all WordPress and plugin related hooks
	 *
	 * @access	private
	 * @since	1.0.0
	 * @return	void
	 */
	private function add_hooks()
	{
		add_action('admin_init', [$this, 'register_settings']);

		if ($this->is_custom_login_enabled()) {
			add_action('login_enqueue_scripts', [$this, 'enqueue_custom_login_styles']);
			add_action('init', [$this, 'enable_remember_me']);
			add_filter('login_headerurl', [$this, 'custom_login_logo_url']);
			add_filter('login_headertext', [$this, 'custom_login_logo_title']);
			add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * This function registers the plugin settings, adds a settings section, 
	 * and creates fields for each option.
	 *
	 * @return void
	 */
	public function register_settings()
	{
		register_setting('aiclinic_settings_group', 'aiclinic_settings', [
			'sanitize_callback' => function ($input) {
				wp_cache_flush();
				return $input;
			}
		]);

		add_settings_section(
			'aiclinic_main_section',
			'',
			'__return_false',
			'aiclinic_settings_group'
		);

		foreach ($this->options as $key => $label) {
			add_settings_field(
				$key,
				$label,
				[$this, 'render_checkbox'],
				'aiclinic_settings_group',
				'aiclinic_main_section',
				['key' => $key]
			);
		}
	}

	/**
	 * Render a checkbox input field.
	 *
	 * This function outputs an HTML checkbox input field for the given setting key.
	 *
	 * @param array $args An array containing the setting key.
	 * @return void
	 */
	public function render_checkbox($args)
	{
		$options = get_option('aiclinic_settings', []);
		$key = $args['key'];
		$value = isset($options[$key]) ? esc_attr($options[$key]) : '';

		$tooltip = isset($this->tooltips[$key])
			? '<span class="tooltip-icon"><i class="fas fa-info-circle"></i>
            <span class="tooltip-text">' . esc_attr($this->tooltips[$key]) . '</span>
           </span>'
			: '';

		if ($key === 'flag_custom_login') {
			echo '<input type="checkbox" name="aiclinic_settings[' . $key . ']" value="1" ' . checked(1, $value, false) . ' />' . $tooltip;
		} elseif ($key === 'custom_login_logo') {
			echo '<input type="text" name="aiclinic_settings[' . $key . ']" value="' . $value . '" style="width: 300px; margin-right: 10px;" />';
			echo '<button type="button" class="button upload_image_button">Upload</button>';
		}
	}

	/**
	 * Check if the custom login feature is enabled.
	 * @return bool Returns true if enabled, otherwise returns false.
	 */
	private function is_custom_login_enabled()
	{
		$settings = get_option('aiclinic_settings', []);
		return !empty($settings['flag_custom_login']);
	}

	/**
	 * Check if the custom login logo is not empty.
	 * @return bool Returns true if enabled, otherwise returns false.
	 */
	private function get_custom_logo_url()
	{
		$settings = get_option('aiclinic_settings', []);
		return $settings['custom_login_logo'];
	}

	/**
	 * Load custom CSS
	 *
	 * @return void
	 */
	public function enqueue_custom_login_styles()
	{
		$default_logo_url = 'http://localhost/timphongkham24h/wp-content/uploads/2025/11/logo.png';
		$custom_logo_url = $this->get_custom_logo_url() ? $this->get_custom_logo_url() : $default_logo_url;

		wp_enqueue_style('aiclinic-custom-login', CLINIC_SEARCH_PLUGIN_URL . 'core/includes/assets/css/custom-login-styles.css', false);

		if (!empty($custom_logo_url)) {
			$custom_css = "
                .login h1 a {
                    background-image: url('" . esc_url($custom_logo_url) . "') !important;
                    background-size: contain !important;
                    width: 100% !important;
                    height: 110px !important;
                }";
			wp_add_inline_style('aiclinic-custom-login', $custom_css);
		}
	}

	/**
	 * Load custom Js.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts()
	{
		wp_enqueue_media();
		wp_enqueue_script('custom-login-script', CLINIC_SEARCH_PLUGIN_URL . 'core/includes/assets/js/custom-login.js', array('jquery'), null, true);
	}

	/**
	 * Automatically enable the "Remember Me" checkbox.
	 *
	 * @return void
	 */
	public function enable_remember_me()
	{
		add_filter('login_footer', function () {
			echo "<script>document.getElementById('rememberme').checked = true;</script>";
		});
	}

	/**
	 * Change the login page logo URL.
	 *
	 * @return string The homepage URL.
	 */
	public function custom_login_logo_url()
	{
		return home_url();
	}

	/**
	 * Change the login logo title.
	 *
	 * @return string The new title.
	 */
	public function custom_login_logo_title()
	{
		return 'Tìm phòng khám 24h';
	}
}
