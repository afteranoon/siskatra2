<?php
/**
 * SISKATRA - Dashboard dengan Statistik dan Filter Kategori
 */
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

// Helper functions untuk WhatsApp
function generateWhatsAppLink($product_name, $seller_phone, $order_id = null) {
    $phone = preg_replace('/\D/', '', $seller_phone);
    
    if (strlen($phone) <= 10) {
        $phone = '62' . ltrim($phone, '0');
    } elseif (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    if ($order_id) {
        $msg = "Halo, saya ingin mengonfirmasi pembelian produk \"$product_name\" dengan Order ID: $order_id. Berapa total pembayarannya dan bagaimana cara pembayarannya?";
    } else {
        $msg = "Halo, saya tertarik dengan produk \"$product_name\". Bisakah saya tahu harganya?";
    }
    
    $encoded_msg = urlencode($msg);
    return "https://wa.me/{$phone}?text={$encoded_msg}";
}

// Filter kategori
$category_filter = $_GET['category'] ?? '';
$search_query = $_GET['search'] ?? '';

// Query produk dengan filter
$stmt = $conn->prepare("
    SELECT 
        p.product_id,
        p.product_name AS name,
        p.price,
        p.stock,
        p.description,
        COALESCE(c.category_name, 'Kategori Tidak Diketahui') AS category,
        c.category_id,
        pi.image_url AS image,
        u.username AS seller_name,
        u.id AS seller_id,
        u.phone AS seller_phone
    FROM products p
    INNER JOIN users u ON p.seller_id = u.id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
    WHERE p.stock > 0
");

if (!empty($category_filter)) {
    $like_pattern = "%" . $category_filter . "%";
    $stmt = $conn->prepare("
        SELECT 
            p.product_id,
            p.product_name AS name,
            p.price,
            p.stock,
            p.description,
            COALESCE(c.category_name, 'Kategori Tidak Diketahui') AS category,
            c.category_id,
            pi.image_url AS image,
            u.username AS seller_name,
            u.id AS seller_id,
            u.phone AS seller_phone
        FROM products p
        INNER JOIN users u ON p.seller_id = u.id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
        WHERE p.stock > 0 AND LOWER(c.category_name) LIKE LOWER(?)
    ");
    $stmt->bind_param("s", $like_pattern);
}

if (!empty($search_query)) {
    $like_search = "%" . $search_query . "%";
    $stmt = $conn->prepare("
        SELECT 
            p.product_id,
            p.product_name AS name,
            p.price,
            p.stock,
            p.description,
            COALESCE(c.category_name, 'Kategori Tidak Diketahui') AS category,
            c.category_id,
            pi.image_url AS image,
            u.username AS seller_name,
            u.id AS seller_id,
            u.phone AS seller_phone
        FROM products p
        INNER JOIN users u ON p.seller_id = u.id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_main = 1
        WHERE p.stock > 0 AND (p.product_name LIKE ? OR p.description LIKE ?)
    ");
    $stmt->bind_param("ss", $like_search, $like_search);
}

$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Ambil semua kategori untuk filter
$categories_result = $conn->query("SELECT * FROM categories ORDER BY category_name");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Statistik untuk dashboard (seller/admin)
$stats = null;
if ($_SESSION['role'] === 'seller' || $_SESSION['role'] === 'admin') {
    $seller_id = $_SESSION['user_id'];
    
    $stats_stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM products WHERE seller_id = ?) as total_products,
            (SELECT COUNT(*) FROM orders WHERE seller_id = ?) as total_orders,
            (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE seller_id = ? AND status = 'completed') as total_revenue,
            (SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'pending') as pending_orders
    ");
    $stats_stmt->bind_param("iiii", $seller_id, $seller_id, $seller_id, $seller_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->get_result()->fetch_assoc();
}

$dashboard_stories = [];
$table_check = $conn->query("SHOW TABLES LIKE 'stories'");
if ($table_check && $table_check->num_rows > 0) {
    // Check if seller_id column exists
    $column_check = $conn->query("SHOW COLUMNS FROM stories LIKE 'seller_id'");
    if ($column_check && $column_check->num_rows > 0) {
        $stories_query = $conn->query("
            SELECT 
                s.*,
                u.username AS seller_name,
                (SELECT image_url FROM stories WHERE seller_id = s.seller_id AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1) as latest_image
            FROM stories s
            JOIN users u ON s.seller_id = u.id
            WHERE s.expires_at > NOW()
            GROUP BY s.seller_id
            ORDER BY MAX(s.created_at) DESC
            LIMIT 10
        ");
        $dashboard_stories = $stories_query ? $stories_query->fetch_all(MYSQLI_ASSOC) : [];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SISKATRA</title>
    <link rel="icon" type="image/png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: #ffffff;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header img.logo { height: 40px; }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .header-right a {
            text-decoration: none;
        }
        
        .profile-btn {
            width: 38px;
            height: 38px;
            background: #FFD43B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .profile-btn img {
            width: 24px;
            height: 24px;
        }
        
        .logout-btn {
            background: #0046ad;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .banner {
            background: linear-gradient(135deg, #0046ad 0%, #284e7f 100%);
            border-radius: 20px;
            margin: 25px auto;
            width: 92%;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Stats Cards untuk Seller */
        .stats-section {
            width: 92%;
            margin: 0 auto 25px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .stat-card h4 {
            font-size: 24px;
            color: #0046ad;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            font-size: 12px;
            color: #666;
        }
        
        /* Search */
        .search-section {
            width: 92%;
            margin: 0 auto 25px;
        }
        
        .search-container {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 10px 20px;
        }
        
        .search-container input {
            border: none;
            outline: none;
            flex: 1;
            font-size: 15px;
            font-family: inherit;
            padding: 8px;
        }
        
        .search-container button {
            background: #FFD43B;
            border: none;
            padding: 10px 25px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        
        /* Stories Section */
        .stories-section {
            width: 92%;
            margin: 0 auto 25px;
        }
        
        .stories-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stories-header h3 {
            font-size: 16px;
            color: #333;
        }
        
        .stories-header a {
            font-size: 13px;
            color: #0046ad;
            text-decoration: none;
        }
        
        .stories-carousel {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            scrollbar-width: thin;
        }
        
        .story-avatar {
            flex-shrink: 0;
            text-align: center;
            cursor: pointer;
        }
        
        .story-avatar .avatar-ring {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            margin-bottom: 6px;
        }
        
        .story-avatar .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid white;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .story-avatar .avatar-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .story-avatar .seller-name {
            font-size: 11px;
            color: #333;
            max-width: 70px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .story-add {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 2px dashed #0046ad;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #0046ad;
            margin-bottom: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .story-add:hover {
            background: #e8f0fe;
        }
        
        /* Content Layout */
        .content-wrapper {
            display: flex;
            width: 92%;
            margin: 0 auto;
            gap: 25px;
        }
        
        .main-content { flex: 3; }
        
        .sidebar { flex: 1; min-width: 250px; }
        
        /* Kategori Sidebar */
        .kategori-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: sticky;
            top: 90px;
        }
        
        .kategori-card h3 {
            font-size: 16px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .kategori-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .kategori-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }
        
        .kategori-item:hover,
        .kategori-item.active {
            background: #FFD43B;
        }
        
        .kategori-item img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }
        
        /* Welcome */
        .welcome-section {
            margin-bottom: 20px;
        }
        
        .welcome-section h2 {
            color: #284e7f;
            font-size: 22px;
        }
        
        /* Produk */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h3 {
            font-size: 18px;
            color: #333;
        }
        
        .add-product-btn {
            background: #FFD43B;
            color: #333;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        
        .produk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .produk-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
            transition: transform 0.2s;
        }
        
        .produk-card:hover {
            transform: translateY(-5px);
        }
        
        .produk-img {
            width: 100%;
            height: 150px;
            background: #f0f0f0;
            background-size: cover;
            background-position: center;
        }
        
        .produk-info {
            padding: 15px;
        }
        
        .produk-info h4 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #333;
        }
        
        .produk-info .category {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .produk-info .price {
            font-size: 15px;
            font-weight: 700;
            color: #0046ad;
            margin-bottom: 12px;
        }
        
        .produk-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn-produk {
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-family: inherit;
        }
        
        .btn-view {
            background: #FFD43B;
            color: #333;
        }
        
        .btn-edit {
            background: #0046ad;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 15px;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .quick-actions h3 {
            font-size: 14px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .quick-links a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-size: 13px;
            transition: background 0.2s;
        }
        
        .quick-links a:hover {
            background: #e9ecef;
        }
        
        /* Popup */
        .popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .popup-content {
            background: white;
            border-radius: 20px;
            width: 380px;
            max-width: 90%;
            padding: 25px;
            position: relative;
        }
        
        .popup-close {
            position: absolute;
            top: 15px;
            left: 20px;
            font-size: 20px;
            cursor: pointer;
            color: #666;
        }
        
        .popup-content img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 15px;
        }
        
        .popup-content h4 {
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .popup-content .popup-cat {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .popup-content .popup-price {
            font-size: 18px;
            font-weight: 700;
            color: #0046ad;
            margin-bottom: 15px;
        }
        
        .popup-content textarea {
            width: 100%;
            height: 80px;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px;
            font-family: inherit;
            resize: none;
            margin-bottom: 15px;
        }
        
        .btn-wa {
            display: block;
            width: 100%;
            background: #25D366;
            color: white;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .btn-wa:hover {
            background: #1dbf4b;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #0046ad;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0046ad;
        }
        
        .btn-pesan {
            width: 100%;
            padding: 12px;
            background: #0046ad;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-pesan:hover {
            background: #003080;
        }
        
        .notif {
            position: fixed;
            top: 80px;
            right: 20px;
            padding: 15px 25px;
            background: #4caf50;
            color: white;
            border-radius: 10px;
            font-size: 14px;
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @media (max-width: 900px) {
            .content-wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                order: -1;
            }
            
            .kategori-card {
                position: static;
            }
            
            .kategori-list {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <img src="assets/logo_siskatra_text.png" alt="SISKATRA" class="logo">
        <div class="header-right">
            <?php
            $profile_url = $_SESSION['role'] === 'seller' ? 'profile_seller.php' : 
                          ($_SESSION['role'] === 'admin' ? 'admin_panel.php' : 'profile_buyer.php');
            ?>
            <a href="<?php echo $profile_url; ?>">
                <div class="profile-btn">
                    <img src="assets/profile.png" alt="Profile">
                </div>
            </a>
            <a href="logout.php" class="logout-btn">
                <img src="assets/logout_icon.png" alt="" style="width:16px;height:16px;">
                Keluar
            </a>
        </div>
    </div>
    
    <!-- Notifikasi -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'product_deleted'): ?>
        <div class="notif">Produk berhasil dihapus!</div>
        <script>setTimeout(() => document.querySelector('.notif').remove(), 3000);</script>
    <?php endif; ?>
    
    <!-- Stories Section -->
    <?php if (!empty($dashboard_stories) || $_SESSION['role'] === 'seller'): ?>
    <div class="stories-section">
        <div class="stories-header">
            <h3>📷 Stories Hari Ini</h3>
            <a href="stories.php">Lihat Semua →</a>
        </div>
        <div class="stories-carousel">
            <?php if ($_SESSION['role'] === 'seller'): ?>
                <div class="story-avatar" onclick="location.href='stories.php'">
                    <div class="story-add">+</div>
                    <div class="seller-name">Tambah</div>
                </div>
            <?php endif; ?>
            
            <?php foreach ($dashboard_stories as $story): ?>
                <div class="story-avatar" onclick="location.href='stories.php'">
                    <div class="avatar-ring">
                        <div class="avatar-img">
                            <img src="uploads/stories/<?= htmlspecialchars($story['latest_image']) ?>" alt="">
                        </div>
                    </div>
                    <div class="seller-name"><?= htmlspecialchars($story['seller_name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Banner -->
    <div class="banner">
        <img src="assets/Banner_Iklan.png" alt="Banner">
    </div>
    
    <!-- Stats untuk Seller -->
    <?php if ($stats): ?>
        <div class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <h4><?= $stats['total_products'] ?></h4>
                    <p>Total Produk</p>
                </div>
                <div class="stat-card">
                    <h4><?= $stats['total_orders'] ?></h4>
                    <p>Total Pesanan</p>
                </div>
                <div class="stat-card">
                    <h4><?= $stats['pending_orders'] ?></h4>
                    <p>Pesanan Pending</p>
                </div>
                <div class="stat-card">
                    <h4><?= number_format($stats['total_revenue'], 0, ',', '.') ?></h4>
                    <p>Total Pendapatan</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Search -->
    <div class="search-section">
        <form class="search-container" method="GET">
            <?php if ($category_filter): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Cari produk..." 
                   value="<?= htmlspecialchars($search_query) ?>">
            <button type="submit">CARI</button>
        </form>
    </div>
    
    <!-- Content -->
    <div class="content-wrapper">
        <div class="main-content">
            <div class="welcome-section">
                <h2>Selamat datang, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
            </div>
            
            <div class="section-header">
                <h3>
                    <?php if ($category_filter): ?>
                        Produk: <?= htmlspecialchars(ucfirst($category_filter)) ?>
                        <a href="dashboard.php" style="font-size:12px;color:#666;margin-left:10px;">(Reset)</a>
                    <?php else: ?>
                        Produk Terbaru
                    <?php endif; ?>
                </h3>
                
                <?php if ($_SESSION['role'] === 'seller'): ?>
                    <a href="add_product.php" class="add-product-btn">+ Tambah Produk</a>
                <?php endif; ?>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>Tidak ada produk ditemukan.</p>
                    <?php if ($_SESSION['role'] === 'seller'): ?>
                        <a href="add_product.php" class="add-product-btn">+ Tambah Produk Pertama</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="produk-grid">
                    <?php foreach ($products as $p): ?>
                        <div class="produk-card">
                            <div class="produk-img" style="background-image: url('<?= $p['image'] ? 'uploads/' . htmlspecialchars($p['image']) : 'assets/story1.png' ?>')"></div>
                            <div class="produk-info">
                                <h4><?= htmlspecialchars($p['name']) ?></h4>
                                <p class="category"><?= htmlspecialchars($p['category']) ?></p>
                                <p class="price"><?= number_format($p['price'], 0, ',', '.') ?></p>
                                
                                <?php $is_owner = ($_SESSION['role'] === 'seller' && $p['seller_name'] === $_SESSION['username']); ?>
                                
                                <div class="produk-actions">
                                    <?php if ($is_owner): ?>
                                        <a href="edit_product.php?id=<?= $p['product_id'] ?>" class="btn-produk btn-edit">Edit</a>
                                        <button class="btn-produk btn-delete" 
                                                onclick="confirmDelete(<?= $p['product_id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')">
                                            Hapus
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-produk btn-view" 
                                                onclick='showPopup(<?= json_encode([
                                                    "id" => $p["product_id"],
                                                    "name" => $p["name"],
                                                    "category" => $p["category"],
                                                    "price" => $p["price"],
                                                    "description" => $p["description"] ?? "",
                                                    "image" => $p["image"] ? "uploads/" . $p["image"] : "assets/story1.png",
                                                    "seller" => $p["seller_name"],
                                                    "phone" => $p["seller_phone"] ?? ""
                                                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Lihat Detail
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sidebar">
            <div class="kategori-card">
                <h3>Kategori</h3>
                <div class="kategori-list">
                    <a href="?category=" class="kategori-item <?php echo empty($category_filter) ? 'active' : ''; ?>">
                        <img src="assets/logo_siskatrabaru.png" alt="">
                        Semua
                    </a>
                    <a href="?category=makanan" class="kategori-item <?php echo $category_filter === 'makanan' ? 'active' : ''; ?>">
                        <img src="assets/Makanan.png" alt="">
                        Makanan
                    </a>
                    <a href="?category=minuman" class="kategori-item <?php echo $category_filter === 'minuman' ? 'active' : ''; ?>">
                        <img src="assets/Minuman.png" alt="">
                        Minuman
                    </a>
                    <a href="?category=barang" class="kategori-item <?php echo $category_filter === 'barang' ? 'active' : ''; ?>">
                        <img src="assets/Barang.png" alt="">
                        Barang
                    </a>
                    <a href="?category=jasa" class="kategori-item <?php echo $category_filter === 'jasa' ? 'active' : ''; ?>">
                        <img src="assets/Jasa.png" alt="">
                        Jasa
                    </a>
                </div>
            </div>
            
            <?php if ($_SESSION['role'] === 'seller' || $_SESSION['role'] === 'admin'): ?>
                <div class="quick-actions">
                    <h3>Menu Cepat</h3>
                    <div class="quick-links">
                        <a href="kategori/">Kelola Kategori</a>
                        <a href="laporan.php">Lihat Laporan</a>
                        <a href="add_product.php">Tambah Produk</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Popup Detail -->
    <div class="popup" id="popup">
        <div class="popup-content">
            <span class="popup-close" onclick="closePopup()">&#8592;</span>
            <img id="popupImage" src="/placeholder.svg" alt="">
            <h4 id="popupName"></h4>
            <p class="popup-cat" id="popupCategory"></p>
            <p class="popup-price" id="popupPrice"></p>
            <textarea id="popupDesc" readonly></textarea>
            
            <!-- Form pesanan dengan quantity dan notes -->
            <div id="orderForm" style="display: none;">
                <form id="pesan-form" method="POST" action="buat_pesanan.php">
                    <input type="hidden" id="product_id" name="product_id">
                    <div class="form-group">
                        <label>Jumlah</label>
                        <input type="number" name="quantity" value="1" min="1" max="999">
                    </div>
                    <div class="form-group">
                        <label>Catatan (Opsional)</label>
                        <textarea name="notes" placeholder="Tambahkan catatan untuk penjual..." rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn-pesan">Pesan Sekarang</button>
                </form>
            </div>
            
            <div id="waButton">
                <a id="popupWA" href="#" target="_blank" class="btn-wa">Chat WhatsApp Seller</a>
            </div>
        </div>
    </div>
    
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Buat Pesanan</h2>
            <form method="POST" action="buat_pesanan.php" class="modal-form">
                <input type="hidden" id="product_id" name="product_id">
                <label>Produk: <span id="product_name"></span></label>
                <label for="quantity">Jumlah:</label>
                <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                <label for="notes">Catatan (opsional):</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Tulis catatan tambahan untuk penjual..."></textarea>
                <button type="submit">Lanjut ke WhatsApp</button>
            </form>
        </div>
    </div>
    
    <script>
        function showPopup(data) {
            document.getElementById('popupImage').src = data.image;
            document.getElementById('popupName').textContent = data.name;
            document.getElementById('popupCategory').textContent = data.category;
            document.getElementById('popupPrice').textContent = 'Rp ' + Number(data.price).toLocaleString('id-ID');
            document.getElementById('popupDesc').value = data.description || 'Tidak ada deskripsi';
            
            const role = '<?= $_SESSION['role'] ?>';
            const orderFormDiv = document.getElementById('orderForm');
            const waButtonDiv = document.getElementById('waButton');
            
            if (role === 'buyer') {
                document.getElementById('product_id').value = data.id;
                orderFormDiv.style.display = 'block';
                waButtonDiv.style.display = 'none';
            } else {
                orderFormDiv.style.display = 'none';
                waButtonDiv.style.display = 'block';
                const phone = data.phone ? data.phone.replace(/\D/g, '') : '';
                const msg = encodeURIComponent(`Halo, saya tertarik dengan produk "${data.name}" di SISKATRA.`);
                document.getElementById('popupWA').href = phone ? `https://wa.me/${phone}?text=${msg}` : '#';
            }
            
            document.getElementById('popup').style.display = 'flex';
        }
        
        function closePopup() {
            document.getElementById('popup').style.display = 'none';
        }
        
        function confirmDelete(id, name) {
            if (confirm(`Yakin ingin menghapus produk "${name}"?`)) {
                const token = CryptoJS.SHA256(document.cookie.match(/PHPSESSID=([^;]+)/)?.[1] + id).toString();
                window.location.href = `hapus_product.php?id=${id}&token=${token}`;
            }
        }
        
        function openModal(productId, productName, sellerId, sellerPhone) {
            document.getElementById('product_id').value = productId;
            document.getElementById('product_name').textContent = productName;
            document.getElementById('orderModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        function filterCategory(category) {
            if (category) {
                window.location.href = '?category=' + category;
            } else {
                window.location.href = '?';
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Close popup on outside click
        document.getElementById('popup').addEventListener('click', function(e) {
            if (e.target === this) closePopup();
        });
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
</body>
</html>
