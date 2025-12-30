CREATE TABLE `users` (
  `id` INT unsigned NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(32) NOT NULL,
  `name` VARCHAR(128) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `plan_id` VARCHAR(64) NOT NULL DEFAULT 'demo',
  `api_key` CHAR(32) NOT NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_client` (`client_id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`client_id`, `name`, `email`, `plan_id`, `api_key`, `metadata`)
VALUES
  ('1', 'Demo Operator', 'demo@example.com', 'starter', 'demoapikey0000000000000000000', JSON_OBJECT('assistant', 'Assistant A')),
  ('2', 'Alpha Operator', 'alpha@example.com', 'pro', 'alphaapikey00000000000000000000', JSON_OBJECT('assistant', 'Assistant B'));

