ALTER TABLE products
    ADD COLUMN image_data MEDIUMBLOB NULL,
    ADD COLUMN image_mime VARCHAR(50) NULL;

ALTER TABLE hero_banners
    ADD COLUMN image_data MEDIUMBLOB NULL,
    ADD COLUMN image_mime VARCHAR(50) NULL;