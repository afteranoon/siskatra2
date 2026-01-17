<?php
/**
 * SISKATRA - Halaman Login
 * Login untuk Admin, Seller, dan Buyer
 */
session_start();

// Jika sudah login, redirect ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SISKATRA</title>
    <!-- Fixed favicon to use .png extension -->
    <link rel="icon" type="image/png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #ffffff;
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
        
        .login-box {
            box-shadow: 20px -10px 10px rgba(0, 0, 0, 0.2);
            background: #0046ad;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 380px;
            color: white;
            text-align: center;
        }
        
        .login-box h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        input {
            width: 90%;
            padding: 12px 15px;
            margin: 10px 0;
            border-radius: 5px;
            border: none;
            font-family: 'Montserrat', sans-serif;
            font-size: 14px;
        }
        
        input:focus {
            outline: 2px solid #FFD43B;
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
        
        .role-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        
        .role-container label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .role-container label:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .role-container input[type="radio"] {
            width: auto;
            margin: 0;
            cursor: pointer;
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
        }
        
        button[type="submit"]:hover {
            background: #FFC107;
            transform: translateY(-2px);
        }
        
        .login-box p {
            margin-top: 20px;
            font-size: 14px;
        }
        
        .login-box a {
            color: white;
            text-decoration: underline;
        }
        
        .login-box a:hover {
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
        .notif.wrongpass { background: #578FCA; }
        .notif.nouser { background: #A1E3F9; color: #000; }
        .notif.error { background: #E74C3C; }
        .notif.logout { background: #3674B5; }
        .notif.registered { background: #27AE60; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 400px) {
            .login-box {
                padding: 25px 20px;
            }
            
            .role-container {
                gap: 10px;
            }
            
            .role-container label {
                font-size: 12px;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>LOGIN</h2>
        <!-- Logo uses .png extension -->
        <img src="assets/logo_siskatrabaru.png" alt="Logo SISKATRA" class="logo">
        
        <form action="proses_login_secure.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            
            <div class="password-container">
                <input type="password" name="password" id="password" placeholder="Password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <span id="eye-icon">👁</span>
                </button>
            </div>
            
            <!-- Removed Admin option, only show Buyer and Seller -->
            <div class="role-container">
                <label><input type="radio" name="role" value="buyer" required> Buyer</label>
                <label><input type="radio" name="role" value="seller"> Seller</label>
            </div>
            <!-- Hidden field for admin detection via JavaScript -->
            <input type="hidden" name="admin_mode" id="admin_mode" value="0">
            
            <button type="submit">Login</button>
        </form>
        
        <p>Belum punya akun? <a href="register.php">Register</a></p>
    </div>
    
    <?php if (!empty($status)): ?>
    <div class="notif <?php echo htmlspecialchars($status); ?>">
        <?php
        switch ($status) {
            case 'success':
                echo "Login berhasil!";
                break;
            case 'wrongpass':
                echo "Password salah!";
                break;
            case 'nouser':
                echo "User tidak ditemukan!";
                break;
            case 'invalid_role':
                echo "Role tidak valid!";
                break;
            case 'inactive':
                echo "Akun tidak aktif!";
                break;
            case 'logout':
            case 'logged_out':
                echo "Anda telah berhasil keluar.";
                break;
            case 'registered':
                echo "Registrasi berhasil! Silakan login.";
                break;
            case 'empty':
                echo "Semua field harus diisi!";
                break;
            default:
                echo "Terjadi kesalahan!";
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
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.querySelector('input[name="username"]').value.trim();
            if (username === '@admin') {
                document.getElementById('admin_mode').value = '1';
            }
        });
    </script>
</body>
</html>
