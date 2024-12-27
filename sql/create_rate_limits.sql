-- Create table for rate limiting
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` varchar(45) NOT NULL,
    `endpoint` varchar(50) NOT NULL,
    `window_start` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `attempt_count` int(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ip_endpoint_window` (`ip_address`, `endpoint`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
