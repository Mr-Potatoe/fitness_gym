-- Create payment_accounts table
CREATE TABLE IF NOT EXISTS `payment_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_type` varchar(50) NOT NULL,
  `account_details` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample payment accounts
INSERT INTO `payment_accounts` (`account_type`, `account_details`, `is_active`) VALUES
('GCash', 'Account Name: VikingsFit Gym\nAccount Number: 09123456789', 1),
('Bank Transfer', 'Bank: BDO\nAccount Name: VikingsFit Gym Inc.\nAccount Number: 1234567890', 1),
('Maya', 'Account Name: VikingsFit Gym\nAccount Number: 09987654321', 1);
