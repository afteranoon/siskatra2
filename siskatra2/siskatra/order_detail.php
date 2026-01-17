<?php
/**
 * SISKATRA - Detail Pesanan
 * Updated to use mysqli and match the actual orders table structure
 */
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

$order_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$stmt = $conn->prepare("
    SELECT o.*, 
           p.product_name, p.price AS unit_price, p.description AS product_desc,
           buyer.username AS buyer_name, buyer.phone AS buyer_phone,
           seller.username AS seller_name, seller.phone AS seller_phone,
           pi.image_url
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    JOIN users buyer ON o.buyer_id = buyer.id
    JOIN users seller ON o.seller_id = seller.id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
    WHERE o.order_id = ? AND (o.buyer_id = ? OR o.seller_id = ?)
");
$stmt->bind_param("iii", $order_id, $user_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: dashboard.php');
    exit();
}

// Update status (untuk seller)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'seller' && $order['seller_id'] == $user_id) {
    $new_status = $_POST['status'] ?? '';
    $allowed_status = ['confirmed', 'processing', 'shipped', 'completed'];
    
    if (in_array($new_status, $allowed_status)) {
        $update = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $update->bind_param("si", $new_status, $order_id);
        $update->execute();
        header("Location: order_detail.php?id=$order_id&updated=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - SISKATRA</title>
    <link rel="icon" type="image/png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: #0046ad;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 { font-size: 18px; }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
        }
        
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 16px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item label {
            display: block;
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .info-item span {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #cce5ff; color: #004085; }
        .badge-processing { background: #e2e3f3; color: #383d96; }
        .badge-shipped { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #d4edda; color: #155724; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .product-section {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .product-section:last-child { border-bottom: none; }
        
        .product-img {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .product-info { flex: 1; }
        
        .product-info h4 {
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .product-info p {
            font-size: 12px;
            color: #666;
        }
        
        .product-price {
            text-align: right;
            font-weight: 600;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding-top: 15px;
            margin-top: 15px;
            border-top: 2px solid #eee;
            font-size: 16px;
            font-weight: 700;
        }
        
        .status-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .status-form select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            margin-right: 10px;
        }
        
        .btn-update {
            background: #0046ad;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .alert {
            padding: 12px 20px;
            background: #d4edda;
            color: #155724;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detail Pesanan</h1>
        <!-- Fixed back URL to use correct file names -->
        <a href="<?= $role === 'seller' ? 'profile_seller.php' : 'profile_buyer.php' ?>" class="btn-back">Kembali</a>
    </div>
    
    <div class="container">
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert">Status pesanan berhasil diperbarui!</div>
        <?php endif; ?>
        
        <div class="card">
            <h3 class="card-title">Informasi Pesanan</h3>
            <div class="order-info">
                <div class="info-item">
                    <label>ID Pesanan</label>
                    <span>#<?= $order['order_id'] ?></span>
                </div>
                <div class="info-item">
                    <label>Tanggal</label>
                    <span><?= date('d M Y, H:i', strtotime($order['order_date'])) ?></span>
                </div>
                <div class="info-item">
                    <label>Buyer</label>
                    <span><?= htmlspecialchars($order['buyer_name']) ?></span>
                </div>
                <div class="info-item">
                    <label>Seller</label>
                    <span><?= htmlspecialchars($order['seller_name']) ?></span>
                </div>
                <div class="info-item">
                    <label>Status</label>
                    <span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                </div>
                <div class="info-item">
                    <label>Telepon Buyer</label>
                    <span><?= htmlspecialchars($order['buyer_phone'] ?? '-') ?></span>
                </div>
            </div>
            
            <?php if ($role === 'seller' && $order['seller_id'] == $user_id && !in_array($order['status'], ['completed', 'cancelled'])): ?>
                <form method="POST" class="status-form">
                    <label><strong>Update Status:</strong></label><br><br>
                    <select name="status">
                        <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Dikonfirmasi</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Diproses</option>
                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Dikirim</option>
                        <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Selesai</option>
                    </select>
                    <button type="submit" class="btn-update">Update Status</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3 class="card-title">Item Pesanan</h3>
            <div class="product-section">
                <img src="<?= $order['image_url'] ? 'uploads/' . htmlspecialchars($order['image_url']) : 'assets/story1.png' ?>" 
                     alt="<?= htmlspecialchars($order['product_name']) ?>" class="product-img">
                <div class="product-info">
                    <h4><?= htmlspecialchars($order['product_name']) ?></h4>
                    <p><?= $order['quantity'] ?> x <?= formatRupiah($order['unit_price']) ?></p>
                </div>
                <div class="product-price"><?= formatRupiah($order['total_price']) ?></div>
            </div>
            
            <div class="total-row">
                <span>Total</span>
                <span><?= formatRupiah($order['total_price']) ?></span>
            </div>
        </div>
        
        <?php if ($order['notes']): ?>
            <div class="card">
                <h3 class="card-title">Catatan</h3>
                <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
