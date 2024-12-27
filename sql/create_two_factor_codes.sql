-- Create table for two-factor authentication codes
CREATE TABLE IF NOT EXISTS `two_factor_codes` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `code` varchar(10) NOT NULL,
    `expires_at` timestamp NOT NULL,
    `used` tinyint(1) NOT NULL DEFAULT 0,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`),
    KEY `expires_at_idx` (`expires_at`),
    KEY `code_idx` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
