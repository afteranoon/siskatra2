<?php
/**
 * SISKATRA - Proses Login (Secure Version)
 * Menggunakan prepared statement untuk mencegah SQL Injection
 * Support untuk role: admin, seller, buyer
 * Hidden admin login: username @admin dengan password admin123
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
$admin_mode = $_POST['admin_mode'] ?? '0';

if ($username === '@admin') {
    $username = 'admin'; // Gunakan username admin yang ada di database
    $role = 'admin';
}

// Validasi input
if (empty($username) || empty($password) || empty($role)) {
    header("Location: login.php?status=empty");
    exit;
}

$allowed_roles = ['buyer', 'seller', 'admin'];
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
    
    if (isset($row['is_active']) && $row['is_active'] == 0) {
        header("Location: login.php?status=inactive");
        exit;
    }
    
    $password_valid = false;
    
    // Cek dengan password_verify (untuk password yang di-hash)
    if (password_verify($password, $row['password'])) {
        $password_valid = true;
    }
    // Backward compatibility: cek plain text password (untuk data lama)
    elseif ($password === $row['password']) {
        $password_valid = true;
        
        // Update password lama ke hash untuk keamanan
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->bind_param("si", $hashed_password, $row['id']);
        $update_stmt->execute();
    }
    
    if ($password_valid) {
        // Regenerate session ID untuk keamanan
        session_regenerate_id(true);
        
        // Set session
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_type'] = $row['role'];
        $_SESSION['full_name'] = $row['full_name'] ?? $row['username'];
        $_SESSION['email'] = $row['email'] ?? '';
        
        // Generate CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $update_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update_login->bind_param("i", $row['id']);
        $update_login->execute();
        
        header("Location: dashboard.php?status=success");
    } else {
        header("Location: login.php?status=wrongpass");
    }
} else {
    header("Location: login.php?status=nouser");
}
exit;
?>
