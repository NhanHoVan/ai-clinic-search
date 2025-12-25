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
    pricing_plan ENUM('BASIC', 'GOLD') NOT NULL DEFAULT 'BASIC' COMMENT 'Customer service plan',
    domain VARCHAR(255) UNIQUE COMMENT 'Customer domain',
    register_date DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Registration date',
    customer_status ENUM('ACTIVE', 'INACTIVE', 'UNPAID') NOT NULL DEFAULT 'UNPAID' COMMENT 'Customer status',
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

-- Table for storing clinic information
CREATE TABLE IF NOT EXISTS wp_aiclinic_clinics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique identifier for the clinic',
    place_id VARCHAR(255) COMMENT 'Google Places place_id',
    name VARCHAR(255) NOT NULL COMMENT 'Clinic name',
    address TEXT COMMENT 'Full clinic address',
    province VARCHAR(100) COMMENT 'Province or city code',
    district VARCHAR(100) COMMENT 'District or area code',
    lat DECIMAL(10,7) COMMENT 'Latitude of the clinic location',
    lng DECIMAL(10,7) COMMENT 'Longitude of the clinic location',
    phone VARCHAR(50) COMMENT 'Contact phone number',
    email VARCHAR(255) COMMENT 'Contact email address',
    website VARCHAR(100) COMMENT 'website of clinic',
    description TEXT COMMENT 'Short description or clinic introduction',
    verify_status ENUM('AI_DETECTED', 'ADMIN_VERIFIED', 'OWNER_VERIFIED') NOT NULL DEFAULT 'AI_DETECTED' COMMENT 'Verification status of the clinic',
    is_active ENUM('ACTIVE', 'HIDDEN') NOT NULL DEFAULT 'ACTIVE' COMMENT 'Display status',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last record update time',
    INDEX idx_clinics_province_district (province, district),
    INDEX idx_clinics_name (name)
)  {CHARSET_COLLATE};

-- Table for storing clinic tags (e.g., "Orthopedic", "Oncology", "ENT")
CREATE TABLE IF NOT EXISTS wp_aiclinic_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, -- Unique tag ID
    name VARCHAR(100) NOT NULL COMMENT 'Tag name, e.g., "Orthopedic" or "Oncology"',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time'
) {CHARSET_COLLATE};

-- Junction table linking clinics and tags (many-to-many relationship)
CREATE TABLE IF NOT EXISTS wp_aiclinic_tag_relations (
    clinic_id BIGINT UNSIGNED NOT NULL COMMENT 'Related clinic ID',
    tag_id BIGINT UNSIGNED NOT NULL COMMENT 'Related tag ID',
    PRIMARY KEY (clinic_id, tag_id),
    FOREIGN KEY (clinic_id) REFERENCES wp_aiclinic_clinics(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES wp_aiclinic_tags(id) ON DELETE CASCADE
) {CHARSET_COLLATE};

-- Table for storing user reviews of clinics
CREATE TABLE IF NOT EXISTS wp_aiclinic_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY, -- Unique review ID
    clinic_id BIGINT UNSIGNED NOT NULL COMMENT 'Associated clinic ID',
    reviewer_name VARCHAR(255) COMMENT 'Name of the reviewer (optional)',
    rating TINYINT UNSIGNED COMMENT 'Rating score between 1 and 5',
    comment TEXT COMMENT 'User review comment text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Review submission time',
    FOREIGN KEY (clinic_id) REFERENCES wp_aiclinic_clinics(id) ON DELETE CASCADE
) {CHARSET_COLLATE};

-- Table for storing suggest clinic information
CREATE TABLE IF NOT EXISTS wp_aiclinic_clinic_suggestions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique identifier for the edit suggestion',
    suggestion_type ENUM('CREATE', 'EDIT') NOT NULL DEFAULT 'EDIT' COMMENT 'Type of suggestion',
    clinic_id BIGINT UNSIGNED NULL COMMENT 'Referenced clinic ID (NULL for CREATE suggestion)',
    user_id BIGINT UNSIGNED NULL COMMENT 'User who suggested the edit',
    suggested_fields JSON NOT NULL COMMENT 'Proposed clinic field changes in JSON format',
    status ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING' COMMENT 'Review status of the edit suggestion',
    reviewed_by BIGINT UNSIGNED NULL COMMENT 'Admin who reviewed the suggestion',
    review_note TEXT COMMENT 'Review note or rejection reason',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Suggestion creation time',
    reviewed_at DATETIME NULL COMMENT 'Suggestion review time',
    suggested_by ENUM('OWNER', 'OTHER') NOT NULL DEFAULT 'OTHER' COMMENT 'Source of suggestion: clinic owner or other user'
    INDEX idx_clinic_suggestion_clinic_status (clinic_id, status),
    INDEX idx_clinic_suggestion_user (user_id),
    CONSTRAINT fk_clinic_suggestion_clinic
    FOREIGN KEY (clinic_id) REFERENCES wp_aiclinic_clinics(id) ON DELETE CASCADE
) {CHARSET_COLLATE};
