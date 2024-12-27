-- Create subscriptions table
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `plan_id` bigint(20) UNSIGNED NOT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `status` enum('pending', 'active', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    `amount` decimal(10,2) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id_idx` (`user_id`),
    KEY `plan_id_idx` (`plan_id`),
    KEY `status_idx` (`status`),
    CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_subscriptions_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create payments table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscription_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `payment_method` varchar(50) NOT NULL,
    `reference_number` varchar(100) NOT NULL,
    `account_name` varchar(100) DEFAULT NULL,
    `account_number` varchar(50) DEFAULT NULL,
    `proof_file` varchar(255) DEFAULT NULL,
    `status` enum('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    `verification_notes` text,
    `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
    `verified_at` timestamp NULL DEFAULT NULL,
    `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `subscription_id_idx` (`subscription_id`),
    KEY `user_id_idx` (`user_id`),
    KEY `verified_by_idx` (`verified_by`),
    KEY `status_idx` (`status`),
    CONSTRAINT `fk_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`),
    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_payments_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
