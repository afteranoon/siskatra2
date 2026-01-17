<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

$user_id = $_SESSION['user_id'];
$product_id = (int)($_GET['id'] ?? 0);

// Cek apakah produk milik seller ini
$stmt_product = $conn->prepare("
    SELECT p.*, c.category_name, pi.image_url 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
    WHERE p.product_id = ? AND p.seller_id = ?
");
$stmt_product->bind_param("ii", $product_id, $user_id);
$stmt_product->execute();
$product = $stmt_product->get_result()->fetch_assoc();

if (!$product) {
    header('Location: profile_seller.php');
    exit();
}

// Ambil pesanan untuk produk ini
$stmt_orders = $conn->prepare("
    SELECT o.*, u.username AS buyer_username, u.phone AS buyer_phone
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    WHERE o.product_id = ?
    ORDER BY o.order_date DESC
");
$stmt_orders->bind_param("i", $product_id);
$stmt_orders->execute();
$orders = $stmt_orders->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung statistik
$total_orders = count($orders);
$total_revenue = array_sum(array_column($orders, 'total_price'));
$pending_orders = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Produk - SISKATRA</title>
    <link rel="website icon" type="png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; color: #333; }
        
        .header {
            background: #0046ad;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .back-btn {
            width: 40px; height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
        .header-title {
            color: #FFD43B;
            font-family: 'Lilita One', cursive;
            font-size: 24px;
        }
        
        .main-content { max-width: 1000px; margin: 0 auto; padding: 30px 20px; }
        
        .product-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .product-image {
            width: 120px; height: 120px;
            background: #D9D9D9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            flex-shrink: 0;
        }
        .product-image img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
        .product-details h2 { font-size: 24px; margin-bottom: 10px; }
        .product-details p { color: #666; margin-bottom: 5px; }
        .product-price { font-size: 20px; color: #0046ad; font-weight: bold; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-number { font-size: 28px; font-weight: bold; color: #0046ad; }
        .stat-label { color: #666; font-size: 14px; margin-top: 5px; }
        
        .section-title { font-size: 22px; margin-bottom: 20px; }
        
        .orders-table {
            width: 100%;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .orders-table th, .orders-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .orders-table th { background: #0046ad; color: white; }
        .orders-table tr:hover { background: #f9f9f9; }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }
        .btn-primary { background: #0046ad; color: white; }
        .btn-wa { background: #25D366; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .orders-table { font-size: 14px; }
            .product-info { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="profile_seller.php" class="back-btn">←</a>
        <h1 class="header-title">Pesanan Produk</h1>
    </div>

    <div class="main-content">
        <div class="product-info">
            <div class="product-image">
                <?php if ($product['image_url'] && file_exists('uploads/' . $product['image_url'])): ?>
                    <img src="uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['product_name']) ?>">
                <?php else: ?>
                    📦
                <?php endif; ?>
            </div>
            <div class="product-details">
                <h2><?= htmlspecialchars($product['product_name']) ?></h2>
                <p>Kategori: <?= htmlspecialchars($product['category_name'] ?? 'Tidak ada') ?></p>
                <p>Stok: <?= $product['stock'] ?> unit</p>
                <p class="product-price">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_orders ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $pending_orders ?></div>
                <div class="stat-label">Menunggu Konfirmasi</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Rp <?= number_format($total_revenue, 0, ',', '.') ?></div>
                <div class="stat-label">Total Pendapatan</div>
            </div>
        </div>

        <h3 class="section-title">Daftar Pesanan</h3>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <p style="font-size: 48px; margin-bottom: 15px;">📋</p>
                <p>Belum ada pesanan untuk produk ini</p>
            </div>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Pembeli</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= $order['order_id'] ?></td>
                        <td><?= htmlspecialchars($order['buyer_username']) ?></td>
                        <td><?= $order['quantity'] ?></td>
                        <td>Rp <?= number_format($order['total_price'], 0, ',', '.') ?></td>
                        <td>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td>
                        <td>
                            <a href="view_order_detail.php?id=<?= $order['order_id'] ?>" class="btn btn-primary">Detail</a>
                            <?php if ($order['buyer_phone']): ?>
                                <a href="https://wa.me/<?= $order['buyer_phone'] ?>" target="_blank" class="btn btn-wa">WA</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
