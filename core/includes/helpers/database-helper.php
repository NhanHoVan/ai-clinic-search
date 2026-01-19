<?php

namespace AICLINIC\Core\Includes\Helpers;

/**
 * A helper object for database.
 */
class Database_Helper
{

    /**
     * wpdb
     *
     * @var		global
     * @since   1.0.0
     */
    private $wpdb;

    /**
     * Construct
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Updates a database table schema based on a provided CREATE TABLE SQL statement.
     * This function creates a temporary table from the SQL statement and compares it with the existing table.
     * It then adds missing columns and modifies existing columns if necessary.
     *
     * @param string $table_name        The name of the existing table to update
     * @param string $create_table_sql  The CREATE TABLE SQL statement with the desired schema
     * @return bool                     True on success, false on failure
     * @since 1.0.0
     */
    public function aiclinic_update_table_schema($table_name, $create_table_sql)
    {
        // Create a temporary table name
        $temp_table = $table_name . '_temp_' . time();

        $temp_table_sql = preg_replace(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`[^`]+`|[^\s(]+)/i',
            "CREATE TABLE IF NOT EXISTS `$temp_table`",
            $create_table_sql
        );

        error_log("Creating temp table with SQL: " . $temp_table_sql);

        $create_result = $this->wpdb->query($temp_table_sql);
        if ($create_result === false) {
            error_log("Failed to create temp table: " . $this->wpdb->last_error);
            return false;
        }

        try {
            $existing_columns = $this->wpdb->get_results(
                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = '$table_name'",
                ARRAY_A
            );

            $existing_columns_map = [];
            foreach ($existing_columns as $column) {
                $existing_columns_map[$column['COLUMN_NAME']] = $column;
            }

            $new_columns = $this->wpdb->get_results(
                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = '$temp_table'",
                ARRAY_A
            );

            foreach ($new_columns as $new_column) {
                $column_name = $new_column['COLUMN_NAME'];

                $default_clause = '';
                if ($new_column['COLUMN_DEFAULT'] !== null) {
                    if ($new_column['COLUMN_DEFAULT'] === 'NULL') {
                        $default_clause = 'DEFAULT NULL';
                    } else {
                        $default_value = $new_column['COLUMN_DEFAULT'];

                        $is_enum = (stripos($new_column['COLUMN_TYPE'], 'enum') === 0);

                        if ($is_enum) {
                            $default_clause = "DEFAULT $default_value";
                        } else if ($default_value === 'CURRENT_TIMESTAMP') {
                            $default_clause = "DEFAULT $default_value";
                        } else if (!is_numeric($default_value)) {
                            $default_clause = "DEFAULT '" . $this->wpdb->_real_escape($default_value) . "'";
                        } else {
                            $default_clause = "DEFAULT $default_value";
                        }
                    }
                }

                $nullable = $new_column['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';

                $extra = $new_column['EXTRA'] ? $new_column['EXTRA'] : '';

                $comment = '';
                if (!empty($new_column['COLUMN_COMMENT'])) {
                    $comment = "COMMENT '" . $this->wpdb->_real_escape($new_column['COLUMN_COMMENT']) . "'";
                }

                $column_definition = trim("{$new_column['COLUMN_TYPE']} $nullable $default_clause $extra $comment");

                if (!isset($existing_columns_map[$column_name])) {
                    $alter_sql = "ALTER TABLE `$table_name` ADD COLUMN `$column_name` $column_definition";
                    $result = $this->wpdb->query($alter_sql);
                    error_log("Added column: $column_name to $table_name. Result: " . var_export($result, true));
                    if ($result === false) {
                        error_log("Error: " . $this->wpdb->last_error);
                    }
                } else {
                    $existing_column = $existing_columns_map[$column_name];

                    $needs_update = false;
                    if (
                        $existing_column['COLUMN_TYPE'] != $new_column['COLUMN_TYPE'] ||
                        $existing_column['IS_NULLABLE'] != $new_column['IS_NULLABLE'] ||
                        $existing_column['COLUMN_DEFAULT'] != $new_column['COLUMN_DEFAULT'] ||
                        $existing_column['EXTRA'] != $new_column['EXTRA']
                    ) {
                        $needs_update = true;
                    }

                    if ($needs_update) {
                        $alter_sql = "ALTER TABLE `$table_name` MODIFY COLUMN `$column_name` $column_definition";
                        $result = $this->wpdb->query($alter_sql);
                        error_log("Modified column: $column_name in $table_name. Result: " . var_export($result, true));
                        if ($result === false) {
                            error_log("Error: " . $this->wpdb->last_error);
                        }
                    }
                }
            }
        } finally {
            // Clean up by dropping the temporary table
            $this->wpdb->query("DROP TABLE IF EXISTS `$temp_table`");
            error_log("Dropped temp table: $temp_table");
        }

        return true;
    }

    /**
     * Validate table name to prevent SQL injection
     */
    private function validate_table_name($table_name)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
            return false;
        }
        return true;
    }

    /**
     * Validate column name to prevent SQL injection
     * 
     * @param string $column Column name to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_column($column)
    {
        if (strpos($column, '.') !== false) {
            $parts = explode('.', $column);
            foreach ($parts as $part) {
                if (!$this->validate_column_name($part)) {
                    return false;
                }
            }
            return true;
        }

        return $this->validate_column_name($column);
    }

    /**
     * Validate column name to prevent SQL injection
     */
    private function validate_column_name($column_name)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column_name)) {
            return false;
        }
        return true;
    }

    /**
     * Sanitize ORDER BY direction
     */
    private function sanitize_order_direction($direction)
    {
        return strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Insert (INSERT) a record in a given table.
     *
     * @param string $table_name The name of the database table (without prefix).
     * @param array $data An associative array of column-value pairs to insert or update.
     * @param array $format An array of formats for the $data values.
     *
     * @return int|false The number of rows affected or false on failure.
     */
    public function aiclinic_insert($table_name, $data, $format = null)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        $table = $this->wpdb->prefix . $table_name;

        // Auto-detect format if not provided
        if ($format === null) {
            $format = $this->auto_detect_format($data);
        }

        return $this->wpdb->insert($table, $data, $format);
    }

    /**
     * Auto-detect data format for wpdb operations
     */
    private function auto_detect_format($data)
    {
        $format = [];
        foreach ($data as $value) {
            if (is_int($value)) {
                $format[] = '%d';
            } elseif (is_float($value)) {
                $format[] = '%f';
            } else {
                $format[] = '%s';
            }
        }
        return $format;
    }

    /**
     * Build common WHERE conditions and params from args
     * 
     * @param array $args Arguments containing search, language, filters
     * @param string $alias Optional table alias for column references
     * @return array ['conditions' => array, 'params' => array]
     */
    private function build_where_conditions($args, $alias = '')
    {
        $conditions = [];
        $params = [];
        $prefix = $alias ? "$alias." : '';

        // Search condition
        if (!empty($args['search']) && !empty($args['column_search'])) {
            if (!$this->validate_column_name($args['column_search'])) {
                return ['conditions' => [], 'params' => []];
            }
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $conditions[] = "{$prefix}`{$args['column_search']}` LIKE %s";
            $params[] = $like;
        }

        // Language filter
        if (!empty($args['language'])) {
            $conditions[] = "{$prefix}`language` = %s";
            $params[] = $args['language'];
        }

        // Custom filters
        if (!empty($args['filters']) && is_array($args['filters'])) {
            foreach ($args['filters'] as $col => $val) {
                if (!$this->validate_column($col)) {
                    continue;
                }

                // Handle alias in column if provided
                $col_with_prefix = (strpos($col, '.') === false && $prefix)
                    ? $prefix . "`$col`"
                    : "`$col`";

                if (is_array($val)) {
                    // Support for IN clause
                    $placeholders = implode(',', array_fill(0, count($val), '%s'));
                    $conditions[] = "$col_with_prefix IN ($placeholders)";
                    $params = array_merge($params, $val);
                } else {
                    $conditions[] = "$col_with_prefix = %s";
                    $params[] = $val;
                }
            }
        }

        return ['conditions' => $conditions, 'params' => $params];
    }

    /**
     * Build JOIN clause from args
     * 
     * @param array $joins Array of join configurations
     *                     Each join: ['type' => 'INNER|LEFT|RIGHT', 'table' => 'table_name alias', 'on' => 'condition']
     * @return string SQL JOIN clause
     */
    private function build_joins($joins)
    {
        if (empty($joins) || !is_array($joins)) {
            return '';
        }

        $join_sql = '';
        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');

            // Parse table & alias: 'aiclinic_tags d' => table='aiclinic_tags', alias='d'
            [$join_table, $join_alias] = array_pad(explode(' ', $join['table'], 2), 2, '');

            if (!$this->validate_table_name($join_table)) {
                continue;
            }

            $full_table = $this->wpdb->prefix . $join_table . ($join_alias ? " $join_alias" : '');

            $on = $join['on'];

            $join_sql .= " $type JOIN $full_table ON $on ";
        }

        return $join_sql;
    }

    /**
     * Retrieve records from a specified table with pagination, optional search by keyword, and language filter.
     *
     * @param string $table_name     The name of the table to retrieve records from (can include alias: 'table alias')
     * @param array $args           Arguments array with keys: search, column_search, language, limit, offset, order_by, sort_direction, filters, select, joins, group_by, having
     *
     * @return array|false An array of records or false on error.
     */
    public function aiclinic_get_all($table_name, $args = [])
    {
        // Parse table & alias: 'aiclinic_clinics c' => table='aiclinic_clinics', alias='c'
        [$table, $alias] = array_pad(explode(' ', $table_name, 2), 2, '');

        if (!$this->validate_table_name($table)) {
            return false;
        }

        $base_table = $this->wpdb->prefix . $table . ($alias ? " $alias" : '');

        // Default arguments
        $defaults = [
            'search' => '',
            'column_search' => '',
            'language' => '',
            'limit' => 10,
            'offset' => 0,
            'order_by' => 'id',
            'sort_direction' => 'ASC',
            'filters' => [],
            'select' => '*',
            'joins' => [],
            'group_by' => '',
            'having' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        // Validate order_by column
        if (!$this->validate_column_name($args['order_by'])) {
            return false;
        }

        // Build WHERE conditions (pass alias for proper column prefixing)
        $where_data = $this->build_where_conditions($args, $alias);
        $conditions = $where_data['conditions'];
        $params = $where_data['params'];

        // Build JOIN clause
        $join_sql = $this->build_joins($args['joins']);

        // Build WHERE clause
        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sort_direction = $this->sanitize_order_direction($args['sort_direction']);
        $select = esc_sql($args['select']);

        // Handle order_by with potential alias
        $order_by_col = (strpos($args['order_by'], '.') === false && $alias)
            ? "$alias.`{$args['order_by']}`"
            : "`{$args['order_by']}`";

        $sql = "
            SELECT $select
            FROM $base_table
            $join_sql
            $where
        ";

        if (!empty($args['group_by'])) {
            $sql .= " GROUP BY {$args['group_by']} ";
        }

        if (!empty($args['having'])) {
            $sql .= " HAVING {$args['having']} ";
        }

        $sql .= " ORDER BY $order_by_col $sort_direction LIMIT %d OFFSET %d";

        $params[] = absint($args['limit']);
        $params[] = absint($args['offset']);

        return $this->wpdb->get_results($this->wpdb->prepare($sql, ...$params), ARRAY_A);
    }

    /**
     * Count the total number of records in a table with optional filtering.
     *
     * @param string $table_name      The name of the database table (without prefix, can include alias)
     * @param array $args            Arguments array with keys: search, column_search, language, filters, joins, group_by, having, distinct
     *
     * @return int|null Returns the total number of matching records, or null on failure.
     */
    public function aiclinic_count_record($table_name, $args = [])
    {
        // Parse table & alias
        [$table, $alias] = array_pad(explode(' ', $table_name, 2), 2, '');

        if (!$this->validate_table_name($table)) {
            return null;
        }

        $defaults = [
            'search' => '',
            'column_search' => '',
            'language' => '',
            'filters' => [],
            'joins' => [],
            'group_by' => '',
            'having' => '',
            'distinct' => '',
        ];

        $args = wp_parse_args($args, $defaults);
        $base_table = $this->wpdb->prefix . $table . ($alias ? " $alias" : '');

        // Build WHERE conditions
        $where_data = $this->build_where_conditions($args, $alias);
        $conditions = $where_data['conditions'];
        $params = $where_data['params'];

        // Build JOIN clause
        $join_sql = $this->build_joins($args['joins']);

        // Build WHERE clause
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Handle COUNT based on GROUP BY, HAVING, or DISTINCT
        if (!empty($args['group_by']) || !empty($args['having'])) {
            // When using GROUP BY, we need to count the number of groups
            // Use subquery approach for accuracy
            $inner_sql = "
                SELECT 1
                FROM $base_table
                $join_sql
                $where
            ";

            if (!empty($args['group_by'])) {
                $inner_sql .= " GROUP BY {$args['group_by']} ";
            }

            if (!empty($args['having'])) {
                $inner_sql .= " HAVING {$args['having']} ";
            }

            $sql = "SELECT COUNT(*) FROM ($inner_sql) as grouped_count";
        } elseif (!empty($args['distinct'])) {
            // COUNT DISTINCT for specific column
            $sql = "
            SELECT COUNT(DISTINCT {$args['distinct']})
            FROM $base_table
            $join_sql
            $where
        ";
        } else {
            // Simple COUNT(*)
            $sql = "
                SELECT COUNT(*)
                FROM $base_table
                $join_sql
                $where
            ";
        }

        return empty($params)
            ? (int) $this->wpdb->get_var($sql)
            : (int) $this->wpdb->get_var($this->wpdb->prepare($sql, ...$params));
    }

    /**
     * Upsert (INSERT or UPDATE) a record in a given table.
     *
     * @param string $table_name The name of the database table (without prefix).
     * @param array $data An associative array of column-value pairs to insert or update.
     * @param array $where_conditions An associative array of conditions to check for existing records.
     * @param array $format An array of formats for the $data values.
     * @param array $where_format An array of formats for the $where_conditions values.
     *
     * @return int|false The number of rows affected or false on failure.
     */
    public function aiclinic_upsert($table_name, $data, $where_conditions, $format = null, $where_format = null)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        // Validate column names in where conditions
        foreach (array_keys($where_conditions) as $col) {
            if (!$this->validate_column_name($col)) {
                return false;
            }
        }

        $table = $this->wpdb->prefix . $table_name;

        // Auto-detect formats if not provided
        if ($format === null) {
            $format = $this->auto_detect_format($data);
        }

        if ($where_format === null) {
            $where_format = $this->auto_detect_format($where_conditions);
        }

        // Build WHERE clause safely
        $where_parts = [];
        foreach (array_keys($where_conditions) as $key) {
            $where_parts[] = "`$key` = %s";
        }
        $where_clause = implode(" AND ", $where_parts);

        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE $where_clause",
            array_values($where_conditions)
        ));

        if ($existing) {
            return $this->wpdb->update($table, $data, $where_conditions, $format, $where_format);
        } else {
            // Merge where conditions into data for insert
            $insert_data = array_merge($data, $where_conditions);
            $insert_format = array_merge($format, $where_format);
            return $this->wpdb->insert($table, $insert_data, $insert_format);
        }
    }

    /**
     * Update a record in a given table.
     *
     * @param string $table_name The name of the database table (without prefix).
     * @param array $data An associative array of column-value pairs to update.
     * @param array $where_conditions An associative array of WHERE conditions.
     * @param array $format An array of formats for the $data values.
     * @param array $where_format An array of formats for the $where_conditions values.
     *
     * @return int|false Number of rows updated, or false on failure.
     */
    public function aiclinic_update($table_name, $data, $where_conditions, $format = null, $where_format = null)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        foreach (array_keys($where_conditions) as $col) {
            if (!$this->validate_column_name($col)) {
                return false;
            }
        }

        $table = $this->wpdb->prefix . $table_name;

        if ($format === null) {
            $format = $this->auto_detect_format($data);
        }

        if ($where_format === null) {
            $where_format = $this->auto_detect_format($where_conditions);
        }

        return $this->wpdb->update($table, $data, $where_conditions, $format, $where_format);
    }

    /**
     * Drop a table from the WordPress database.
     *
     * @param string $table_name The name of the table to drop (without prefix).
     * @return bool True if table was dropped successfully, false otherwise.
     */
    public function aiclinic_drop_table($table_name)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        $full_table_name = $this->wpdb->prefix . $table_name;

        $sql = $this->wpdb->prepare("DROP TABLE IF EXISTS `%s`", $full_table_name);

        // Note: Table names cannot be prepared with %s, need to validate instead
        $sql = "DROP TABLE IF EXISTS `$full_table_name`";

        return $this->wpdb->query($sql) !== false;
    }

    /**
     * Delete a record from a specific table based on ID.
     * 
     * @param string $table_name The table name (excluding the wp_ prefix).
     * @param int $id_record The ID of the record to delete.
     * @return int|false The number of rows affected. False if an error occurred.
     */
    public function aiclinic_delete_record_by_id($table_name, $id_record)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        $table = $this->wpdb->prefix . $table_name;

        return $this->wpdb->delete($table, ['id' => absint($id_record)], ['%d']);
    }

    /**
     * Delete records from a table based on conditions.
     * 
     * @param string $table_name The table name (excluding the wp_ prefix).
     * @param array $where_conditions The conditions for deletion.
     * @param array $where_format Format for where conditions.
     * @return int|false The number of rows affected. False if an error occurred.
     */
    public function aiclinic_delete_records($table_name, $where_conditions, $where_format = null)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        foreach (array_keys($where_conditions) as $col) {
            if (!$this->validate_column_name($col)) {
                return false;
            }
        }

        $table = $this->wpdb->prefix . $table_name;

        if ($where_format === null) {
            $where_format = $this->auto_detect_format($where_conditions);
        }

        return $this->wpdb->delete($table, $where_conditions, $where_format);
    }

    /**
     * Get values from a specific column in a table with a WHERE condition.
     *
     * @param string $table_name      The name of the table (without prefix).
     * @param string $target_column   The column name to retrieve values from.
     * @param string $where_column    The column name to apply the WHERE condition on.
     * @param mixed  $where_value     The value to match in the WHERE condition.
     * @return array|false            An array of values from the target column or false on error.
     */
    public function aiclinic_get_column_by_condition($table_name, $target_column, $where_column, $where_value)
    {
        if (
            !$this->validate_table_name($table_name) ||
            !$this->validate_column_name($target_column) ||
            !$this->validate_column_name($where_column)
        ) {
            return false;
        }

        $table = $this->wpdb->prefix . $table_name;

        $sql = "SELECT `$target_column` FROM `$table` WHERE `$where_column` = %s";
        return $this->wpdb->get_col($this->wpdb->prepare($sql, $where_value));
    }

    /**
     * Get a single record by ID.
     *
     * @param string $table_name The name of the table (without prefix).
     * @param int $id The ID of the record.
     * @return array|null The record or null if not found.
     */
    public function aiclinic_get_by_id($table_name, $id)
    {
        if (!$this->validate_table_name($table_name)) {
            return null;
        }

        $table = $this->wpdb->prefix . $table_name;

        $sql = "SELECT * FROM `$table` WHERE `id` = %d";
        return $this->wpdb->get_row($this->wpdb->prepare($sql, absint($id)), ARRAY_A);
    }

    /**
     * Check if a record exists based on conditions.
     *
     * @param string $table_name The name of the table (without prefix).
     * @param array $conditions An associative array of column-value pairs.
     * @return bool True if exists, false otherwise.
     */
    public function aiclinic_record_exists($table_name, $conditions)
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        foreach (array_keys($conditions) as $col) {
            if (!$this->validate_column_name($col)) {
                return false;
            }
        }

        $table = $this->wpdb->prefix . $table_name;

        $where_parts = [];
        foreach (array_keys($conditions) as $key) {
            $where_parts[] = "`$key` = %s";
        }
        $where_clause = implode(" AND ", $where_parts);

        $sql = "SELECT COUNT(*) FROM `$table` WHERE $where_clause";
        $count = $this->wpdb->get_var($this->wpdb->prepare($sql, array_values($conditions)));

        return $count > 0;
    }

    /**
     * Truncate a table (delete all records and reset AUTO_INCREMENT).
     *
     * @param string $table_name Table name without prefix.
     * @return bool True on success, false on failure.
     */
    public function aiclinic_truncate_table($table_name): bool
    {
        if (!$this->validate_table_name($table_name)) {
            return false;
        }

        $full_table_name = $this->wpdb->prefix . $table_name;
        $sql = "TRUNCATE TABLE `$full_table_name`";

        return $this->wpdb->query($sql) !== false;
    }
}
