-- Table for storing general plugin settings
CREATE TABLE IF NOT EXISTS wp_aiclinic_plugin_settings (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Unique ID
    option_name VARCHAR(255) UNIQUE NOT NULL COMMENT 'Name of the setting option',
    option_value TEXT NOT NULL COMMENT 'Value of the setting option',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated timestamp'
) {CHARSET_COLLATE};

-- Table for storing customer information using the plugin
CREATE TABLE IF NOT EXISTS wp_aiclinic_customers (
    id MEDIUMINT(9) NOT NULL AUTO_INCREMENT PRIMARY KEY, -- Customer ID
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'User ID from wp_users',
    customer_key VARCHAR(255) NOT NULL UNIQUE COMMENT 'Unique identifier for the customer',
    customer_name VARCHAR(255) NOT NULL COMMENT 'Customer name',
    pricing_plan ENUM('basic', 'gold') NOT NULL DEFAULT 'basic' COMMENT 'Customer service plan',
    domain VARCHAR(255) UNIQUE COMMENT 'Customer domain',
    register_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Registration date',
    customer_status ENUM('active', 'inactive', 'unpaid') NOT NULL DEFAULT 'unpaid' COMMENT 'Customer status',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated timestamp',
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE
) {CHARSET_COLLATE};

-- Simplified table for storing plugin error logs
CREATE TABLE IF NOT EXISTS wp_aiclinic_plugin_error_logs (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Unique log ID
    action VARCHAR(100) NOT NULL COMMENT 'Name of the action being performed when the error occurred',
    message TEXT NOT NULL COMMENT 'Detailed error message or log content',
    context TEXT COMMENT 'Additional information such as post ID, user ID, or related data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) {CHARSET_COLLATE};
