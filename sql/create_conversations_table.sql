CREATE TABLE `conversations` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `bot_id` VARCHAR(64) NOT NULL,
  `client_id` VARCHAR(32) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `dialog` JSON NOT NULL DEFAULT (JSON_ARRAY()),
  `user_agent` VARCHAR(512) NULL,
  `ip_address` VARCHAR(45) NULL,
  `referrer` VARCHAR(512) NULL,
  `page_url` VARCHAR(512) NULL,
  `metadata` JSON NULL,
  `message_count` INT unsigned NOT NULL DEFAULT 0,
  `last_message_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conversation` (`client_id`, `user_id`),
  INDEX `idx_client` (`client_id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_last_message` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


