<?php
session_start();

// Cek login dan role seller
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

$seller_id = $_SESSION['user_id'];
$current_tab = $_GET['tab'] ?? 'products'; // Tab aktif: products atau orders

// Ambil data seller
$stmt_seller = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'seller'");
$stmt_seller->bind_param("i", $seller_id);
$stmt_seller->execute();
$seller = $stmt_seller->get_result()->fetch_assoc();

if (!$seller) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Ambil semua produk penjual
$stmt_products = $conn->prepare("
    SELECT p.*, c.category_name,
           (SELECT COUNT(*) FROM orders WHERE product_id = p.product_id) as order_count,
           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as main_image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.seller_id = ?
    ORDER BY p.created_at DESC
");
$stmt_products->bind_param("i", $seller_id);
$stmt_products->execute();
$products = $stmt_products->get_result();

$stmt_orders = $conn->prepare("
    SELECT o.*, 
           p.product_name, p.price AS unit_price,
           b.username AS buyer_name, b.phone AS buyer_phone
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    JOIN users b ON o.buyer_id = b.id
    WHERE o.seller_id = ?
    ORDER BY o.order_date DESC
");
$stmt_orders->bind_param("i", $seller_id);
$stmt_orders->execute();
$orders = $stmt_orders->get_result();

// Hitung statistik
$stmt_stats = $conn->prepare("
    SELECT 
        COUNT(DISTINCT p.product_id) as total_products,
        SUM(p.stock) as total_stock,
        COUNT(o.order_id) as total_orders,
        SUM(o.total_price) as total_revenue,
        (SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'pending') as pending_orders
    FROM products p
    LEFT JOIN orders o ON p.product_id = o.product_id
    WHERE p.seller_id = ?
");
$stmt_stats->bind_param("ii", $seller_id, $seller_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Seller - SISKATRA</title>
    <!-- Fixed favicon extension to .png -->
    <link rel="icon" type="image/png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Lilita One', cursive;
            background-color: #f5f5f5;
            color: #333;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #284e7f);
            padding: 20px;
            min-height: 180px;
            position: relative;
            color: white;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-size: 18px;
        }

        .header-content {
            text-align: center;
            margin-top: 20px;
        }

        .siskatra-logo {
            color: #FFD43B;
            font-size: 48px;
            margin-bottom: 5px;
        }

        .subtitle {
            color: #FFD43B;
            font-size: 14px;
        }

        .profile-label {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            margin-top: 20px;
            padding-left: 20px;
        }

        /* Profile Section */
        .profile-section {
            background: white;
            margin: 20px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .profile-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .profile-info {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: #FFD43B;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            color: white;
        }

        .user-details h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .user-details p {
            font-size: 12px;
            color: #666;
            font-family: Arial, sans-serif;
        }

        .profile-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary {
            background: #0046ad;
            color: white;
        }

        .btn-primary:hover {
            background: #003280;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            padding: 20px;
            background: #f9f9f9;
        }

        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid #0046ad;
        }

        .stat-card.alert {
            border-left-color: #ffc107;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0046ad;
            margin-bottom: 5px;
        }

        .stat-card.alert .stat-value {
            color: #ffc107;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            font-family: Arial, sans-serif;
        }

        /* Tab navigation */
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #eee;
            padding: 0 20px;
            background: white;
        }

        .tab-btn {
            padding: 15px 25px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            font-family: Arial, sans-serif;
        }

        .tab-btn.active {
            color: #0046ad;
            border-bottom-color: #0046ad;
        }

        .tab-btn:hover {
            color: #0046ad;
        }

        .tab-content {
            display: none;
            padding: 20px;
        }

        .tab-content.active {
            display: block;
        }

        /* Products Section */
        .products-section {
            padding: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
        }

        .products-grid {
            display: grid;
            gap: 15px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            display: flex;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            align-items: flex-start;
        }

        .product-image {
            width: 100px;
            height: 100px;
            background: #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 40px;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .product-meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
            font-family: Arial, sans-serif;
        }

        .product-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 10px;
            font-size: 12px;
        }

        .detail-item {
            background: #f5f5f5;
            padding: 8px;
            border-radius: 5px;
            font-family: Arial, sans-serif;
        }

        .detail-label {
            color: #666;
            font-size: 11px;
        }

        .detail-value {
            color: #0046ad;
            font-weight: bold;
        }

        .product-actions {
            display: flex;
            gap: 8px;
            flex-direction: column;
        }

        .product-actions .btn {
            width: 100%;
            padding: 6px 10px;
            font-size: 11px;
        }

        /* Orders section */
        .orders-grid {
            display: grid;
            gap: 15px;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #0046ad;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .order-id {
            font-size: 16px;
            font-weight: bold;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-shipped { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }

        .order-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 12px;
            font-size: 12px;
            font-family: Arial, sans-serif;
        }

        .order-detail-item {
            display: flex;
            justify-content: space-between;
        }

        .order-detail-label { color: #666; }
        .order-detail-value { color: #0046ad; font-weight: bold; }

        .order-total {
            font-size: 16px;
            font-weight: bold;
            color: #0046ad;
            padding-top: 12px;
            border-top: 1px solid #eee;
            margin-bottom: 12px;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .order-actions .btn {
            flex: 1;
            min-width: 120px;
            padding: 8px 12px;
            font-size: 11px;
            font-family: Arial, sans-serif;
        }

        .btn-view-detail {
            background: #0046ad;
            color: white;
        }

        .btn-view-detail:hover {
            background: #003280;
        }

        .btn-wa {
            background: #25D366;
            color: white;
        }

        .btn-wa:hover {
            background: #1dbf4b;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .logout-section {
            text-align: right;
            padding: 0 20px 20px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .product-card {
                flex-direction: column;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-actions {
                width: 100%;
                margin-top: 10px;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .tab-btn {
                padding: 12px 15px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <a href="dashboard.php" class="back-btn">←</a>
        <div class="header-content">
            <h1 class="siskatra-logo">SISKATRA</h1>
            <p class="subtitle">Sistem Kategori dan Transaksi</p>
        </div>
        <p class="profile-label">PROFILE SELLER</p>
    </div>

    <!-- Profile Section -->
    <div class="profile-section">
        <div class="profile-header">
            <div class="profile-info">
                <div class="user-avatar">🏪</div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($seller['username']); ?></h3>
                    <p>Seller | <span style="color: #0046ad;">📞 <?php echo htmlspecialchars($seller['phone'] ?? 'N/A'); ?></span></p>
                </div>
            </div>
            <div class="profile-actions">
                <a href="edit_profile.php" class="btn btn-primary">Edit Profil</a>
                <a href="logout.php" class="btn btn-danger">Keluar</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_products'] ?? 0; ?></div>
                <div class="stat-label">Total Produk</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_stock'] ?? 0; ?></div>
                <div class="stat-label">Total Stok</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                <div class="stat-label">Pesanan</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">Rp <?php echo number_format($stats['total_revenue'] ?? 0, 0, ',', '.'); ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
            <!-- Tambah stat pending orders -->
            <div class="stat-card alert">
                <div class="stat-value"><?php echo $stats['pending_orders'] ?? 0; ?></div>
                <div class="stat-label">Pesanan Pending</div>
            </div>
        </div>

        <!-- Tab navigation -->
        <div class="tabs">
            <button class="tab-btn <?= $current_tab === 'products' ? 'active' : '' ?>" 
                    onclick="location.href='?tab=products'">
                Produk Saya
            </button>
            <button class="tab-btn <?= $current_tab === 'orders' ? 'active' : '' ?>" 
                    onclick="location.href='?tab=orders'">
                Pesanan Masuk (<?= $stats['pending_orders'] ?>)
            </button>
        </div>

        <!-- Tab Products -->
        <div class="tab-content <?= $current_tab === 'products' ? 'active' : '' ?>">
            <div class="products-section">
                <div class="section-header">
                    <h3 class="section-title">Produk Saya</h3>
                    <a href="add_product.php" class="btn btn-primary">+ Tambah Produk</a>
                </div>

                <?php if ($products->num_rows > 0): ?>
                    <div class="products-grid">
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <?php if ($product['main_image']): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($product['main_image']); ?>" alt="">
                                    <?php else: ?>
                                        📦
                                    <?php endif; ?>
                                </div>

                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-meta">Kategori: <strong><?php echo htmlspecialchars($product['category_name']); ?></strong></div>
                                    <div class="product-meta"><?php echo htmlspecialchars(substr($product['description'], 0, 80)) . (strlen($product['description']) > 80 ? '...' : ''); ?></div>

                                    <div class="product-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Harga</div>
                                            <div class="detail-value">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Stok</div>
                                            <div class="detail-value"><?php echo $product['stock']; ?> pcs</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Pesanan</div>
                                            <div class="detail-value"><?php echo $product['order_count']; ?></div>
                                        </div>
                                    </div>

                                    <div class="product-actions">
                                        <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-primary">Edit</a>
                                        <form method="POST" action="hapus_product.php" style="width: 100%;">
                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="width: 100%;" onclick="return confirm('Hapus produk ini?');">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>Belum ada produk. <a href="add_product.php" style="color: #0046ad; text-decoration: underline;">Tambah produk sekarang</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tab Orders -->
        <div class="tab-content <?= $current_tab === 'orders' ? 'active' : '' ?>">
            <div class="products-section">
                <div class="section-header">
                    <h3 class="section-title">Pesanan Masuk</h3>
                </div>

                <?php if ($orders->num_rows > 0): ?>
                    <div class="orders-grid">
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div class="order-id">Order #<?php echo $order['order_id']; ?></div>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>

                                <div class="order-details">
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Produk</span>
                                        <span class="order-detail-value"><?php echo htmlspecialchars($order['product_name']); ?></span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Pembeli</span>
                                        <span class="order-detail-value"><?php echo htmlspecialchars($order['buyer_name']); ?></span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Jumlah</span>
                                        <span class="order-detail-value"><?php echo $order['quantity']; ?> unit</span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Harga Satuan</span>
                                        <span class="order-detail-value">Rp <?php echo number_format($order['unit_price'], 0, ',', '.'); ?></span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Tanggal</span>
                                        <span class="order-detail-value"><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></span>
                                    </div>
                                    <div class="order-detail-item">
                                        <span class="order-detail-label">Kontak Pembeli</span>
                                        <span class="order-detail-value"><?php echo htmlspecialchars($order['buyer_phone'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>

                                <div class="order-total">
                                    Total: Rp <?php echo number_format($order['total_price'], 0, ',', '.'); ?>
                                </div>

                                <div class="order-actions">
                                    <a href="view_order_detail.php?id=<?php echo $order['order_id']; ?>" class="btn btn-view-detail">
                                        Lihat Detail
                                    </a>
                                    <?php if ($order['buyer_phone']): ?>
                                        <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $order['buyer_phone']); ?>?text=Halo, pesanan Anda #<?php echo $order['order_id']; ?> sedang diproses." 
                                           target="_blank" class="btn btn-wa">
                                            Chat Pembeli
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <p>Belum ada pesanan masuk</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
