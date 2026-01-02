CREATE TABLE `users` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(32) NOT NULL,
  `widget_hash` CHAR(32) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `plan_id` VARCHAR(64) NOT NULL DEFAULT 'demo',
  `api_key` CHAR(32) NOT NULL,
  `widget_title` VARCHAR(128) NOT NULL DEFAULT 'Support',
  `widget_operator_label` VARCHAR(128) NOT NULL DEFAULT 'Operator Online',
  `widget_welcome` TEXT NULL,
  `widget_placeholder` VARCHAR(128) NOT NULL DEFAULT 'Type your message...',
  `widget_typing_label` VARCHAR(128) NOT NULL DEFAULT 'Operator typing...',
  `widget_sound_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_client` (`client_id`),
  UNIQUE KEY `uq_users_hash` (`widget_hash`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`client_id`, `widget_hash`, `name`, `email`, `plan_id`, `api_key`, `widget_title`, `widget_operator_label`, `widget_welcome`, `metadata`)
VALUES
  ('1', 'a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6', 'Demo Operator', 'demo@example.com', 'starter', 'demoapikey0000000000000000000', 'Demo Support', 'Assistant Online', 'Hello! I am the assistant for Client 1. How can I help you today?', JSON_OBJECT('assistant', 'Assistant A')),
  ('2', 'q1w2e3r4t5y6u7i8o9p0a1s2d3f4g5h6', 'Alpha Operator', 'alpha@example.com', 'pro', 'alphaapikey00000000000000000000', 'Alpha Support', 'Operator Online', 'Welcome to Alpha! How can we assist you?', JSON_OBJECT('assistant', 'Assistant B'));
