<?php
/**
 * SISKATRA - Halaman Laporan dengan Export Data
 * Updated to use mysqli instead of PDO
 */
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Hanya seller yang bisa akses laporan mereka sendiri
if ($_SESSION['role'] !== 'seller') {
    header('Location: dashboard.php');
    exit();
}

include 'koneksi.php';

$user_id = $_SESSION['user_id'];

// Filter tanggal
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-d');

// Statistik Ringkasan
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COALESCE(SUM(total_price), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_price ELSE 0 END), 0) as completed_revenue,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    WHERE p.seller_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
");
$summary_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Data pesanan
$orders_stmt = $conn->prepare("
    SELECT 
        o.*,
        p.product_name,
        buyer.username as buyer_name,
        buyer.phone as buyer_phone
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    JOIN users buyer ON o.buyer_id = buyer.id
    WHERE p.seller_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
    ORDER BY o.order_date DESC
");
$orders_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$orders_stmt->execute();
$orders = $orders_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Produk terlaris
$top_stmt = $conn->prepare("
    SELECT 
        p.product_name,
        SUM(o.quantity) as total_sold,
        SUM(o.total_price) as total_revenue
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    WHERE o.status = 'completed'
    AND p.seller_id = ?
    AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$top_stmt->execute();
$top_products = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pendapatan per hari (untuk grafik sederhana)
$daily_stmt = $conn->prepare("
    SELECT 
        DATE(o.order_date) as tanggal,
        COUNT(*) as jumlah_order,
        SUM(o.total_price) as pendapatan
    FROM orders o
    JOIN products p ON o.product_id = p.product_id
    WHERE p.seller_id = ?
    AND o.status = 'completed'
    AND DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY DATE(o.order_date)
    ORDER BY tanggal ASC
");
$daily_stmt->bind_param("iss", $user_id, $start_date, $end_date);
$daily_stmt->execute();
$daily_data = $daily_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=laporan_siskatra_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
    
    // Header
    fputcsv($output, ['ID Pesanan', 'Produk', 'Tanggal', 'Buyer', 'Qty', 'Total', 'Status']);
    
    // Data
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['order_id'],
            $order['product_name'],
            date('d/m/Y H:i', strtotime($order['order_date'])),
            $order['buyer_name'],
            $order['quantity'],
            $order['total_price'],
            ucfirst($order['status'])
        ]);
    }
    
    fclose($output);
    exit();
}

function formatRupiah($number) {
    return 'Rp ' . number_format($number, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - SISKATRA</title>
    <!-- Fixed favicon to use .png extension -->
    <link rel="icon" type="image/png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Lilita+One&family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            width: 40px; height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            font-size: 18px;
        }
        
        .header h1 { 
            font-size: 22px; 
            font-family: 'Lilita One', cursive;
            color: #FFD43B;
        }
        
        .header-actions { display: flex; gap: 10px; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary { background: #FFD43B; color: #333; }
        .btn-secondary { background: rgba(255,255,255,0.2); color: white; }
        .btn-success { background: #28a745; color: white; }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }
        
        .filter-group input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            text-align: center;
        }
        
        .stat-card h4 {
            font-size: 26px;
            color: #0046ad;
            margin-bottom: 5px;
        }
        
        .stat-card p { font-size: 13px; color: #666; }
        .stat-card.success h4 { color: #28a745; }
        .stat-card.warning h4 { color: #f0ad4e; }
        .stat-card.danger h4 { color: #dc3545; }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 25px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 { font-size: 16px; color: #333; }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
        }
        
        .table tr:hover { background: #f8f9fa; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-confirmed { background: #cce5ff; color: #004085; }
        .badge-processing { background: #d4edda; color: #155724; }
        .badge-shipped { background: #d1ecf1; color: #0c5460; }
        .badge-completed { background: #28a745; color: white; }
        .badge-cancelled { background: #f8d7da; color: #721c24; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .top-products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            padding: 20px;
        }
        
        .top-product-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            position: relative;
        }
        
        .top-product-item .rank {
            position: absolute;
            top: -8px;
            left: -8px;
            width: 28px; height: 28px;
            background: #0046ad;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .top-product-item h4 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #333;
            padding-left: 15px;
        }
        
        .top-product-item p { font-size: 12px; color: #666; }
        .top-product-item .revenue {
            font-size: 14px;
            font-weight: 700;
            color: #0046ad;
            margin-top: 5px;
        }
        
        .chart-container {
            padding: 20px;
        }
        
        .bar-chart {
            display: flex;
            align-items: flex-end;
            gap: 8px;
            height: 150px;
            padding: 10px 0;
        }
        
        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .bar {
            width: 100%;
            max-width: 40px;
            background: #0046ad;
            border-radius: 4px 4px 0 0;
            min-height: 5px;
        }
        
        .bar-label {
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        
        @media (max-width: 900px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filter-form { flex-direction: column; align-items: stretch; }
            .table { font-size: 12px; }
            .table th, .table td { padding: 10px; }
            .header { flex-direction: column; gap: 15px; }
        }
        
        @media print {
            .header, .filter-card, .btn { display: none !important; }
            .card { box-shadow: none; border: 1px solid #ddd; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <!-- Fixed link to profile_seller.php (correct spelling) -->
            <a href="profile_seller.php" class="back-btn">←</a>
            <h1>Laporan Penjualan</h1>
        </div>
        <div class="header-actions">
            <a href="?start=<?= $start_date ?>&end=<?= $end_date ?>&export=csv" class="btn btn-success">
                <span>📊</span> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <span>🖨️</span> Cetak
            </button>
        </div>
    </div>
    
    <div class="container">
        <div class="filter-card">
            <form class="filter-form" method="GET">
                <div class="filter-group">
                    <label>Tanggal Mulai</label>
                    <input type="date" name="start" value="<?= $start_date ?>">
                </div>
                <div class="filter-group">
                    <label>Tanggal Akhir</label>
                    <input type="date" name="end" value="<?= $end_date ?>">
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h4><?= $summary['total_orders'] ?? 0 ?></h4>
                <p>Total Pesanan</p>
            </div>
            <div class="stat-card success">
                <h4><?= $summary['completed_orders'] ?? 0 ?></h4>
                <p>Pesanan Selesai</p>
            </div>
            <div class="stat-card warning">
                <h4><?= $summary['pending_orders'] ?? 0 ?></h4>
                <p>Pesanan Pending</p>
            </div>
            <div class="stat-card">
                <h4><?= formatRupiah($summary['completed_revenue'] ?? 0) ?></h4>
                <p>Total Pendapatan</p>
            </div>
        </div>

        <?php if (!empty($daily_data)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Grafik Pendapatan Harian</h3>
            </div>
            <div class="chart-container">
                <?php
                $max_revenue = max(array_column($daily_data, 'pendapatan'));
                ?>
                <div class="bar-chart">
                    <?php foreach ($daily_data as $day): ?>
                        <?php $height = $max_revenue > 0 ? ($day['pendapatan'] / $max_revenue) * 120 : 5; ?>
                        <div class="bar-item">
                            <div class="bar" style="height: <?= $height ?>px;" title="<?= formatRupiah($day['pendapatan']) ?>"></div>
                            <span class="bar-label"><?= date('d/m', strtotime($day['tanggal'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($top_products)): ?>
        <div class="card">
            <div class="card-header">
                <h3>Produk Terlaris</h3>
            </div>
            <div class="top-products-grid">
                <?php foreach ($top_products as $i => $prod): ?>
                    <div class="top-product-item">
                        <span class="rank"><?= $i + 1 ?></span>
                        <h4><?= htmlspecialchars($prod['product_name']) ?></h4>
                        <p>Terjual: <?= $prod['total_sold'] ?> unit</p>
                        <p class="revenue"><?= formatRupiah($prod['total_revenue']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3>Daftar Pesanan</h3>
                <span style="font-size:13px;color:#666;"><?= count($orders) ?> pesanan</span>
            </div>
            
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <p style="font-size: 40px; margin-bottom: 10px;">📋</p>
                    <p>Tidak ada pesanan pada periode ini.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produk</th>
                                <th>Tanggal</th>
                                <th>Buyer</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?= $order['order_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($order['product_name']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></td>
                                    <td><?= htmlspecialchars($order['buyer_name']) ?></td>
                                    <td><?= $order['quantity'] ?></td>
                                    <td><strong><?= formatRupiah($order['total_price']) ?></strong></td>
                                    <td><span class="badge badge-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span></td>
                                    <td>
                                        <a href="view_order_detail.php?id=<?= $order['order_id'] ?>" style="color:#0046ad;font-size:12px;">Detail</a>
                                        <?php if ($order['buyer_phone']): ?>
                                            | <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $order['buyer_phone']) ?>" target="_blank" style="color:#25D366;font-size:12px;">WA</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
