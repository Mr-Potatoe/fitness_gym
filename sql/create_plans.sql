CREATE TABLE IF NOT EXISTS `plans` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `duration_months` int(11) NOT NULL,
    `price` decimal(10,2) NOT NULL,
    `features` json DEFAULT NULL,
    `created_by` bigint(20) UNSIGNED DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `created_by_idx` (`created_by`),
    CONSTRAINT `fk_plans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
