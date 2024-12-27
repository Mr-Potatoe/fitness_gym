-- Create table for user activity logs
CREATE TABLE IF NOT EXISTS `user_activity_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `action` varchar(100) NOT NULL,
    `details` text DEFAULT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`),
    KEY `action_idx` (`action`),
    KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for admin activity logs
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id` bigint(20) UNSIGNED NOT NULL,
    `action` varchar(100) NOT NULL,
    `target_type` varchar(50) NOT NULL,
    `target_id` bigint(20) UNSIGNED DEFAULT NULL,
    `details` text DEFAULT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `admin_id_idx` (`admin_id`),
    KEY `action_idx` (`action`),
    KEY `target_type_idx` (`target_type`),
    KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for security events
CREATE TABLE IF NOT EXISTS `security_events` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` varchar(50) NOT NULL,
    `severity` enum('low', 'medium', 'high', 'critical') NOT NULL,
    `description` text NOT NULL,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `additional_data` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `event_type_idx` (`event_type`),
    KEY `severity_idx` (`severity`),
    KEY `user_id_idx` (`user_id`),
    KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for login attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` varchar(255) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `success` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `username_ip_idx` (`username`, `ip_address`),
    KEY `attempt_time_idx` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for password reset requests
CREATE TABLE IF NOT EXISTS `password_reset_requests` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `token` varchar(64) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
    `used` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token_idx` (`token`),
    KEY `user_id_idx` (`user_id`),
    KEY `expires_at_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for session tracking
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `session_id` varchar(64) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_id_idx` (`session_id`),
    KEY `user_id_idx` (`user_id`),
    KEY `last_activity_idx` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for API access logs
CREATE TABLE IF NOT EXISTS `api_access_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `endpoint` varchar(255) NOT NULL,
    `method` varchar(10) NOT NULL,
    `request_data` text DEFAULT NULL,
    `response_code` int(11) NOT NULL,
    `response_time` float NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`),
    KEY `endpoint_idx` (`endpoint`),
    KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
