<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Search_Api
 *
 * Thats where we bring the plugin to life
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_Api
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Search_Api
{
	/**
	 * helper
	 *
	 * @var		Ai_Clinic_Search_Helpers
	 * @since   1.0.0
	 */
	private $helper;

	/**
	 * AICLINIC database object.
	 *
	 * @access	public
	 * @since	1.0.0
	 * @var		object|Ai_Clinic_Search_Db
	 */
	private $database;

	function __construct()
	{
		$this->helper = new Ai_Clinic_Search_Helpers();
		$this->database = new Ai_Clinic_Search_DB();
	}

	/**
	 * Calls the OpenAI Chat Completion API to generate content based on a given prompt and usage mode.
	 *
	 * @param string $messages 		Messages to send to OpenAI.
	 *
	 * @return string The generated content returned from OpenAI.
	 *
	 * @throws \Exception If the API key is missing, the API request fails, or the response is invalid.
	 */
	public function aiclinic_call_openai_api(array $messages)
	{
		$setting_ai = $this->database->aiclinic_get_raw_ai_settings();
		if (empty($setting_ai["api_key"]) || empty($setting_ai["api_url"]) || empty($setting_ai["model"])) {
			throw new \Exception(__('Missing OpenAI information setting.', 'ai-clinic-search'));
		}

		$model = $setting_ai["model"];
		$api_url = $setting_ai["api_url"];
		$api_key = $this->helper->config->aiclinic_decrypt_value($setting_ai['api_key']);

		$response = wp_remote_post($api_url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body' => wp_json_encode([
				'model' => $model,
				'messages' => $messages,
				'temperature' => 0.7,
			]),
			'timeout' => 30,
		]);

		if (is_wp_error($response)) {
			throw new \Exception(__('Error calling OpenAI API: ', 'ai-clinic-search') . $response->get_error_message());
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (!isset($data['choices'][0]['message']['content'])) {
			throw new \Exception(__('Invalid response from OpenAI: ', 'ai-clinic-search') . $body);
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Calls the Google Safe Browsing API to check a given URL for potential threats.
	 *
	 * @param string $url The URL to be analyzed.
	 * @return array The decoded response from the Safe Browsing API.
	 * @throws \Exception If the API call fails or returns an error.
	 */
	public function aiclinic_call_safebrowsing_api($url)
	{
		$api_key = AICLINIC_GOOGLE_API_KEY;
		$endpoint = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key=' . $api_key;

		$body = [
			'client' => [
				'clientId' => 'aiclinic-client',
				'clientVersion' => '1.0',
			],
			'threatInfo' => [
				'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING'],
				'platformTypes' => ['ANY_PLATFORM'],
				'threatEntryTypes' => ['URL'],
				'threatEntries' => [['url' => $url]],
			],
		];

		$response = wp_remote_post($endpoint, [
			'headers' => ['Content-Type' => 'application/json'],
			'body'    => wp_json_encode($body),
			'timeout' => 5,
		]);

		if (is_wp_error($response)) {
			throw new \Exception(__('Error calling SafeBrowsing API: ', 'ai-clinic-search') . $response->get_error_message());
		}

		$body_raw = wp_remote_retrieve_body($response);
		$data = json_decode($body_raw, true);

		if (isset($data['error'])) {
			throw new \Exception("Google Safe Browsing API error: " . $data['error']['message']);
		}

		return $data;
	}
}
