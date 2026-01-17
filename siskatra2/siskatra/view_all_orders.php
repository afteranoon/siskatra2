<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'seller') {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

$user_id = $_SESSION['user_id'];

// Filter status
$status_filter = $_GET['status'] ?? '';
$where_status = $status_filter ? "AND o.status = ?" : "";

// Ambil semua pesanan untuk seller
$sql = "
    SELECT o.*, p.product_name, p.price AS unit_price, u.username AS buyer_username, u.phone AS buyer_phone,
           pi.image_url
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
    WHERE p.seller_id = ? $where_status
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
if ($status_filter) {
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung statistik
$stats = [
    'total' => count($orders),
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'revenue' => 0
];

foreach ($orders as $order) {
    $stats[$order['status']] = ($stats[$order['status']] ?? 0) + 1;
    if ($order['status'] === 'completed') {
        $stats['revenue'] += $order['total_price'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Pesanan - SISKATRA</title>
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
        }
        .header-title { color: #FFD43B; font-family: 'Lilita One', cursive; font-size: 24px; }
        
        .main-content { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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
        
        .filter-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid #ddd;
            color: #333;
            background: white;
        }
        .filter-btn.active { background: #0046ad; color: white; border-color: #0046ad; }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .order-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .order-id { font-weight: bold; color: #0046ad; }
        .order-date { font-size: 12px; color: #999; }
        
        .order-product {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .product-thumb {
            width: 60px; height: 60px;
            background: #D9D9D9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .product-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .product-info h4 { font-size: 16px; margin-bottom: 5px; }
        .product-info p { font-size: 13px; color: #666; }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .order-total { font-size: 18px; font-weight: bold; color: #333; }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-detail {
            padding: 8px 15px;
            background: #0046ad;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            grid-column: 1 / -1;
        }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="profile_seller.php" class="back-btn">←</a>
        <h1 class="header-title">Semua Pesanan</h1>
    </div>

    <div class="main-content">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Pesanan</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['completed'] ?></div>
                <div class="stat-label">Selesai</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">Rp <?= number_format($stats['revenue'], 0, ',', '.') ?></div>
                <div class="stat-label">Pendapatan</div>
            </div>
        </div>

        <div class="filter-bar">
            <a href="view_all_orders.php" class="filter-btn <?= !$status_filter ? 'active' : '' ?>">Semua</a>
            <a href="?status=pending" class="filter-btn <?= $status_filter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?status=confirmed" class="filter-btn <?= $status_filter === 'confirmed' ? 'active' : '' ?>">Dikonfirmasi</a>
            <a href="?status=processing" class="filter-btn <?= $status_filter === 'processing' ? 'active' : '' ?>">Diproses</a>
            <a href="?status=completed" class="filter-btn <?= $status_filter === 'completed' ? 'active' : '' ?>">Selesai</a>
            <a href="?status=cancelled" class="filter-btn <?= $status_filter === 'cancelled' ? 'active' : '' ?>">Dibatalkan</a>
        </div>

        <div class="orders-grid">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <p style="font-size: 48px; margin-bottom: 15px;">📋</p>
                    <p>Belum ada pesanan</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <span class="order-id">#<?= $order['order_id'] ?></span>
                        <span class="order-date"><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></span>
                    </div>
                    <div class="order-product">
                        <div class="product-thumb">
                            <?php if ($order['image_url'] && file_exists('uploads/' . $order['image_url'])): ?>
                                <img src="uploads/<?= htmlspecialchars($order['image_url']) ?>" alt="">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h4><?= htmlspecialchars($order['product_name']) ?></h4>
                            <p>Qty: <?= $order['quantity'] ?> x Rp <?= number_format($order['unit_price'], 0, ',', '.') ?></p>
                            <p>Pembeli: <?= htmlspecialchars($order['buyer_username']) ?></p>
                        </div>
                    </div>
                    <div class="order-footer">
                        <div>
                            <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                            <span class="order-total">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></span>
                        </div>
                        <a href="view_order_detail.php?id=<?= $order['order_id'] ?>" class="btn-detail">Detail</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
