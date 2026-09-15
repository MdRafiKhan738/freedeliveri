CREATE TABLE IF NOT EXISTS product_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    media_type ENUM('image', 'video') NOT NULL,
    media_data MEDIUMBLOB NOT NULL,
    media_mime VARCHAR(50) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_media_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_media_product (product_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;