-- Create table for file uploads
CREATE TABLE IF NOT EXISTS `file_uploads` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `original_name` varchar(255) NOT NULL,
    `stored_name` varchar(255) NOT NULL,
    `file_path` varchar(255) NOT NULL,
    `file_type` varchar(50) NOT NULL,
    `file_size` bigint(20) NOT NULL,
    `mime_type` varchar(255) NOT NULL,
    `hash` varchar(64) NOT NULL,
    `uploaded_by` bigint(20) UNSIGNED NOT NULL,
    `scan_status` enum('pending', 'clean', 'infected') DEFAULT 'pending',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `uploaded_by_idx` (`uploaded_by`),
    KEY `scan_status_idx` (`scan_status`),
    KEY `hash_idx` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
