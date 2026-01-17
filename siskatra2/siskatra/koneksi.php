<?php
/**
 * SISKATRA - Koneksi Database
 * File ini mengatur koneksi ke database MySQL
 */

$host = "localhost";
$user = "root";
$pass = "";
$db   = "siskatra_db";

// Koneksi MySQLi (untuk file yang sudah ada)
$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Koneksi PDO (untuk fitur baru yang lebih aman)
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Koneksi PDO gagal: " . $e->getMessage());
}

// Helper function untuk generate order code
function generateOrderCode() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// Helper function untuk format rupiah
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
