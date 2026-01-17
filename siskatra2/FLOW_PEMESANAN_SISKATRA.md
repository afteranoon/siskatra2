# FLOW PEMESANAN SISKATRA - DOKUMENTASI

## Overview
Sistem pemesanan Siskatra dirancang agar buyer dapat langsung membuat pesanan dan menghubungi seller via WhatsApp dengan referensi Order ID yang jelas.

---

## 1. FLOW BUYER MEMBELI PRODUK

### Step 1: Buyer Membuka Dashboard
- Buyer login dan melihat produk di `dashboard.php`
- Buyer klik "Lihat Detail" pada produk yang diminati

### Step 2: Popup Detail Produk
- Popup menampilkan:
  - Foto produk
  - Nama, kategori, harga
  - Deskripsi produk
  - Form input: Jumlah & Catatan (untuk buyer)
  - Tombol "Pesan Sekarang"

### Step 3: Buyer Submit Pesanan
- Buyer isi jumlah dan catatan (opsional)
- Klik "Pesan Sekarang"
- Form submit ke `buat_pesanan.php` (POST)

### Step 4: Pesanan Tersimpan di Database
File: `buat_pesanan.php`
- Validasi: Produk ada? Stok cukup?
- INSERT ke tabel `orders` dengan status: `pending`
- UPDATE stok produk (kurangi sesuai jumlah)
- Redirect ke `view_order_detail.php?id=$order_id`

### Step 5: Halaman Konfirmasi
File: `view_order_detail.php`
- Tampilkan ringkasan pesanan
- Tampilkan nomor pesanan (#Order ID)
- Tombol: "Hubungi Penjual via WhatsApp"
- Link WhatsApp include Order ID dalam pesan

### Step 6: Buyer Hubungi Seller
- Buyer klik tombol WhatsApp
- Terbuka chat WhatsApp dengan pesan otomatis berisi Order ID
- Buyer & Seller negotiate pembayaran via WhatsApp

---

## 2. FLOW SELLER MENERIMA PESANAN

### Step 1: Seller Membuka Profile
- Seller klik profile icon di dashboard
- Masuk ke `profile_seller.php`

### Step 2: Lihat Tab "Pesanan Masuk"
- Tab menampilkan semua pesanan dengan status
- Pesanan pending ditampilkan dengan badge alert
- Stat card menampilkan jumlah pesanan pending

### Step 3: Lihat Detail Pesanan
- Seller klik "Lihat Detail" pada pesanan
- Masuk ke `view_order_detail.php`
- Tampilkan:
  - Info produk (nama, harga, jumlah)
  - Info pembeli (nama, nomor WhatsApp)
  - Total pembayaran
  - Status pesanan saat ini

### Step 4: Update Status Pesanan
- Seller bisa ubah status dari dropdown:
  - pending → confirmed → processing → shipped → completed
- Atau batalkan dengan status: cancelled
- Klik "Update Status" untuk simpan

### Step 5: Chat dengan Buyer
- Dari halaman detail, seller bisa klik "Hubungi via WhatsApp"
- Chat untuk konfirmasi pembayaran, pengiriman, dll

### Step 6: Tracking Pesanan
- Buyer bisa lihat status pesanan di `profile_buyyer.php`
- Lihat riwayat pesanan mereka
- Update status akan langsung terlihat

---

## 3. STRUKTUR DATABASE ORDERS

\`\`\`
orders table:
- order_id (PRIMARY KEY)
- buyer_id (FOREIGN KEY - users)
- seller_id (FOREIGN KEY - users)
- product_id (FOREIGN KEY - products)
- quantity (INT)
- total_price (DECIMAL)
- notes (TEXT - catatan buyer)
- status (ENUM: pending, confirmed, processing, shipped, completed, cancelled)
- order_date (TIMESTAMP)
\`\`\`

---

## 4. FILE-FILE YANG DIGUNAKAN

### Core Files:
1. `dashboard.php` - Menampilkan produk dengan form pesanan
2. `buat_pesanan.php` - Handler submit pesanan
3. `view_order_detail.php` - Detail pesanan (buyer & seller)
4. `profile_seller.php` - Profile seller + tab pesanan masuk
5. `profile_buyyer.php` - Profile buyer + history pesanan
6. `helpers/whatsapp_helper.php` - Helper untuk generate WhatsApp link

### Database:
- `scripts/01_siskatra_database.sql` - Setup database dengan tabel orders

---

## 5. NOTIFIKASI & STATUS

### Status Pesanan:
- **pending** - Pesanan baru, belum dikonfirmasi seller
- **confirmed** - Seller sudah konfirmasi & receive payment
- **processing** - Sedang diproses/dikemas
- **shipped** - Sudah dikirim
- **completed** - Selesai
- **cancelled** - Dibatalkan

### WhatsApp Messages:
- **Buyer → Seller**: Pesan otomatis include Order ID
- **Seller → Buyer**: Update status via WhatsApp jika perlu

---

## 6. FITUR KEAMANAN

1. **Prepared Statement** - Cegah SQL injection
2. **Session Validation** - Hanya buyer/seller yang bersangkutan bisa lihat detail
3. **Stock Management** - Otomatis update stok saat order dibuat
4. **Transaction Handling** - Rollback jika ada error

---

## 7. CARA TESTING

### Sebagai Buyer:
1. Login dengan akun buyer
2. Dashboard → Klik "Lihat Detail" produk
3. Isi jumlah & catatan → "Pesan Sekarang"
4. Klik tombol WhatsApp untuk hubungi seller
5. Lihat history pesanan di Profile

### Sebagai Seller:
1. Login dengan akun seller
2. Buka Profile → Tab "Pesanan Masuk"
3. Klik "Lihat Detail" pesanan
4. Update status pesanan
5. Chat dengan buyer via WhatsApp

---

## 8. INTEGRASI WHATSAPP

Helper function `generateWhatsAppLink()`:
- Auto-format nomor telepon (tambah kode negara 62)
- Generate pesan otomatis
- Return URL siap untuk href

Contoh:
\`\`\`php
$wa_link = generateWhatsAppLink("Kopi Premium", "081234567890", 123);
// Output: https://wa.me/6281234567890?text=Halo%20saya%20ingin...
\`\`\`

---

## 9. ENHANCEMENT FUTURE

Fitur yang bisa ditambah:
- Notifikasi email saat ada pesanan baru
- Payment gateway integration (Midtrans, Stripe)
- Rating & review setelah pesanan completed
- Tracking pengiriman real-time
- Bot WhatsApp untuk notifikasi otomatis
- Analytics & reporting dashboard

---

Last Updated: 2026
