-- Add read status columns to messaging tables
-- Run this in your MySQL client or phpMyAdmin

-- Add is_read column to message_logs table
ALTER TABLE message_logs ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0 AFTER status;

-- Add read_at column to message_logs table  
ALTER TABLE message_logs ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER is_read;

-- Add is_read column to messages table (if it exists)
ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0 AFTER status;

-- Add read_at column to messages table (if it exists)
ALTER TABLE messages ADD COLUMN IF NOT EXISTS read_at DATETIME NULL AFTER is_read;
