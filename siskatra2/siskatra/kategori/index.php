<?php
/**
 * SISKATRA - Manajemen Kategori
 * Halaman untuk CRUD kategori (Admin/Seller)
 */
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

// Hanya admin dan seller yang bisa akses
if (!in_array($_SESSION['role'], ['admin', 'seller'])) {
    header('Location: ../dashboard.php');
    exit();
}

include '../koneksi.php';

// Handle Delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $cat_id = (int)$_GET['delete'];
    
    // Cek apakah kategori digunakan produk
    $check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
    $check->execute([$cat_id]);
    $count = $check->fetchColumn();
    
    if ($count > 0) {
        $error = "Kategori tidak bisa dihapus karena masih digunakan oleh $count produk.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
        $stmt->execute([$cat_id]);
        $success = "Kategori berhasil dihapus!";
    }
}

// Ambil semua kategori
$categories = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM products WHERE category_id = c.category_id) as product_count
    FROM categories c 
    ORDER BY c.category_name ASC
")->fetchAll();

$success = $_GET['success'] ?? $success ?? null;
$error = $_GET['error'] ?? $error ?? null;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kategori - SISKATRA</title>
    <link rel="icon" type="image/png" href="../assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f7fa;
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
        
        .header h1 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #FFD43B;
            color: #333;
        }
        
        .btn-primary:hover {
            background: #f7ca1e;
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-edit {
            background: #0046ad;
            color: white;
        }
        
        .btn-edit:hover {
            background: #003a8c;
        }
        
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 18px;
            color: #333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-count {
            background: #e3f2fd;
            color: #1565c0;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .actions .btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state p {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manajemen Kategori</h1>
        <div class="header-actions">
            <a href="tambah.php" class="btn btn-primary">+ Tambah Kategori</a>
            <a href="../dashboard.php" class="btn btn-secondary">Kembali</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Daftar Kategori</h2>
                <span class="badge badge-count"><?= count($categories) ?> Kategori</span>
            </div>
            
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <p>Belum ada kategori. Tambahkan kategori pertama!</p>
                    <a href="tambah.php" class="btn btn-primary">+ Tambah Kategori</a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Icon</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th>Jumlah Produk</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $i => $cat): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <?php if ($cat['category_icon']): ?>
                                        <img src="../assets/<?= htmlspecialchars($cat['category_icon']) ?>" 
                                             alt="<?= htmlspecialchars($cat['category_name']) ?>" 
                                             class="category-icon">
                                    <?php else: ?>
                                        <span style="font-size:24px;">📦</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($cat['category_name']) ?></strong></td>
                                <td><?= htmlspecialchars($cat['category_description'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-count"><?= $cat['product_count'] ?> produk</span>
                                </td>
                                <td>
                                    <?php if ($cat['is_active']): ?>
                                        <span class="badge badge-active">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="edit.php?id=<?= $cat['category_id'] ?>" class="btn btn-edit">Edit</a>
                                        <?php if ($cat['product_count'] == 0): ?>
                                            <a href="?delete=<?= $cat['category_id'] ?>" 
                                               class="btn btn-danger"
                                               onclick="return confirm('Yakin hapus kategori ini?')">Hapus</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
