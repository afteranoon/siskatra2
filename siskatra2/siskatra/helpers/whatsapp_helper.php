<?php
/**
 * WhatsApp Helper Functions
 * Untuk generate link WhatsApp dengan format otomatis
 */

/**
 * Generate link WhatsApp untuk menghubungi seller
 * @param string $product_name - Nama produk
 * @param string $seller_phone - Nomor telepon seller
 * @param int|null $order_id - ID pesanan (opsional)
 * @return string URL WhatsApp link
 */
function generateWhatsAppLink($product_name, $seller_phone, $order_id = null) {
    // Bersihkan nomor telepon dari karakter non-digit
    $phone = preg_replace('/\D/', '', $seller_phone);
    
    // Tambahkan kode negara jika belum ada
    if (strlen($phone) <= 10) {
        // Anggap nomor lokal, tambah kode negara Indonesia
        $phone = '62' . ltrim($phone, '0');
    } elseif (substr($phone, 0, 1) === '0') {
        // Ganti 0 di awal dengan kode negara
        $phone = '62' . substr($phone, 1);
    }
    
    // Buat pesan WhatsApp
    if ($order_id) {
        $msg = "Halo, saya ingin mengonfirmasi pembelian produk \"$product_name\" dengan Order ID: $order_id. Berapa total pembayarannya dan bagaimana cara pembayarannya?";
    } else {
        $msg = "Halo, saya tertarik dengan produk \"$product_name\". Bisakah saya tahu harganya?";
    }
    
    $encoded_msg = urlencode($msg);
    return "https://wa.me/{$phone}?text={$encoded_msg}";
}

/**
 * Generate link WhatsApp untuk seller menghubungi buyer
 * @param string $buyer_phone - Nomor telepon buyer
 * @param int $order_id - ID pesanan
 * @return string URL WhatsApp link
 */
function generateSellerWhatsAppLink($buyer_phone, $order_id) {
    $phone = preg_replace('/\D/', '', $buyer_phone);
    
    if (strlen($phone) <= 10) {
        $phone = '62' . ltrim($phone, '0');
    } elseif (substr($phone, 0, 1) === '0') {
        $phone = '62' . substr($phone, 1);
    }
    
    $msg = "Halo, pesanan Anda #$order_id sedang saya proses. Terima kasih telah memesan di SISKATRA!";
    $encoded_msg = urlencode($msg);
    return "https://wa.me/{$phone}?text={$encoded_msg}";
}

/**
 * Format nomor telepon ke format yang rapi
 * @param string $phone - Nomor telepon
 * @return string Nomor telepon terformat
 */
function formatPhoneNumber($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    
    if (strlen($phone) <= 10) {
        return '62' . ltrim($phone, '0');
    } elseif (substr($phone, 0, 1) === '0') {
        return '62' . substr($phone, 1);
    }
    
    return $phone;
}
?>
