<?php
/**
 * SISKATRA - Proses Login (Secure Version)
 * Menggunakan prepared statement untuk mencegah SQL Injection
 */
session_start();
include "koneksi.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

// Validasi input
if (empty($username) || empty($password) || empty($role)) {
    header("Location: login.php?status=empty");
    exit;
}

// Validasi role
$allowed_roles = ['buyer', 'seller'];
if (!in_array($role, $allowed_roles)) {
    header("Location: login.php?status=invalid_role");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
$stmt->bind_param("ss", $username, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    
    // Cek password (gunakan password_verify jika menggunakan hash)
    // Untuk backward compatibility dengan password plain text:
    if ($password === $row['password']) {
        // Regenerate session ID untuk keamanan
        session_regenerate_id(true);
        
        // Set session
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_type'] = $row['role'];
        
        // Generate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        header("Location: dashboard.php?status=success");
    } else {
        header("Location: login.php?status=wrongpass");
    }
} else {
    header("Location: login.php?status=nouser");
}
exit;
?>
