-- =============================================
-- SISKATRA - Tabel Categories
-- =============================================

-- Hapus tabel jika sudah ada (untuk fresh install)
DROP TABLE IF EXISTS categories;

-- Buat tabel categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    category_icon VARCHAR(255) DEFAULT NULL,
    category_description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_name (category_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT CATEGORIES
-- Icon names match assets folder (Capital first letter, .png extension)
-- =====================================================
INSERT INTO categories (category_name, category_icon, category_description) VALUES
('Makanan', 'Makanan.png', 'Aneka makanan ringan dan berat'),
('Minuman', 'Minuman.png', 'Berbagai jenis minuman segar dan kemasan'),
('Barang', 'Barang.png', 'Produk kerajinan dan barang lainnya'),
('Jasa', 'Jasa.png', 'Layanan jasa dari siswa SMK')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);
