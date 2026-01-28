-- Database Update V4: Add Username
-- 1. Add username column
ALTER TABLE users ADD COLUMN username VARCHAR(50) UNIQUE AFTER name;

-- 2. Populate username from email (part before @) for existing users
UPDATE users SET username = SUBSTRING_INDEX(email, '@', 1) WHERE username IS NULL;

-- 3. Make username NOT NULL after population
ALTER TABLE users MODIFY COLUMN username VARCHAR(50) NOT NULL;
