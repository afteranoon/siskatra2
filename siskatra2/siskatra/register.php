<?php
/**
 * SISKATRA - Halaman Register
 * Registrasi untuk Seller dan Buyer
 */
session_start();
include "koneksi.php";

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    
    // Validasi input
    if (empty($username) || empty($password) || empty($role)) {
        $status = 'empty';
    } elseif (!in_array($role, ['buyer', 'seller'])) {
        $status = 'invalid_role';
    } elseif ($role === 'seller' && empty($phone)) {
        $status = 'phone_required';
    } else {
        // Cek apakah username sudah ada
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $status = 'exists';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hashed_password, $role, $phone);
            
            if ($stmt->execute()) {
                header("Location: login.php?status=registered");
                exit;
            } else {
                $status = 'failed';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SISKATRA</title>
    <link rel="website icon" type="png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f2f2f2;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin: 10px auto;
            display: block;
        }
        
        .register-box {
            box-shadow: 20px -10px 10px rgba(0, 0, 0, 0.2);
            background: #0046ad;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 380px;
            color: white;
            text-align: center;
        }
        
        .register-box h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        input, select {
            width: 90%;
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 5px;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }
        
        input:focus, select:focus {
            outline: 2px solid #FFD43B;
        }
        
        select {
            cursor: pointer;
            background: white;
            color: #333;
        }
        
        select option[value=""] {
            color: gray;
        }
        
        .password-container {
            position: relative;
            width: 90%;
            margin: 10px auto;
        }
        
        .password-container input {
            width: 100%;
            padding-right: 45px;
            margin: 0;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #666;
        }
        
        .toggle-password:hover {
            color: #0046ad;
        }
        
        #phoneField {
            display: none;
        }
        
        #phoneField input {
            width: 90%;
        }
        
        button[type="submit"] {
            background: #FFD43B;
            border: none;
            padding: 12px;
            width: 95%;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            font-family: 'Montserrat', sans-serif;
            transition: all 0.3s ease;
            color: #333;
            margin-top: 10px;
        }
        
        button[type="submit"]:hover {
            background: #FFC107;
            transform: translateY(-2px);
        }
        
        .register-box p {
            margin-top: 20px;
            font-size: 14px;
        }
        
        .register-box a {
            color: white;
            text-decoration: underline;
        }
        
        .register-box a:hover {
            color: #FFD43B;
        }
        
        /* Notification Styles */
        .notif {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
            color: white;
            animation: fadeIn 0.5s ease-in-out;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            max-width: 300px;
        }
        
        .notif.success { background: #3674B5; }
        .notif.failed { background: #E74C3C; }
        .notif.exists { background: #E67E22; }
        .notif.empty { background: #E74C3C; }
        .notif.invalid_role { background: #E74C3C; }
        .notif.phone_required { background: #E67E22; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 400px) {
            .register-box {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-box">
        <h2>REGISTER</h2>
        <img src="assets/logo_siskatrabaru.png" alt="Logo SISKATRA" class="logo">
        
        <form action="" method="POST">
            <input type="text" name="username" placeholder="Username" required 
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <span id="eye-icon">👁</span>
                </button>
            </div>
            
            <select name="role" required onchange="togglePhoneField(this.value)">
                <option value="" style="color: gray;">Pilih Role Anda</option>
                <option value="buyer" <?php echo (isset($_POST['role']) && $_POST['role'] === 'buyer') ? 'selected' : ''; ?>>Buyer</option>
                <option value="seller" <?php echo (isset($_POST['role']) && $_POST['role'] === 'seller') ? 'selected' : ''; ?>>Seller</option>
            </select>
            
            <!-- Field No WhatsApp (Hanya untuk Seller) -->
            <div id="phoneField">
                <input type="tel" name="phone" placeholder="No WhatsApp (contoh: 081234567890)" 
                       pattern="[0-9]{10,13}" title="Minimal 10 digit, tanpa spasi/simbol"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
            
            <button type="submit">Register</button>
        </form>
        
        <p>Sudah punya akun? <a href="login.php">Login</a></p>
    </div>
    
    <?php if (!empty($status)): ?>
    <div class="notif <?php echo htmlspecialchars($status); ?>">
        <?php
        switch ($status) {
            case 'success':
                echo "✅ Registrasi berhasil! Silakan login sekarang.";
                break;
            case 'failed':
                echo "❌ Registrasi gagal! Coba lagi.";
                break;
            case 'exists':
                echo "⚠️ Username sudah digunakan!";
                break;
            case 'empty':
                echo "❌ Semua field harus diisi!";
                break;
            case 'invalid_role':
                echo "❌ Role tidak valid!";
                break;
            case 'phone_required':
                echo "⚠️ No WhatsApp wajib untuk Seller!";
                break;
            default:
                echo "❌ Terjadi kesalahan!";
        }
        ?>
    </div>
    <script>
        setTimeout(() => {
            const notif = document.querySelector('.notif');
            if (notif) notif.style.display = 'none';
        }, 3000);
    </script>
    <?php endif; ?>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                eyeIcon.textContent = '👁';
            }
        }
        
        function togglePhoneField(role) {
            const phoneDiv = document.getElementById('phoneField');
            phoneDiv.style.display = (role === 'seller') ? 'block' : 'none';
        }
        
        // Check on page load if seller was selected
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.querySelector('select[name="role"]');
            if (roleSelect.value === 'seller') {
                togglePhoneField('seller');
            }
        });
    </script>
</body>
</html>
