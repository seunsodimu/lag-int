-- Simplified User Authentication Database Schema
-- This schema provides user management and authentication for the 3DCart NetSuite Integration

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    -- Indexes (avoid IF NOT EXISTS; inline indexes are idempotent with CREATE TABLE IF NOT EXISTS)
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- User sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    -- Useful indexes
    INDEX idx_user_id_sessions (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active_sessions (is_active),
    INDEX idx_sessions_cleanup (expires_at, is_active)
);

-- User activity log table
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    -- Useful indexes
    INDEX idx_user_id_activity (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at_activity (created_at)
);

-- Integration activity log table (optional - for tracking integration activities)
CREATE TABLE IF NOT EXISTS integration_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    activity_type ENUM('order_processing', 'customer_creation', 'manual_upload', 'configuration_change') NOT NULL,
    order_id VARCHAR(50),
    customer_id VARCHAR(50),
    status ENUM('success', 'failure', 'pending') NOT NULL,
    details JSON,
    error_message TEXT,
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    -- Useful indexes
    INDEX idx_user_id_integration (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_status (status),
    INDEX idx_created_at_integration (created_at),
    INDEX idx_order_id (order_id)
);

-- Insert default admin user (password: admin123 - CHANGE THIS IN PRODUCTION!)
INSERT INTO users (username, email, password_hash, role, first_name, last_name) 
VALUES (
    'admin', 
    'seun_sodimu@lagunatools.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
    'admin', 
    'System', 
    'Administrator'
) ON DUPLICATE KEY UPDATE 
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    role = VALUES(role);

-- Create a view for active users
CREATE OR REPLACE VIEW active_users AS
SELECT 
    id,
    username,
    email,
    role,
    first_name,
    last_name,
    created_at,
    last_login,
    CASE 
        WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN 'locked'
        WHEN is_active = 1 THEN 'active'
        ELSE 'inactive'
    END as status
FROM users
WHERE is_active = 1;

-- Create a view for user session summary
CREATE OR REPLACE VIEW user_session_summary AS
SELECT 
    u.id,
    u.username,
    u.email,
    u.role,
    COUNT(s.id) as active_sessions,
    MAX(s.created_at) as last_session_start
FROM users u
LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 1 AND s.expires_at > NOW()
GROUP BY u.id, u.username, u.email, u.role;

-- Password reset requests table
CREATE TABLE IF NOT EXISTS user_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    UNIQUE INDEX idx_token_hash (token_hash),
    INDEX idx_expires (expires_at),
    CONSTRAINT fk_upr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);