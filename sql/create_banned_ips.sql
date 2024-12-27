-- Create table for banned IPs
CREATE TABLE IF NOT EXISTS `banned_ips` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) NOT NULL,
    `reason` text DEFAULT NULL,
    `banned_by` bigint(20) UNSIGNED NOT NULL,
    `expires_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_address_idx` (`ip_address`),
    KEY `banned_by_idx` (`banned_by`),
    KEY `expires_at_idx` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
