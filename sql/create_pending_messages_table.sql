CREATE TABLE `pending_messages` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(32) NOT NULL,
  `user_id` VARCHAR(64) NOT NULL,
  `message_role` VARCHAR(32) NOT NULL DEFAULT 'assistant',
  `message_text` TEXT NOT NULL,
  `metadata` JSON NULL,
  `delivered` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pending_user` (`client_id`, `user_id`, `delivered`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


