-- Add options column to settings table for select/dropdown UI type
-- This migration is safe to run multiple times (IF NOT EXISTS)

ALTER TABLE `settings`
ADD COLUMN IF NOT EXISTS `options` TEXT NULL AFTER `type`;
