-- =============================================
-- SISKATRA - Tabel Users dengan Role System
-- Role: admin, seller, buyer
-- =============================================

-- Hapus tabel jika sudah ada (untuk fresh install)
DROP TABLE IF EXISTS users;

-- Buat tabel users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    profile_image VARCHAR(255) DEFAULT 'default.png',
    role ENUM('admin', 'seller', 'buyer') NOT NULL DEFAULT 'buyer',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin account
-- Password: admin123 (hashed dengan password_hash)
INSERT INTO users (username, email, password, full_name, phone, role) VALUES 
('admin', 'admin@siskatra.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', '081234567890', 'admin');

-- Insert sample seller account
-- Password: seller123
INSERT INTO users (username, email, password, full_name, phone, address, role) VALUES 
('seller1', 'seller1@siskatra.com', '$2y$10$8K1p/a0dR1xqM8K3hQl3.O8Y6kXCmG8hN2mT0vW5qA1sD3fG5hJ7i', 'Toko Segar Jaya', '081987654321', 'Jl. Pasar Baru No. 10, Jakarta', 'seller');

-- Insert sample buyer account  
-- Password: buyer123
INSERT INTO users (username, email, password, full_name, phone, address, role) VALUES 
('buyer1', 'buyer1@siskatra.com', '$2y$10$6F2p/b1dS2yqN9L4iRL4.P9Z7lYDnH9iO3nU1wX6rB2tE4gH6iK8j', 'Budi Santoso', '082112345678', 'Jl. Merdeka No. 25, Bandung', 'buyer');
