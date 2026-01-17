<?php
/**
 * SISKATRA - Stories Feature
 * Fitur story seperti Instagram untuk menampilkan produk yang available hari ini
 * Story bertahan 24 jam
 */
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

include 'koneksi.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = '';
$error = '';

// Handle upload story (hanya seller)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_story']) && $user_role === 'seller') {
    $caption = trim($_POST['caption'] ?? '');
    $product_id = !empty($_POST['product_id']) ? intval($_POST['product_id']) : null;
    
    // Handle file upload
    if (isset($_FILES['story_image']) && $_FILES['story_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['story_image']['type'];
        $file_size = $_FILES['story_image']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.";
        } elseif ($file_size > $max_size) {
            $error = "Ukuran file terlalu besar. Maksimal 5MB.";
        } else {
            // Create uploads directory if not exists
            $upload_dir = 'uploads/stories/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $extension = pathinfo($_FILES['story_image']['name'], PATHINFO_EXTENSION);
            $filename = 'story_' . $user_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['story_image']['tmp_name'], $filepath)) {
                // Insert ke database
                $stmt = $conn->prepare("INSERT INTO stories (seller_id, image_url, caption, product_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $user_id, $filename, $caption, $product_id);
                
                if ($stmt->execute()) {
                    $message = "Story berhasil diupload!";
                } else {
                    $error = "Gagal menyimpan story ke database.";
                    unlink($filepath); // Hapus file jika gagal insert
                }
            } else {
                $error = "Gagal mengupload file.";
            }
        }
    } else {
        $error = "Pilih gambar untuk story.";
    }
}

// Handle delete story
if (isset($_GET['delete']) && $user_role === 'seller') {
    $story_id = intval($_GET['delete']);
    
    // Pastikan story milik seller ini
    $stmt = $conn->prepare("SELECT image_url FROM stories WHERE story_id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $story_id, $user_id);
    $stmt->execute();
    $story = $stmt->get_result()->fetch_assoc();
    
    if ($story) {
        // Hapus file
        $filepath = 'uploads/stories/' . $story['image_url'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        // Hapus dari database
        $stmt = $conn->prepare("DELETE FROM stories WHERE story_id = ?");
        $stmt->bind_param("i", $story_id);
        $stmt->execute();
        
        $message = "Story berhasil dihapus!";
    }
}

// Auto-delete expired stories (24 jam)
$conn->query("DELETE FROM stories WHERE expires_at < NOW()");

// Ambil semua stories yang masih aktif (belum expired)
$stories_stmt = $conn->prepare("
    SELECT 
        s.*,
        u.username AS seller_name,
        u.phone AS seller_phone,
        p.product_name,
        p.price AS product_price,
        TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) AS minutes_ago
    FROM stories s
    JOIN users u ON s.seller_id = u.id
    LEFT JOIN products p ON s.product_id = p.product_id
    WHERE s.expires_at > NOW()
    ORDER BY s.created_at DESC
");
$stories_stmt->execute();
$all_stories = $stories_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group stories by seller
$stories_by_seller = [];
foreach ($all_stories as $story) {
    $sid = $story['seller_id'];
    if (!isset($stories_by_seller[$sid])) {
        $stories_by_seller[$sid] = [
            'seller_name' => $story['seller_name'],
            'seller_phone' => $story['seller_phone'],
            'stories' => []
        ];
    }
    $stories_by_seller[$sid]['stories'][] = $story;
}

// Ambil produk seller untuk pilihan di form (jika seller)
$seller_products = [];
if ($user_role === 'seller') {
    $prod_stmt = $conn->prepare("SELECT product_id, product_name FROM products WHERE seller_id = ? AND stock > 0 ORDER BY product_name");
    $prod_stmt->bind_param("i", $user_id);
    $prod_stmt->execute();
    $seller_products = $prod_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Ambil story seller ini
    $my_stories_stmt = $conn->prepare("
        SELECT s.*, p.product_name 
        FROM stories s 
        LEFT JOIN products p ON s.product_id = p.product_id 
        WHERE s.seller_id = ? AND s.expires_at > NOW()
        ORDER BY s.created_at DESC
    ");
    $my_stories_stmt->bind_param("i", $user_id);
    $my_stories_stmt->execute();
    $my_stories = $my_stories_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Helper function untuk format waktu
function timeAgo($minutes) {
    if ($minutes < 1) return 'Baru saja';
    if ($minutes < 60) return $minutes . ' menit lalu';
    $hours = floor($minutes / 60);
    if ($hours < 24) return $hours . ' jam lalu';
    return 'Lebih dari sehari';
}

function timeRemaining($created_at) {
    $expires = strtotime($created_at) + (24 * 60 * 60);
    $remaining = $expires - time();
    if ($remaining <= 0) return 'Expired';
    $hours = floor($remaining / 3600);
    $minutes = floor(($remaining % 3600) / 60);
    return $hours . 'j ' . $minutes . 'm tersisa';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stories - SISKATRA</title>
    <!-- Fixed favicon extension to .png -->
    <link rel="icon" type="image/png" href="assets/logo_siskatrabaru.png">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: #ffffff;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header img.logo { height: 40px; }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .back-btn {
            background: #0046ad;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 25px;
        }
        
        .page-title {
            font-size: 24px;
            color: #284e7f;
            margin-bottom: 20px;
        }
        
        /* Messages */
        .message {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Stories Carousel */
        .stories-section {
            margin-bottom: 30px;
        }
        
        .stories-section h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .stories-carousel {
            display: flex;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            scrollbar-width: thin;
        }
        
        .story-avatar {
            flex-shrink: 0;
            text-align: center;
            cursor: pointer;
        }
        
        .story-avatar .avatar-ring {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888);
            margin-bottom: 6px;
        }
        
        .story-avatar .avatar-ring.viewed {
            background: #ccc;
        }
        
        .story-avatar .avatar-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 3px solid white;
            background: #FFD43B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            overflow: hidden;
        }
        
        .story-avatar .avatar-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .story-avatar .seller-name {
            font-size: 11px;
            color: #333;
            max-width: 70px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Upload Section (Seller only) */
        .upload-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }
        
        .upload-section h3 {
            font-size: 18px;
            color: #0046ad;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .file-input-wrapper {
            position: relative;
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .file-input-wrapper:hover {
            border-color: #0046ad;
            background: #f8f9ff;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-input-wrapper .upload-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .file-input-wrapper p {
            color: #666;
            font-size: 14px;
        }
        
        .file-input-wrapper .file-name {
            color: #0046ad;
            font-weight: 600;
            margin-top: 10px;
        }
        
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin: 10px auto;
            display: none;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0046ad;
        }
        
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        
        .btn-upload {
            background: linear-gradient(45deg, #f09433, #e6683c, #dc2743);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-upload:hover {
            transform: scale(1.02);
        }
        
        /* Camera/Gallery Buttons */
        .capture-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn-capture {
            flex: 1;
            padding: 12px;
            border: 2px solid #0046ad;
            background: white;
            color: #0046ad;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .btn-capture:hover {
            background: #0046ad;
            color: white;
        }
        
        .btn-capture.active {
            background: #0046ad;
            color: white;
        }
        
        /* My Stories (Seller) */
        .my-stories {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            margin-bottom: 30px;
        }
        
        .my-stories h3 {
            font-size: 18px;
            color: #0046ad;
            margin-bottom: 20px;
        }
        
        .my-stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .my-story-card {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            aspect-ratio: 9/16;
            background: #f0f0f0;
        }
        
        .my-story-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .my-story-card .story-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 15px 10px 10px;
            color: white;
        }
        
        .my-story-card .story-time {
            font-size: 10px;
            opacity: 0.8;
        }
        
        .my-story-card .story-caption {
            font-size: 11px;
            margin-top: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .my-story-card .delete-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(220,53,69,0.9);
            color: white;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Story Viewer Modal */
        .story-viewer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        .story-viewer.active {
            display: flex;
        }
        
        .story-viewer-content {
            position: relative;
            max-width: 400px;
            width: 100%;
            max-height: 90vh;
        }
        
        .story-progress-bar {
            display: flex;
            gap: 4px;
            padding: 10px 15px;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
        }
        
        .progress-segment {
            flex: 1;
            height: 3px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            overflow: hidden;
        }
        
        .progress-segment .progress-fill {
            height: 100%;
            background: white;
            width: 0%;
            transition: width 0.1s linear;
        }
        
        .progress-segment.completed .progress-fill {
            width: 100%;
        }
        
        .progress-segment.active .progress-fill {
            animation: progressFill 5s linear forwards;
        }
        
        @keyframes progressFill {
            from { width: 0%; }
            to { width: 100%; }
        }
        
        .story-header {
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 10;
        }
        
        .story-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }
        
        .story-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #FFD43B;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .story-user-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .story-time-ago {
            font-size: 11px;
            opacity: 0.7;
        }
        
        .story-close {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            padding: 5px;
        }
        
        .story-image-container {
            width: 100%;
            aspect-ratio: 9/16;
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .story-image-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .story-caption-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            padding: 40px 20px 20px;
            color: white;
        }
        
        .story-caption-text {
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .story-product-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
        }
        
        .story-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .story-nav.prev { left: -50px; }
        .story-nav.next { right: -50px; }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 600px) {
            .container { padding: 15px; }
            
            .capture-buttons {
                flex-direction: column;
            }
            
            .story-nav.prev { left: 10px; }
            .story-nav.next { right: 10px; }
            
            .my-stories-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <!-- Fixed logo extension to .png -->
        <img src="assets/logo_siskatra_text.png" alt="SISKATRA" class="logo">
        <div class="header-right">
            <a href="dashboard.php" class="back-btn">← Kembali</a>
        </div>
    </div>
    
    <div class="container">
        <h1 class="page-title">Stories</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Stories Carousel (All active stories) -->
        <?php if (!empty($stories_by_seller)): ?>
            <div class="stories-section">
                <h3>Produk Available Hari Ini</h3>
                <div class="stories-carousel">
                    <?php foreach ($stories_by_seller as $seller_id => $seller_data): ?>
                        <div class="story-avatar" onclick="openStoryViewer(<?= $seller_id ?>)">
                            <div class="avatar-ring">
                                <div class="avatar-img">
                                    <?php 
                                    $first_story = $seller_data['stories'][0];
                                    ?>
                                    <img src="uploads/stories/<?= htmlspecialchars($first_story['image_url']) ?>" alt="">
                                </div>
                            </div>
                            <div class="seller-name"><?= htmlspecialchars($seller_data['seller_name']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="stories-section">
                <div class="empty-state">
                    <div class="icon">📷</div>
                    <p>Belum ada story hari ini</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Upload Section (Seller only) -->
        <?php if ($user_role === 'seller'): ?>
            <div class="upload-section">
                <h3>📷 Buat Story Baru</h3>
                
                <form class="upload-form" method="POST" enctype="multipart/form-data">
                    <div class="capture-buttons">
                        <button type="button" class="btn-capture" onclick="openCamera()">
                            📸 Kamera
                        </button>
                        <button type="button" class="btn-capture" onclick="openGallery()">
                            🖼️ Galeri
                        </button>
                    </div>
                    
                    <div class="file-input-wrapper" id="fileInputWrapper">
                        <input type="file" name="story_image" id="storyImage" accept="image/*" required>
                        <div class="upload-icon">📷</div>
                        <p>Klik atau drag foto ke sini</p>
                        <p class="file-name" id="fileName"></p>
                        <img class="preview-image" id="previewImage" alt="Preview">
                    </div>
                    
                    <div class="form-group">
                        <label>Caption (opsional)</label>
                        <textarea name="caption" placeholder="Tulis caption untuk story..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Tag Produk (opsional)</label>
                        <select name="product_id">
                            <option value="">-- Pilih Produk --</option>
                            <?php foreach ($seller_products as $prod): ?>
                                <option value="<?= $prod['product_id'] ?>"><?= htmlspecialchars($prod['product_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" name="upload_story" class="btn-upload">
                        🚀 Upload Story
                    </button>
                </form>
            </div>
            
            <!-- My Stories -->
            <?php if (!empty($my_stories)): ?>
                <div class="my-stories">
                    <h3>Story Saya (<?= count($my_stories) ?>)</h3>
                    <div class="my-stories-grid">
                        <?php foreach ($my_stories as $story): ?>
                            <div class="my-story-card">
                                <img src="uploads/stories/<?= htmlspecialchars($story['image_url']) ?>" alt="">
                                <a href="?delete=<?= $story['story_id'] ?>" class="delete-btn" onclick="return confirm('Hapus story ini?')">×</a>
                                <div class="story-overlay">
                                    <div class="story-time"><?= timeRemaining($story['created_at']) ?></div>
                                    <?php if ($story['caption']): ?>
                                        <div class="story-caption"><?= htmlspecialchars($story['caption']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Story Viewer Modal -->
    <div class="story-viewer" id="storyViewer">
        <div class="story-viewer-content">
            <div class="story-progress-bar" id="progressBar"></div>
            
            <div class="story-header">
                <div class="story-user-info">
                    <div class="story-user-avatar">🏪</div>
                    <div>
                        <div class="story-user-name" id="storyUserName"></div>
                        <div class="story-time-ago" id="storyTimeAgo"></div>
                    </div>
                </div>
                <button class="story-close" onclick="closeStoryViewer()">×</button>
            </div>
            
            <div class="story-image-container">
                <img id="storyImage" src="/placeholder.svg" alt="">
                <div class="story-caption-overlay" id="storyCaptionOverlay">
                    <div class="story-caption-text" id="storyCaptionText"></div>
                    <div class="story-product-tag" id="storyProductTag" style="display:none;">
                        🏷️ <span id="storyProductName"></span>
                    </div>
                </div>
            </div>
            
            <button class="story-nav prev" onclick="prevStory()">‹</button>
            <button class="story-nav next" onclick="nextStory()">›</button>
        </div>
    </div>
    
    <script>
        // File input handling
        const storyImageInput = document.getElementById('storyImage');
        const fileNameDisplay = document.getElementById('fileName');
        const previewImage = document.getElementById('previewImage');
        
        storyImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileNameDisplay.textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewImage.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
        
        function openCamera() {
            storyImageInput.setAttribute('capture', 'environment');
            storyImageInput.click();
        }
        
        function openGallery() {
            storyImageInput.removeAttribute('capture');
            storyImageInput.click();
        }
        
        // Story viewer
        const storiesData = <?= json_encode($stories_by_seller) ?>;
        let currentSellerId = null;
        let currentStoryIndex = 0;
        let storyTimer = null;
        
        function openStoryViewer(sellerId) {
            currentSellerId = sellerId;
            currentStoryIndex = 0;
            document.getElementById('storyViewer').classList.add('active');
            showStory();
        }
        
        function closeStoryViewer() {
            document.getElementById('storyViewer').classList.remove('active');
            clearTimeout(storyTimer);
            currentSellerId = null;
        }
        
        function showStory() {
            if (!currentSellerId || !storiesData[currentSellerId]) return;
            
            const sellerStories = storiesData[currentSellerId].stories;
            const story = sellerStories[currentStoryIndex];
            
            // Update UI
            document.getElementById('storyUserName').textContent = storiesData[currentSellerId].seller_name;
            document.getElementById('storyTimeAgo').textContent = formatMinutesAgo(story.minutes_ago);
            document.getElementById('storyImage').src = 'uploads/stories/' + story.image_url;
            
            // Caption
            const captionText = document.getElementById('storyCaptionText');
            const captionOverlay = document.getElementById('storyCaptionOverlay');
            if (story.caption) {
                captionText.textContent = story.caption;
                captionOverlay.style.display = 'block';
            } else {
                captionText.textContent = '';
            }
            
            // Product tag
            const productTag = document.getElementById('storyProductTag');
            if (story.product_name) {
                document.getElementById('storyProductName').textContent = story.product_name + ' - Rp ' + formatPrice(story.product_price);
                productTag.style.display = 'inline-flex';
            } else {
                productTag.style.display = 'none';
            }
            
            // Progress bar
            updateProgressBar(sellerStories.length);
            
            // Auto advance
            clearTimeout(storyTimer);
            storyTimer = setTimeout(() => {
                nextStory();
            }, 5000);
        }
        
        function updateProgressBar(total) {
            const progressBar = document.getElementById('progressBar');
            progressBar.innerHTML = '';
            
            for (let i = 0; i < total; i++) {
                const segment = document.createElement('div');
                segment.className = 'progress-segment';
                if (i < currentStoryIndex) segment.classList.add('completed');
                if (i === currentStoryIndex) segment.classList.add('active');
                segment.innerHTML = '<div class="progress-fill"></div>';
                progressBar.appendChild(segment);
            }
        }
        
        function nextStory() {
            const sellerStories = storiesData[currentSellerId].stories;
            if (currentStoryIndex < sellerStories.length - 1) {
                currentStoryIndex++;
                showStory();
            } else {
                // Move to next seller or close
                const sellerIds = Object.keys(storiesData);
                const currentIndex = sellerIds.indexOf(String(currentSellerId));
                if (currentIndex < sellerIds.length - 1) {
                    currentSellerId = sellerIds[currentIndex + 1];
                    currentStoryIndex = 0;
                    showStory();
                } else {
                    closeStoryViewer();
                }
            }
        }
        
        function prevStory() {
            if (currentStoryIndex > 0) {
                currentStoryIndex--;
                showStory();
            } else {
                // Move to prev seller
                const sellerIds = Object.keys(storiesData);
                const currentIndex = sellerIds.indexOf(String(currentSellerId));
                if (currentIndex > 0) {
                    currentSellerId = sellerIds[currentIndex - 1];
                    currentStoryIndex = storiesData[currentSellerId].stories.length - 1;
                    showStory();
                }
            }
        }
        
        function formatMinutesAgo(minutes) {
            if (minutes < 1) return 'Baru saja';
            if (minutes < 60) return minutes + ' menit lalu';
            const hours = Math.floor(minutes / 60);
            return hours + ' jam lalu';
        }
        
        function formatPrice(price) {
            return new Intl.NumberFormat('id-ID').format(price);
        }
        
        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeStoryViewer();
            if (e.key === 'ArrowRight') nextStory();
            if (e.key === 'ArrowLeft') prevStory();
        });
        
        // Click on story image to advance
        document.getElementById('storyImage').addEventListener('click', function(e) {
            const rect = e.target.getBoundingClientRect();
            const x = e.clientX - rect.left;
            if (x < rect.width / 2) {
                prevStory();
            } else {
                nextStory();
            }
        });
    </script>
</body>
</html>
