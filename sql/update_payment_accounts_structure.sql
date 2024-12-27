-- Drop existing payment_accounts table if it exists
DROP TABLE IF EXISTS `payment_accounts`;

-- Create payment_accounts table with new structure
CREATE TABLE `payment_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_type` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample payment accounts
INSERT INTO `payment_accounts` (`account_type`, `account_name`, `account_number`, `is_active`) VALUES
('GCash', 'VikingsFit Gym', '09123456789', 1),
('Bank Transfer', 'VikingsFit Gym Inc.', '1234567890', 1),
('Maya', 'VikingsFit Gym', '09987654321', 1);
