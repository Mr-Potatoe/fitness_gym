-- Create table for two-factor authentication codes
CREATE TABLE IF NOT EXISTS `two_factor_codes` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `code` varchar(10) NOT NULL,
    `expires_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 5 MINUTE),
    `used` tinyint(1) NOT NULL DEFAULT 0,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`),
    KEY `code_idx` (`code`),
    KEY `expires_at_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for two-factor authentication settings
CREATE TABLE IF NOT EXISTS `two_factor_settings` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `enabled` tinyint(1) NOT NULL DEFAULT 0,
    `backup_codes` text DEFAULT NULL,
    `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for backup codes
CREATE TABLE IF NOT EXISTS `backup_codes` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `code` varchar(20) NOT NULL,
    `used` tinyint(1) NOT NULL DEFAULT 0,
    `used_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_code_idx` (`user_id`, `code`),
    KEY `used_idx` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
