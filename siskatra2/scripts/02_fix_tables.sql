-- =====================================================
-- FIX SCRIPT - Jalankan ini untuk memperbaiki database
-- =====================================================

USE siskatra_db;

-- =====================================================
-- 1. Pastikan tabel stories ada dengan struktur yang benar
-- =====================================================

CREATE TABLE IF NOT EXISTS stories (
    story_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    caption TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 2. Fix password admin (password: admin123)
-- =====================================================

UPDATE users SET password = 'admin123' WHERE username = 'admin';

-- Jika admin belum ada, tambahkan
INSERT IGNORE INTO users (username, password, phone, role) VALUES
('admin', 'admin123', '081234567890', 'admin');
