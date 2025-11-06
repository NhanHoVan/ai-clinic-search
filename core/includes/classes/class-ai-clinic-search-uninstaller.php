<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Search_Uninstaller
 *
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_Uninstaller
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Search_Uninstaller
{

	/**
	 * helper
	 *
	 * @var		Ai_Clinic_Search_Helpers
	 * @since   1.0.0
	 */
	private $helper;

	/**
	 * Our Ai_Clinic_Search_Uninstaller constructor 
	 * to run the plugin logic.
	 *
	 * @since 1.0.0
	 */
	function __construct()
	{
		$this->add_hooks();
	}

	private function add_hooks()
	{
		register_uninstall_hook(CLINIC_SEARCH_PLUGIN_FILE, array('Ai_Clinic_Search_Uninstaller', 'aiclinic_uninstall'));
	}

	/**
	 * Perform necessary tasks to uninstall the plugin
	 */
	public static function aiclinic_uninstall()
	{
		$helper = new Ai_Clinic_Search_Helpers();

		$helper->database->aiclinic_drop_table('aiclinic_plugin_settings');
		$helper->database->aiclinic_drop_table('aiclinic_chatbot_logs');
		$helper->database->aiclinic_drop_table('aiclinic_csv_import_export_logs');
		$helper->database->aiclinic_drop_table('aiclinic_admin_dashboard_styles');
		$helper->database->aiclinic_drop_table('aiclinic_seo_settings');
		$helper->database->aiclinic_drop_table('aiclinic_customers');
	}
}
