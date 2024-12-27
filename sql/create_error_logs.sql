-- Create table for error logs
CREATE TABLE IF NOT EXISTS `error_logs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `error_type` varchar(50) NOT NULL,
    `error_message` text NOT NULL,
    `error_code` varchar(50) DEFAULT NULL,
    `file_name` varchar(255) NOT NULL,
    `line_number` int(11) NOT NULL,
    `stack_trace` text NOT NULL,
    `request_data` text NOT NULL,
    `user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `error_type_idx` (`error_type`),
    KEY `created_at_idx` (`created_at`),
    KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
