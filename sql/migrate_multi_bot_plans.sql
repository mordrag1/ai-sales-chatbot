-- Migration: Multi-bot support with plans
-- Run this migration on your database

-- 1. Create plans table
CREATE TABLE IF NOT EXISTS `plans` (
  `id` VARCHAR(32) NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `max_bots` INT unsigned NOT NULL DEFAULT 1,
  `max_messages_per_month` INT unsigned NULL DEFAULT NULL COMMENT 'NULL = unlimited',
  `allowed_domains` JSON NULL COMMENT 'NULL = any domain, array = restricted',
  `price_monthly` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
  `features` JSON NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default plans
INSERT INTO `plans` (`id`, `name`, `max_bots`, `max_messages_per_month`, `allowed_domains`, `price_monthly`, `features`) VALUES
  ('demo', 'Demo', 1, 500, '["https://weba-ai.com"]', 0.00, '{"support": "community"}'),
  ('start', 'Start', 1, 1000, NULL, 19.00, '{"support": "email"}'),
  ('pro', 'Pro', 5, 5000, NULL, 49.00, '{"support": "priority"}'),
  ('max', 'Max', NULL, NULL, NULL, 149.00, '{"support": "dedicated"}')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. Create bots table
CREATE TABLE IF NOT EXISTS `bots` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `user_id` INT unsigned NOT NULL,
  `bot_hash` CHAR(32) NOT NULL COMMENT 'Unique hash for widget embed',
  `name` VARCHAR(128) NOT NULL DEFAULT 'My Bot',
  `widget_title` VARCHAR(128) NOT NULL DEFAULT 'Support',
  `widget_operator_label` VARCHAR(128) NOT NULL DEFAULT 'Operator Online',
  `widget_welcome` TEXT NULL,
  `widget_placeholder` VARCHAR(128) NOT NULL DEFAULT 'Type your message...',
  `widget_typing_label` VARCHAR(128) NOT NULL DEFAULT 'Operator typing...',
  `widget_sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `allowed_domains` JSON NULL COMMENT 'NULL = use plan domains, array = bot-specific',
  `dataset` JSON NULL,
  `n8n_webhook_url` VARCHAR(512) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bots_hash` (`bot_hash`),
  INDEX `idx_bots_user` (`user_id`),
  CONSTRAINT `fk_bots_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create message_usage table for tracking monthly limits
CREATE TABLE IF NOT EXISTS `message_usage` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `user_id` INT unsigned NOT NULL,
  `bot_id` INT unsigned NOT NULL,
  `year_month` CHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `message_count` INT unsigned NOT NULL DEFAULT 0,
  `last_message_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usage_user_bot_month` (`user_id`, `bot_id`, `year_month`),
  INDEX `idx_usage_month` (`year_month`),
  CONSTRAINT `fk_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usage_bot` FOREIGN KEY (`bot_id`) REFERENCES `bots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Update users table: add plan-related fields
-- Run these one by one, ignore errors if column already exists

ALTER TABLE `users` ADD COLUMN `plan_expires_at` TIMESTAMP NULL AFTER `plan_id`;
-- If error "Duplicate column name", column already exists - skip

ALTER TABLE `users` ADD COLUMN `messages_this_month` INT unsigned NOT NULL DEFAULT 0 AFTER `plan_expires_at`;
-- If error "Duplicate column name", column already exists - skip

ALTER TABLE `users` ADD COLUMN `current_month` CHAR(7) NULL AFTER `messages_this_month`;
-- If error "Duplicate column name", column already exists - skip

-- 5. Migrate existing widget data to bots table (for existing users)
-- This creates a bot for each user that had widget settings
INSERT IGNORE INTO `bots` (`user_id`, `bot_hash`, `name`, `widget_title`, `widget_operator_label`, `widget_welcome`, `widget_placeholder`, `widget_typing_label`, `widget_sound_enabled`, `dataset`)
SELECT 
  `id` as `user_id`,
  `widget_hash` as `bot_hash`,
  CONCAT(`name`, ' Bot') as `name`,
  `widget_title`,
  `widget_operator_label`,
  `widget_welcome`,
  `widget_placeholder`,
  `widget_typing_label`,
  `widget_sound_enabled`,
  `dataset`
FROM `users` 
WHERE `widget_hash` IS NOT NULL AND `widget_hash` != '';

-- Note: Old widget_* columns in users table are kept for backward compatibility
-- They can be removed later after full migration

