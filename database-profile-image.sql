-- Run this once on an existing database created before profile image support.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_image_data MEDIUMBLOB NULL,
    ADD COLUMN IF NOT EXISTS profile_image_mime VARCHAR(50) NULL;