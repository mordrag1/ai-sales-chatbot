-- Migration: Add authentication fields to users table
-- Run this migration to enable auth from frontend

-- Add password hash field
ALTER TABLE `users` ADD COLUMN `password_hash` VARCHAR(255) NULL AFTER `api_key`;

-- Add auth token fields
ALTER TABLE `users` ADD COLUMN `auth_token` CHAR(64) NULL AFTER `password_hash`;
ALTER TABLE `users` ADD COLUMN `token_expires_at` TIMESTAMP NULL AFTER `auth_token`;

-- Add user status
ALTER TABLE `users` ADD COLUMN `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' AFTER `token_expires_at`;

-- Add dataset field if not exists
ALTER TABLE `users` ADD COLUMN `dataset` JSON NULL AFTER `widget_sound_enabled`;

-- Add index for auth token lookup
CREATE INDEX `idx_users_auth_token` ON `users` (`auth_token`, `token_expires_at`);

-- Add index for status
CREATE INDEX `idx_users_status` ON `users` (`status`);

