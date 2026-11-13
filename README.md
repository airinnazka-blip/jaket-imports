# 🧥 Aplikasi Jaket Imports

Website e-commerce "Jaket Imports" — belanja jaket import original lebih mudah, cepat, dan terpercaya.
Dibangun dengan **PHP Native + MySQL** sebagai project PKK.

> Oleh: **Airin Mawaddah Azka**

## ✨ Fitur (sesuai laporan)

### User / Pelanggan
- Login & Registrasi akun
- Beranda & promo
- Katalog produk + pencarian, filter kategori, & urutkan harga
- Detail produk + rating & ulasan (1–5 bintang)
- Keranjang belanja (tambah/kurang/hapus)
- Checkout: alamat pengiriman, metode pembayaran, kode promo
- Pembayaran simulasi (Transfer / E-Wallet / COD)
- Riwayat pesanan + tracking real-time (diproses → dikirim → selesai)
- Profil (edit data & ganti password)
- Notifikasi

### Admin
- Dashboard statistik + grafik penjualan 7 hari
- Kelola produk (tambah / ubah / hapus / aktif-nonaktif, upload gambar)
- Kelola pesanan (konfirmasi → proses → kirim → selesai, batalkan)
- Kelola promo & diskon
- Laporan penjualan & pendapatan (filter tanggal, produk terlaris, per kategori, per metode bayar)

## 🛠️ Teknologi
- PHP 8 (native, tanpa framework)
- MySQL 8 (PDO prepared statement)
- HTML + CSS custom + JavaScript vanilla (tanpa CDN)

## 📦 Instalasi (Laragon / XAMPP)

1. **Salin folder proyek** ke web root:
   - Laragon: `C:\laragon\www\jaket-imports`
   - XAMPP: `C:\xampp\htdocs\jaket-imports`

2. **Jalankan server** Apache & MySQL (Laragon: klik *Start All*).

3. **Buat database** — buka *Terminal* Laragon (atau phpMyAdmin → SQL), lalu jalankan:
   ```sql
   SOURCE C:/laragon/www/jaket-imports/database/schema.sql;
   ```
   Atau di phpMyAdmin: import file `database/schema.sql`.
   > Database `jaket_imports` beserta tabel & data awal akan dibuat otomatis.

4. **Akses aplikasi**:
   - Laragon: `http://jaket-imports.test`
   - XAMPP: `http://localhost/jaket-imports`
   - Alternatif tanpa Apache: `php -S localhost:8080 -t C:\laragon\www\jaket-imports` lalu buka `http://localhost:8080`

> Konfigurasi database bisa diubah di `config/db.php` (host, user, password).

## 🔑 Akun Demo

| Role  | Email                     | Password  |
|-------|---------------------------|-----------|
| Admin | admin@jaketimports.com    | admin123  |
| User  | budi@mail.com             | user123   |

Kode promo contoh: `JAKET10` (diskon 10%), `JAKET50`, `JAKET100`.

## 📁 Struktur Folder

```
jaket-imports/
├── config/db.php            # koneksi PDO + base URL
├── includes/                # functions, auth, header, footer
├── assets/css|js            # tema & interaksi
├── images/                  # gambar produk (SVG placeholder)
├── database/schema.sql      # skema + seed data
├── auth/                    # login, register, logout
├── index.php                # beranda
├── katalog.php              # katalog + filter
├── produk.php               # detail + rating/ulasan
├── keranjang.php            # keranjang
├── checkout.php             # checkout
├── pembayaran.php           # simulasi bayar
├── pesanan.php              # riwayat pesanan
├── pesanan-detail.php       # detail + tracking
├── profil.php               # profil user
├── notifikasi.php           # notifikasi
└── admin/                   # dashboard, produk, pesanan, promo, laporan
```

## ⚠️ Catatan
- Semua password di-hash dengan `password_hash()` (bcrypt).
- Semua query menggunakan prepared statement (PDO) + proteksi CSRF & XSS.
- Gambar produk bawaan berupa placeholder SVG; bisa diganti lewat menu Admin → Produk → Edit (upload gambar JPG/PNG/WEBP/GIF/SVG maks. 2MB).
