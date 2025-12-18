-- Add missing columns to artist_applications table

-- Add is_archived column (for soft delete)
ALTER TABLE artist_applications 
ADD COLUMN is_archived TINYINT(1) DEFAULT 0 AFTER `status`;

-- Add submitted_at timestamp column
ALTER TABLE artist_applications 
ADD COLUMN submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER is_archived;

-- Add user_id column if it doesn't exist (for linking to users table)
ALTER TABLE artist_applications 
ADD COLUMN user_id INT(11) AFTER id;

-- Update existing record to set user_id same as id (since id is currently the user_id)
UPDATE artist_applications SET user_id = id WHERE user_id IS NULL;
