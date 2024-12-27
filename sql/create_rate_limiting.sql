-- Create table for rate limiting
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) NOT NULL,
    `endpoint` varchar(255) NOT NULL,
    `requests` int(11) NOT NULL DEFAULT 1,
    `window_start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_endpoint_window` (`ip_address`, `endpoint`, `window_start`),
    KEY `window_start_idx` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for error logging
CREATE TABLE IF NOT EXISTS `error_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `error_type` varchar(50) NOT NULL,
    `error_message` text NOT NULL,
    `error_code` varchar(50) DEFAULT NULL,
    `file_name` varchar(255) NOT NULL,
    `line_number` int(11) DEFAULT NULL,
    `stack_trace` text,
    `request_data` text,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `error_type_idx` (`error_type`),
    KEY `created_at_idx` (`created_at`),
    KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for file uploads
CREATE TABLE IF NOT EXISTS `file_uploads` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_name` varchar(255) NOT NULL,
    `stored_name` varchar(255) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_type` varchar(100) NOT NULL,
    `file_size` bigint(20) NOT NULL,
    `mime_type` varchar(100) NOT NULL,
    `hash` varchar(64) NOT NULL,
    `uploaded_by` bigint(20) UNSIGNED NOT NULL,
    `scan_status` enum('pending','clean','infected') NOT NULL DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `uploaded_by_idx` (`uploaded_by`),
    KEY `scan_status_idx` (`scan_status`),
    KEY `hash_idx` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
