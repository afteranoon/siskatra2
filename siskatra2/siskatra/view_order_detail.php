<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$order_id = (int)($_GET['id'] ?? 0);

// Ambil detail pesanan
$stmt = $conn->prepare("
    SELECT o.*, 
           p.product_name, p.price AS unit_price, p.description AS product_desc,
           seller.username AS seller_name, seller.phone AS seller_phone,
           buyer.username AS buyer_name, buyer.phone AS buyer_phone,
           pi.image_url
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    JOIN users seller ON o.seller_id = seller.id
    JOIN users buyer ON o.buyer_id = buyer.id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
    WHERE o.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: dashboard.php');
    exit();
}

// Cek akses: hanya buyer atau seller yang bersangkutan
if ($role === 'seller' && $order['seller_id'] != $user_id) {
    header('Location: dashboard.php');
    exit();
}
if ($role === 'buyer' && $order['buyer_id'] != $user_id) {
    header('Location: dashboard.php');
    exit();
}

// Proses update status (hanya untuk seller)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'seller') {
    $new_status = $_POST['status'] ?? '';
    $allowed_status = ['pending', 'confirmed', 'processing', 'shipped', 'completed', 'cancelled'];
    
    if (in_array($new_status, $allowed_status)) {
        $update_stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND seller_id = ?");
        $update_stmt->bind_param("sii", $new_status, $order_id, $user_id);
        $update_stmt->execute();
        header("Location: view_order_detail.php?id=$order_id&updated=1");
        exit();
    }
}

$back_url = $role === 'seller' ? 'view_all_orders.php' : 'profile_buyer.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?= $order_id ?> - SISKATRA</title>
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
        
        .main-content { max-width: 800px; margin: 0 auto; padding: 30px 20px; }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
        }
        
        .order-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        .order-header {
            background: #0046ad;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-id { font-size: 20px; font-weight: bold; }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-processing { background: #d4edda; color: #155724; }
        .status-shipped { background: #17a2b8; color: white; }
        .status-completed { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }
        
        .order-body { padding: 25px; }
        
        .product-section {
            display: flex;
            gap: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
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
        .product-details h3 { font-size: 20px; margin-bottom: 10px; }
        .product-details p { color: #666; margin-bottom: 5px; }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .info-box { padding: 15px; background: #f9f9f9; border-radius: 10px; }
        .info-box h4 { color: #0046ad; margin-bottom: 10px; font-size: 14px; text-transform: uppercase; }
        .info-box p { margin-bottom: 5px; }
        
        .total-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-label { font-size: 18px; color: #666; }
        .total-amount { font-size: 28px; font-weight: bold; color: #0046ad; }
        
        .action-section {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .action-section h4 { margin-bottom: 15px; }
        .status-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .status-form select {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: #0046ad; color: white; }
        .btn-wa { background: #25D366; color: white; display: inline-flex; align-items: center; gap: 8px; }
        
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .product-section { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="<?= $back_url ?>" class="back-btn">←</a>
        <h1 class="header-title">Detail Pesanan</h1>
    </div>

    <div class="main-content">
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert">Status pesanan berhasil diperbarui!</div>
        <?php endif; ?>

        <div class="order-card">
            <div class="order-header">
                <span class="order-id">Pesanan #<?= $order_id ?></span>
                <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
            </div>
            <div class="order-body">
                <div class="product-section">
                    <div class="product-image">
                        <?php if ($order['image_url'] && file_exists('uploads/' . $order['image_url'])): ?>
                            <img src="uploads/<?= htmlspecialchars($order['image_url']) ?>" alt="">
                        <?php else: ?>
                            📦
                        <?php endif; ?>
                    </div>
                    <div class="product-details">
                        <h3><?= htmlspecialchars($order['product_name']) ?></h3>
                        <p>Harga: Rp <?= number_format($order['unit_price'], 0, ',', '.') ?></p>
                        <p>Jumlah: <?= $order['quantity'] ?> unit</p>
                        <p>Tanggal: <?= date('d F Y, H:i', strtotime($order['order_date'])) ?></p>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-box">
                        <h4>Pembeli</h4>
                        <p><strong><?= htmlspecialchars($order['buyer_name']) ?></strong></p>
                        <?php if ($order['buyer_phone']): ?>
                            <p><?= htmlspecialchars($order['buyer_phone']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="info-box">
                        <h4>Penjual</h4>
                        <p><strong><?= htmlspecialchars($order['seller_name']) ?></strong></p>
                        <?php if ($order['seller_phone']): ?>
                            <p><?= htmlspecialchars($order['seller_phone']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($order['notes']): ?>
                <div class="info-box" style="margin-top: 20px;">
                    <h4>Catatan</h4>
                    <p><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
                </div>
                <?php endif; ?>

                <div class="total-section">
                    <span class="total-label">Total Pembayaran</span>
                    <span class="total-amount">Rp <?= number_format($order['total_price'], 0, ',', '.') ?></span>
                </div>

                <?php if ($role === 'seller'): ?>
                <div class="action-section">
                    <h4>Update Status Pesanan</h4>
                    <form method="POST" class="status-form">
                        <select name="status">
                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Dikonfirmasi</option>
                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Diproses</option>
                            <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Dikirim</option>
                            <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Selesai</option>
                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </form>
                    <?php if ($order['buyer_phone']): ?>
                        <a href="https://wa.me/<?= $order['buyer_phone'] ?>?text=Halo, pesanan Anda #<?= $order_id ?> sedang diproses." target="_blank" class="btn btn-wa" style="margin-top: 15px;">
                            <span>📱</span> Hubungi via WhatsApp
                        </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                    <?php if ($order['seller_phone']): ?>
                    <div class="action-section">
                        <a href="https://wa.me/<?= $order['seller_phone'] ?>?text=Halo, saya ingin bertanya tentang pesanan #<?= $order_id ?>" target="_blank" class="btn btn-wa">
                            <span>📱</span> Hubungi Penjual via WhatsApp
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
