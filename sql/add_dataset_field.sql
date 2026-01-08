-- Add dataset field to users table
-- This field stores a large JSON array of various data

ALTER TABLE `users` ADD COLUMN `dataset` JSON NULL AFTER `widget_sound_enabled`;

-- Example of updating dataset for a user:
-- UPDATE users SET dataset = '[{"key": "value"}, {"another": "data"}]' WHERE client_id = '1';


