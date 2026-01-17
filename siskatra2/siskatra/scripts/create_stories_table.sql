-- Tabel untuk fitur Story (seperti Instagram Story)
-- Story hanya bertahan 24 jam

CREATE TABLE IF NOT EXISTS stories (
    story_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    caption TEXT,
    product_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP GENERATED ALWAYS AS (created_at + INTERVAL 24 HOUR) STORED,
    views_count INT DEFAULT 0,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE SET NULL,
    INDEX idx_expires (expires_at),
    INDEX idx_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
