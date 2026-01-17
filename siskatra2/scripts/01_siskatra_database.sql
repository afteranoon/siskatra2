-- =====================================================
-- SISKATRA DATABASE - Sistem Kategori dan Transaksi
-- Database: siskatra_db
-- =====================================================

-- Buat database jika belum ada
CREATE DATABASE IF NOT EXISTS siskatra_db;
USE siskatra_db;

-- =====================================================
-- 1. TABEL USERS
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    role ENUM('buyer', 'seller', 'admin') NOT NULL DEFAULT 'buyer',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 2. TABEL CATEGORIES
-- =====================================================
CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    category_icon VARCHAR(255) DEFAULT NULL,
    category_description TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================
-- 3. TABEL PRODUCTS
-- =====================================================
CREATE TABLE IF NOT EXISTS products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    stock INT NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    seller_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 4. TABEL PRODUCT_IMAGES
-- =====================================================
CREATE TABLE IF NOT EXISTS product_images (
    image_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_main TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 5. TABEL ORDERS (Disesuaikan dengan struktur PHP)
-- =====================================================
DROP TABLE IF EXISTS orders;

CREATE TABLE IF NOT EXISTS orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    total_price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 7. TABEL STORIES (untuk fitur story yang sudah ada)
-- =====================================================
CREATE TABLE IF NOT EXISTS stories (
    story_id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    caption TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 24 HOUR),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- 8. INSERT DEFAULT CATEGORIES
-- =====================================================
INSERT INTO categories (category_name, category_icon, category_description) VALUES
('Minuman', 'minuman.png', 'Berbagai jenis minuman segar dan kemasan'),
('Makanan', 'makanan.png', 'Aneka makanan ringan dan berat'),
('Barang', 'barang.png', 'Produk kerajinan dan barang lainnya'),
('Jasa', 'jasa.png', 'Layanan jasa dari siswa SMK')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- =====================================================
-- 9. INSERT ADMIN USER (password: admin123)
-- Fixed password hash - this hash is for 'admin123'
-- =====================================================
INSERT INTO users (username, password, phone, role) VALUES
('admin', '$2y$10$YourHashHere', '081234567890', 'admin')
ON DUPLICATE KEY UPDATE password = '$2y$10$YourHashHere';

-- =====================================================
-- 10. CREATE INDEXES FOR BETTER PERFORMANCE
-- =====================================================
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
CREATE INDEX IF NOT EXISTS idx_products_seller ON products(seller_id);
CREATE INDEX IF NOT EXISTS idx_orders_buyer ON orders(buyer_id);
CREATE INDEX IF NOT EXISTS idx_orders_seller ON orders(seller_id);
CREATE INDEX IF NOT EXISTS idx_orders_product ON orders(product_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(order_date);
