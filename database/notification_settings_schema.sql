-- Notification Settings Schema
-- This table stores email notification recipients by notification type

CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notification_type` enum('3dcart_success_webhook','3dcart_failed_webhook','hubspot_success_webhook','hubspot_failed_webhook','3dcart_success_manual','3dcart_failed_manual','hubspot_success_manual','hubspot_failed_manual') NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_notification_recipient` (`notification_type`, `recipient_email`),
  KEY `idx_notification_type` (`notification_type`),
  KEY `idx_active` (`is_active`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification settings
-- web_dev@lagunatools.com should receive all notifications by default
INSERT INTO `notification_settings` (`notification_type`, `recipient_email`, `created_by`) VALUES
('3dcart_success_webhook', 'web_dev@lagunatools.com', 1),
('3dcart_failed_webhook', 'web_dev@lagunatools.com', 1),
('hubspot_success_webhook', 'web_dev@lagunatools.com', 1),
('hubspot_failed_webhook', 'web_dev@lagunatools.com', 1),
('3dcart_success_manual', 'web_dev@lagunatools.com', 1),
('3dcart_failed_manual', 'web_dev@lagunatools.com', 1),
('hubspot_success_manual', 'web_dev@lagunatools.com', 1),
('hubspot_failed_manual', 'web_dev@lagunatools.com', 1)
ON DUPLICATE KEY UPDATE 
  `updated_at` = current_timestamp();

-- Create view for easy querying of active notification settings
CREATE OR REPLACE VIEW `active_notification_settings` AS
SELECT 
  `notification_type`,
  GROUP_CONCAT(`recipient_email` SEPARATOR ',') as `recipients`
FROM `notification_settings` 
WHERE `is_active` = 1 
GROUP BY `notification_type`;