<?php
/**
 * SISKATRA - Buat Pesanan Baru
 * Updated to use mysqli instead of PDO to match existing codebase
 */
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'buyer') {
    header('Location: login.php');
    exit();
}

include 'koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$buyer_id = $_SESSION['user_id'];
$product_id = (int)($_POST['product_id'] ?? 0);
$quantity = (int)($_POST['quantity'] ?? 1);
$notes = trim($_POST['notes'] ?? '');

if ($quantity < 1) $quantity = 1;

// Ambil data produk dengan mysqli
$stmt = $conn->prepare("
    SELECT p.*, p.seller_id 
    FROM products p 
    WHERE p.product_id = ? AND p.stock >= ?
");
$stmt->bind_param("ii", $product_id, $quantity);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: dashboard.php?error=product_unavailable');
    exit();
}

// Hitung total
$total_price = $product['price'] * $quantity;
$seller_id = $product['seller_id'];

// Mulai transaksi
$conn->begin_transaction();

try {
    // Insert order
    $order_stmt = $conn->prepare("
        INSERT INTO orders (product_id, buyer_id, seller_id, quantity, total_price, notes, status, order_date)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $order_stmt->bind_param("iiiids", $product_id, $buyer_id, $seller_id, $quantity, $total_price, $notes);
    
    if (!$order_stmt->execute()) {
        throw new Exception("Gagal membuat pesanan");
    }
    
    $order_id = $conn->insert_id;
    
    // Kurangi stok
    $stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");
    $stock_stmt->bind_param("ii", $quantity, $product_id);
    
    if (!$stock_stmt->execute()) {
        throw new Exception("Gagal update stok");
    }
    
    $conn->commit();
    
    header("Location: view_order_detail.php?id=$order_id&success=1");
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header('Location: dashboard.php?error=order_failed');
    exit();
}
?>
