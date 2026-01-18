-- Subscriptions table for tracking Polar.sh subscriptions
CREATE TABLE `subscriptions` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `user_id` INT unsigned NOT NULL,
  `polar_subscription_id` VARCHAR(128) NOT NULL,
  `polar_customer_id` VARCHAR(128) NULL,
  `polar_product_id` VARCHAR(128) NULL,
  `plan_id` VARCHAR(64) NOT NULL,
  `status` ENUM('active', 'canceled', 'past_due', 'expired', 'pending') NOT NULL DEFAULT 'pending',
  `current_period_start` TIMESTAMP NULL,
  `current_period_end` TIMESTAMP NULL,
  `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0,
  `canceled_at` TIMESTAMP NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriptions_polar` (`polar_subscription_id`),
  KEY `idx_subscriptions_user` (`user_id`),
  KEY `idx_subscriptions_status` (`status`),
  CONSTRAINT `fk_subscriptions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook logs table for debugging and audit
CREATE TABLE `webhook_logs` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(64) NOT NULL,
  `event_id` VARCHAR(128) NULL,
  `payload` JSON NOT NULL,
  `status` ENUM('success', 'error', 'skipped') NOT NULL DEFAULT 'success',
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_webhook_logs_type` (`event_type`),
  KEY `idx_webhook_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

