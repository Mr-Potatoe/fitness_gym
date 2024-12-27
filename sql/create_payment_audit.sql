-- Create table for payment audit trail
CREATE TABLE IF NOT EXISTS `payment_audit_trail` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` bigint(20) UNSIGNED NOT NULL,
    `action` varchar(50) NOT NULL,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `notes` text DEFAULT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `payment_id_idx` (`payment_id`),
    KEY `user_id_idx` (`user_id`),
    KEY `action_idx` (`action`),
    KEY `created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for payment reconciliation
CREATE TABLE IF NOT EXISTS `payment_reconciliation` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` bigint(20) UNSIGNED NOT NULL,
    `reconciled_by` bigint(20) UNSIGNED NOT NULL,
    `reconciliation_date` date NOT NULL,
    `bank_reference` varchar(100) DEFAULT NULL,
    `bank_amount` decimal(10,2) NOT NULL,
    `difference_amount` decimal(10,2) DEFAULT 0.00,
    `status` enum('matched','unmatched','pending') NOT NULL DEFAULT 'pending',
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `payment_id_idx` (`payment_id`),
    KEY `reconciled_by_idx` (`reconciled_by`),
    KEY `status_idx` (`status`),
    KEY `reconciliation_date_idx` (`reconciliation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for payment disputes
CREATE TABLE IF NOT EXISTS `payment_disputes` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` bigint(20) UNSIGNED NOT NULL,
    `reported_by` bigint(20) UNSIGNED NOT NULL,
    `dispute_type` varchar(50) NOT NULL,
    `description` text NOT NULL,
    `status` enum('open','investigating','resolved','closed') NOT NULL DEFAULT 'open',
    `resolution` text DEFAULT NULL,
    `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
    `resolved_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `payment_id_idx` (`payment_id`),
    KEY `reported_by_idx` (`reported_by`),
    KEY `status_idx` (`status`),
    KEY `dispute_type_idx` (`dispute_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create table for payment notifications
CREATE TABLE IF NOT EXISTS `payment_notifications` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` bigint(20) UNSIGNED NOT NULL,
    `user_id` bigint(20) UNSIGNED NOT NULL,
    `type` varchar(50) NOT NULL,
    `message` text NOT NULL,
    `read` tinyint(1) NOT NULL DEFAULT 0,
    `read_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `payment_id_idx` (`payment_id`),
    KEY `user_id_idx` (`user_id`),
    KEY `type_idx` (`type`),
    KEY `read_idx` (`read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
