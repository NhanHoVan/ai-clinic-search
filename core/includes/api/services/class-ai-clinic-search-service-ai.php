<?php

/**
 * Class Ai_Clinic_Service_OpenAI
 *
 * Handles communication with OpenAI API endpoints.
 *
 * @package AICLINIC
 */
class Ai_Clinic_Service_OpenAI
{
    /**
     * @var Ai_Clinic_Search_DB Database instance
     */
    private $db;

    /**
     * @var string OpenAI API key
     */
    private $api_key;

    /**
     * @var string OpenAI API base URL
     */
    private $api_url;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new Ai_Clinic_Search_DB();
    }

    /**
     * Load API settings from database
     *
     * @throws Exception If settings cannot be loaded
     * @return void
     */
    private function loadSettings()
    {
        $settings = $this->db->aiclinic_get_settings([
            'openai_api_key',
            'openai_api_url'
        ]);

        if (empty($settings['openai_api_key'])) {
            throw new Exception('OpenAI API key not configured');
        }

        $this->api_key = $settings['openai_api_key'];
        $this->api_url = !empty($settings['openai_api_url'])
            ? $settings['openai_api_url']
            : 'https://api.openai.com/v1';
    }

    /**
     * Send chat completion request
     *
     * @param array $messages Array of message objects
     * @param array $options Additional options (model, temperature, etc.)
     * @return array Response data
     * @throws Exception If request fails
     */
    public function chatCompletion(array $messages, array $options = [])
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/chat/completions';

        $body = array_merge([
            'model' => $options['model'] ?? 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000
        ], $options);

        return $this->request('POST', $endpoint, $body);
    }

    /**
     * Send chat completion with structured output
     *
     * @param array $messages Array of message objects
     * @param string $schema_name Schema file name (without .json extension)
     * @param array $options Additional options (model, temperature, etc.)
     * @return array Response data
     * @throws Exception If request fails or schema not found
     */
    public function chatCompletionStructured(array $messages, $schema_name, array $options = [])
    {
        $this->loadSettings();

        $schema_file = CLINIC_SEARCH_PLUGIN_DIR . 'schemas/' . $schema_name . '.json';

        if (!file_exists($schema_file)) {
            throw new Exception('Schema file not found: ' . $schema_name);
        }

        $json_schema = json_decode(file_get_contents($schema_file), true);

        if (!$json_schema) {
            throw new Exception('Invalid JSON schema file: ' . $schema_name);
        }

        $endpoint = $this->api_url . '/chat/completions';

        $body = array_merge([
            'model' => $options['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => $json_schema
            ]
        ], $options);

        return $this->request('POST', $endpoint, $body);
    }

    /**
     * Send HTTP request to OpenAI API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint URL
     * @param array $body Request body
     * @return array Response data
     * @throws Exception If request fails
     */
    private function request($method, $endpoint, $body = [])
    {
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ];

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            throw new Exception('OpenAI API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_msg = $data['error']['message'] ?? 'Unknown error';
            throw new Exception('OpenAI API error: ' . $error_msg);
        }

        return $data;
    }
}
