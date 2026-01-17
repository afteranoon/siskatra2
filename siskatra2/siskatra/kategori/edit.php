<?php
/**
 * SISKATRA - Edit Kategori
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

if ($category_id <= 0) {
    header('Location: index.php');
    exit();
}

// Ambil data kategori
$stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
$stmt->execute([$category_id]);
$category = $stmt->fetch();

if (!$category) {
    header('Location: index.php?error=' . urlencode('Kategori tidak ditemukan!'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['category_name'] ?? '');
    $description = trim($_POST['category_description'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        $error = "Nama kategori wajib diisi!";
    } else {
        // Cek duplikat (exclude current)
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ? AND category_id != ?");
        $check->execute([$name, $category_id]);
        
        if ($check->fetchColumn() > 0) {
            $error = "Kategori dengan nama tersebut sudah ada!";
        } else {
            // Handle upload icon baru
            $icon_name = $category['category_icon'];
            
            if (isset($_FILES['category_icon']) && $_FILES['category_icon']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $ext = strtolower(pathinfo($_FILES['category_icon']['name'], PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    // Hapus icon lama jika ada
                    if ($category['category_icon'] && file_exists('../assets/' . $category['category_icon'])) {
                        unlink('../assets/' . $category['category_icon']);
                    }
                    
                    $icon_name = 'cat_' . time() . '.' . $ext;
                    move_uploaded_file($_FILES['category_icon']['tmp_name'], '../assets/' . $icon_name);
                }
            }
            
            // Update kategori
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET category_name = ?, category_icon = ?, category_description = ?, is_active = ?
                WHERE category_id = ?
            ");
            $stmt->execute([$name, $icon_name, $description, $is_active, $category_id]);
            
            header('Location: index.php?success=' . urlencode('Kategori berhasil diperbarui!'));
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kategori - SISKATRA</title>
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
        
        .container {
            max-width: 600px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
        }
        
        .card h2 {
            color: #0046ad;
            margin-bottom: 25px;
            font-size: 22px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0046ad;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-group input[type="file"] {
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            width: 100%;
            cursor: pointer;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        .form-actions .btn {
            flex: 1;
            text-align: center;
        }
        
        .current-icon {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .current-icon img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        
        .current-icon span {
            font-size: 14px;
            color: #666;
        }
        
        .preview-icon {
            width: 60px;
            height: 60px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            display: none;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            overflow: hidden;
        }
        
        .preview-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Edit Kategori</h1>
        <a href="index.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="container">
        <div class="card">
            <h2>Edit: <?= htmlspecialchars($category['category_name']) ?></h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Nama Kategori *</label>
                    <input type="text" name="category_name" required 
                           value="<?= htmlspecialchars($category['category_name']) ?>"
                           placeholder="Contoh: Minuman, Makanan, Jasa...">
                </div>
                
                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="category_description" 
                              placeholder="Deskripsi singkat tentang kategori ini..."><?= htmlspecialchars($category['category_description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Icon Kategori</label>
                    
                    <?php if ($category['category_icon']): ?>
                        <div class="current-icon">
                            <img src="../assets/<?= htmlspecialchars($category['category_icon']) ?>" alt="Icon">
                            <span>Icon saat ini: <?= htmlspecialchars($category['category_icon']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <input type="file" name="category_icon" accept="image/*" onchange="previewIcon(event)">
                    <div class="preview-icon" id="iconPreview"></div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="is_active" 
                               <?= $category['is_active'] ? 'checked' : '' ?>>
                        <label for="is_active" style="margin:0;cursor:pointer;">Aktifkan kategori ini</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary" style="background:#6c757d;color:white;">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function previewIcon(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('iconPreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                    preview.style.display = 'flex';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
