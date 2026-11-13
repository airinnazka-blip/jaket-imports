-- ============================================================
-- MIGRASI: Konfirmasi Penerimaan Pesanan & Bukti Pembayaran
-- Jalankan jika database jaket_imports SUDAH pernah dibuat
-- sebelum fitur ini ditambahkan.
--
-- Cara jalan (terminal Laragon):
--   mysql -u root jaket_imports < database/migration_penerimaan.sql
-- Atau lewat phpMyAdmin: tab SQL -> paste isi file ini -> Go.
-- ============================================================
USE jaket_imports;

-- Waktu barang dikirim (untuk batas konfirmasi 5 hari)
ALTER TABLE orders
  ADD COLUMN shipped_at TIMESTAMP NULL DEFAULT NULL AFTER status;

-- Detail bukti pembayaran (transfer / e-wallet)
ALTER TABLE payments
  ADD COLUMN paid_amount DECIMAL(12,2) DEFAULT NULL AFTER status,
  ADD COLUMN sender_name VARCHAR(120) DEFAULT NULL AFTER paid_amount,
  ADD COLUMN sender_bank VARCHAR(80) DEFAULT NULL AFTER sender_name,
  ADD COLUMN transfer_at DATETIME DEFAULT NULL AFTER sender_bank,
  ADD COLUMN proof_image VARCHAR(255) DEFAULT NULL AFTER transfer_at,
  ADD COLUMN note TEXT DEFAULT NULL AFTER proof_image;
