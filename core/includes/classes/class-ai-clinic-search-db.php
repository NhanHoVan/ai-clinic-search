<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Search_DB
 *
 * This class contains functions
 * used to interact with the DB in the plugin.
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_DB
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Search_DB
{

    /**
     * wpdb
     *
     * @var		global
     * @since   1.0.0
     */
    private $wpdb;

    /**
     * helper
     *
     * @var		Ai_Clinic_Search_Helpers
     * @since   1.0.0
     */
    private $helper;

    /**
     * Construct
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        $this->add_hooks();

        $this->helper = new Ai_Clinic_Search_Helpers();
    }

    /**
     * Registers related hooks
     *
     * @access	private
     * @since	1.0.0
     * @return	void
     */
    private function add_hooks()
    {
        // TODO
    }

    /**
     * ######################
     * ###
     * #### CALLABLE FUNCTIONS
     * ###
     * ######################
     */

    /**
     * Creates setting and customer tables.
     */
    public function aiclinic_create_table()
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_file = CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/init.sql';
        if (!file_exists($sql_file)) {
            error_log("Database file not found: " . $sql_file);
            return;
        }

        $sql_content = file_get_contents($sql_file);
        if (!$sql_content) {
            error_log("Failed to read database file.");
            return;
        }

        // Replace WordPress prefix and charset collate
        $sql_content = str_replace(
            ['wp_', '{CHARSET_COLLATE}'],
            [$this->wpdb->prefix, $charset_collate],
            $sql_content
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $queries = explode(';', $sql_content);

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                // Extract table name from the CREATE TABLE statement
                if (preg_match('/CREATE TABLE IF NOT EXISTS `?([^` ]+)`?/i', $query, $matches)) {
                    $table_name = $matches[1];

                    // Check if the table already exists
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                        // If the table does not exist, create a new one
                        dbDelta($query);
                    } else {
                        // If the table exists, check and update its schema
                        $this->helper->database->aiclinic_update_table_schema($table_name, $query);
                    }
                }
            }
        }
    }

    /**
     * Insert an error log entry into the aiclinic_plugin_error_logs table.
     *
     * This function records plugin-related errors, including the action being performed,
     * the error message, and any additional context provided.
     *
     * @param array $data {
     *     Associative array containing error log information.
     *
     *     @type string $action     The action being executed when the error occurred.
     *     @type string $message    The error message to be logged.
     *     @type string $context    Additional context or metadata related to the error.
     * }
     *
     * @return int|false The number of rows inserted, or false on failure.
     */
    public function aiclinic_insert_error_logs($data)
    {
        $data_to_insert = [
            'action'     => sanitize_text_field($data['action'] ?? ''),
            'message'    => sanitize_textarea_field($data['message'] ?? ''),
            'context'    => is_array($data['context'] ?? '')
                ? wp_json_encode($data['context'])
                : sanitize_textarea_field($data['context'] ?? ''),
            'created_at' => current_time('mysql'),
        ];

        // Format auto-detected now, no need to pass it
        return $this->helper->database->aiclinic_insert(
            'aiclinic_plugin_error_logs',
            $data_to_insert
        );
    }

    /**
     * Retrieve plugin error logs with optional search, pagination, and sorting.
     *
     * @param array $args {
     *     Optional. Arguments for retrieving error logs.
     *
     *     @type string $search         Search keyword to match in action or message. Default empty.
     *     @type int    $limit          Number of logs per page. Default 10.
     *     @type int    $offset         Offset for pagination. Default 0.
     *     @type string $order_by       Column to sort by. Default 'created_at'.
     *     @type string $sort_direction Sort direction (ASC|DESC). Default 'DESC'.
     *     @type array  $filters        Additional filters. Default empty array.
     * }
     *
     * @return array|false Array of log entries or false on error.
     */
    public function aiclinic_get_error_logs($args = [])
    {
        $defaults = [
            'search'         => '',
            'column_search'  => 'action',
            'limit'          => 10,
            'offset'         => 0,
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC',
            'filters'        => []
        ];

        $args = wp_parse_args($args, $defaults);

        return $this->helper->database->aiclinic_get_all(
            'aiclinic_plugin_error_logs',
            $args
        );
    }

    /**
     * Count the number of error logs in the aiclinic_plugin_error_logs table.
     *
     * This function counts logs where the given search keyword matches
     * the 'action' column using a LIKE query.
     *
     * @param array $args {
     *     Optional. Arguments for counting error logs.
     *
     *     @type string $search        Search keyword to filter logs. Default empty.
     *     @type string $column_search Column to search in. Default 'action'.
     *     @type array  $filters       Additional filters. Default empty array.
     * }
     *
     * @return int|null Number of matching log records or null on error.
     */
    public function aiclinic_count_error_logs($args = [])
    {
        $defaults = [
            'search'        => '',
            'column_search' => 'action',
            'filters'       => []
        ];

        $args = wp_parse_args($args, $defaults);

        return $this->helper->database->aiclinic_count_record(
            'aiclinic_plugin_error_logs',
            $args
        );
    }

    /**
     * Get a single row from a table by a specific column.
     *
     * @param string $table_name The name of the table (without prefix).
     * @param string $column The column name to match.
     * @param mixed  $value The value to search for.
     *
     * @return array|null The found row as an associative array or null if not found.
     */
    public function aiclinic_get_one_by($table_name, $column, $value)
    {
        $results = $this->helper->database->aiclinic_get_all($table_name, [
            'filters' => [$column => $value],
            'limit'   => 1,
            'offset'  => 0
        ]);

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Get multiple values from a specific column in a table.
     *
     * @param string $table_name The name of the table (without prefix).
     * @param string $column The column name to retrieve values from.
     * @param array  $where_conditions Associative array of WHERE conditions.
     *
     * @return array Array of values from the specified column.
     */
    public function aiclinic_get_column_values($table_name, $column, $where_conditions = [])
    {
        $results = $this->helper->database->aiclinic_get_all($table_name, [
            'select'  => $column,
            'filters' => $where_conditions
        ]);

        return array_column($results, $column);
    }

    /**
     * Get raw AI settings from the database without decrypting any value.
     *
     * @return array Associative array of all settings.
     */
    public function aiclinic_get_raw_ai_settings(): array
    {
        $table = 'aiclinic_plugin_settings';

        $keys = [
            'model',
            'api_key',
            'api_url',
            'role_generator',
            'role_assistant',
            'prompt_template',
            'assistant_data_training',
        ];

        // Get all settings in one query using IN clause
        $results = $this->helper->database->aiclinic_get_all($table, [
            'filters' => [
                'option_name' => $keys
            ]
        ]);

        // Transform results into key-value pairs
        $settings = array_fill_keys($keys, '');
        foreach ($results as $row) {
            if (isset($row['option_name'], $row['option_value'])) {
                $settings[$row['option_name']] = $row['option_value'];
            }
        }

        return $settings;
    }

    /**
     * Get AI settings from the database with decryption.
     *
     * @return array Associative array of all settings with decrypted sensitive values.
     */
    public function aiclinic_get_ai_settings(): array
    {
        $settings = $this->aiclinic_get_raw_ai_settings();

        if (method_exists($this->helper, 'config')) {
            $sensitive_keys = ['api_key'];
            foreach ($sensitive_keys as $key) {
                if (!empty($settings[$key])) {
                    $settings[$key] = $this->helper->config->aiclinic_decrypt_value($settings[$key]);
                }
            }
        }

        return $settings;
    }

    /**
     * Update or insert AI settings.
     *
     * @param array $settings Associative array of settings to save.
     * @return bool True on success, false on failure.
     */
    public function aiclinic_save_ai_settings($settings): bool
    {
        $table = 'aiclinic_plugin_settings';
        $success = true;

        foreach ($settings as $key => $value) {
            if ($key === 'api_key' && method_exists($this->helper, 'config')) {
                $value = $this->helper->config->aiclinic_encrypt_value($value);
            }

            $data = [
                'option_name'  => sanitize_key($key),
                'option_value' => sanitize_textarea_field($value),
                'updated_at'   => current_time('mysql')
            ];

            $where = ['option_name' => sanitize_key($key)];

            $result = $this->helper->database->aiclinic_upsert(
                $table,
                $data,
                $where
            );

            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Delete old error logs older than specified days.
     *
     * @param int $days Number of days to keep logs. Default 30.
     * @return int|false Number of deleted rows or false on failure.
     */
    public function aiclinic_cleanup_old_error_logs($days = 30): int|false
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aiclinic_plugin_error_logs';
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));

        $sql = $wpdb->prepare(
            "DELETE FROM `$table` WHERE `created_at` < %s",
            $date_threshold
        );

        return $wpdb->query($sql);
    }
}
