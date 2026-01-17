<?php
/**
 * SISKATRA - Hapus Kategori
 * Updated to allow both admin and seller, matching index.php permissions
 */
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'seller'])) {
    header('Location: ../dashboard.php');
    exit();
}

include '../koneksi.php';

$category_id = (int)($_GET['id'] ?? 0);
$token = $_GET['token'] ?? '';

// Validasi token CSRF
if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
    header('Location: index.php?error=invalid_token');
    exit();
}

if ($category_id <= 0) {
    header('Location: index.php?error=invalid_id');
    exit();
}

// Cek apakah kategori digunakan oleh produk
$check_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE category_id = ?");
$check_stmt->bind_param("i", $category_id);
$check_stmt->execute();
$result = $check_stmt->get_result()->fetch_assoc();

if ($result['total'] > 0) {
    header('Location: index.php?error=category_in_use');
    exit();
}

// Hapus kategori
$delete_stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
$delete_stmt->bind_param("i", $category_id);

if ($delete_stmt->execute()) {
    header('Location: index.php?success=deleted');
} else {
    header('Location: index.php?error=delete_failed');
}
exit();
?>
