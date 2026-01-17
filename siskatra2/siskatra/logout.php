<?php
/**
 * SISKATRA - Logout
 * Menghapus session dan redirect ke login
 */
session_start();

// Hapus semua data session
$_SESSION = array();

// Hapus session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect ke halaman login dengan status logout
header("Location: login.php?status=logout");
exit;
?>
