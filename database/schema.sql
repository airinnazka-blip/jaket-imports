-- ============================================================
-- APLIKASI JAKET IMPORTS - Database Schema & Seed Data
-- Database : jaket_imports  |  MySQL 8 (utf8mb4)
-- ============================================================

CREATE DATABASE IF NOT EXISTS jaket_imports CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE jaket_imports;

-- ------------------------------------------------------------
-- TABEL: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: categories
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: products
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(150) NOT NULL,
  brand VARCHAR(80) NOT NULL DEFAULT 'Jacket Imports',
  description TEXT DEFAULT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: promos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS promos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  type ENUM('percent','nominal') NOT NULL DEFAULT 'percent',
  value DECIMAL(12,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(12,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: cart_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  qty INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: orders
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_code VARCHAR(30) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  address TEXT NOT NULL,
  payment_method ENUM('transfer','ewallet','cod') NOT NULL DEFAULT 'transfer',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('pending','processed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  shipped_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: order_items
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED DEFAULT NULL,
  product_name VARCHAR(150) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: payments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  method ENUM('transfer','ewallet','cod') NOT NULL DEFAULT 'transfer',
  status ENUM('pending','success') NOT NULL DEFAULT 'pending',
  paid_amount DECIMAL(12,2) DEFAULT NULL,
  sender_name VARCHAR(120) DEFAULT NULL,
  sender_bank VARCHAR(80) DEFAULT NULL,
  transfer_at DATETIME DEFAULT NULL,
  proof_image VARCHAR(255) DEFAULT NULL,
  note TEXT DEFAULT NULL,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: reviews
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  comment TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- TABEL: notifications
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin & user contoh
-- password admin = admin123, user = user123 (hash bcrypt)
INSERT INTO users (name, email, password, phone, address, role) VALUES
('Admin Jaket Imports', 'admin@jaketimports.com', '$2y$10$hIIure2C3VBwWCCZjVsj3OZI34LDHN7Be2FfPimQCEsb7kEPh67ue', '081234567890', 'Jl. Contoh No. 1, Jakarta', 'admin'),
('Budi Santoso', 'budi@mail.com', '$2y$10$4ISQDglXjUoE7pSM8iq6JeAfXL6xeYGDKVDAzcg/ryEfL3o64W6xu', '081298765432', 'Jl. Merdeka No. 45, Bandung', 'user');

-- Kategori produk
INSERT INTO categories (name, slug) VALUES
('Hoodie', 'hoodie'),
('Bomber', 'bomber'),
('Parka', 'parka'),
('Denim', 'denim'),
('Blazer', 'blazer');

-- Produk contoh
INSERT INTO products (category_id, name, brand, description, price, stock, image, is_active) VALUES
(1, 'Hoodie Streetwear Oversize', 'Supreme-ish', 'Hoodie premium bahan fleece tebal, cocok untuk gaya kasual dan streetwear. Nyaman dipakai sehari-hari.', 350000, 25, 'products/p1.svg', 1),
(1, 'Hoodie Cotton Lembut', 'Nike Air', 'Hoodie cotton combed 30s yang lembut dan adem. Tersedia berbagai warna.', 299000, 30, 'products/p2.svg', 1),
(2, 'Jaket Bomber Classic', 'Alpha Style', 'Jaket bomber klasik dengan rib knit di lengan dan pinggang. Bahan parasut anti air.', 425000, 18, 'products/p3.svg', 1),
(2, 'Jaket Bomber Satin', 'Jaded', 'Jaket bomber bahan satin mengkilap dengan bordir emblem di dada.', 389000, 15, 'products/p4.svg', 1),
(3, 'Parka Waterproof', 'Columbia Sport', 'Parka waterproof dengan hood besar dan lapisan dalam hangat. Ideal untuk musim hujan.', 499000, 20, 'products/p5.svg', 1),
(3, 'Parka Puffer Tebal', 'The North Pro', 'Parka puffer dengan isolasi tebal, tetap hangat di suhu rendah.', 550000, 12, 'products/p6.svg', 1),
(4, 'Denim Jacket Wash', 'Levi Original', 'Jaket denim dengan efek wash klasik dan jahitan kokoh. Gaya timeless.', 475000, 22, 'products/p7.svg', 1),
(4, 'Denim Overshirt', 'Levi Original', 'Overshirt denim yang bisa dipakai sebagai outer atau atasan. Bahan tebal.', 420000, 17, 'products/p8.svg', 1),
(5, 'Blazer Slim Fit', 'Hugo Boss Original', 'Blazer formal slim fit bahan wool blend, cocok untuk acara resmi dan kantor.', 650000, 10, 'products/p9.svg', 1),
(5, 'Blazer Casual Twill', 'Uniqlo Original', 'Blazer kasual bahan twill stretch yang nyaman, warna netral.', 520000, 14, 'products/p10.svg', 1);

-- Kode promo
INSERT INTO promos (code, name, type, value, min_order, is_active) VALUES
('JAKET10', 'Diskon 10% untuk semua pesanan', 'percent', 10, 0, 1),
('JAKET50', 'Potongan Rp50.000 untuk min. belanja Rp500.000', 'nominal', 50000, 500000, 1),
('JAKET100', 'Potongan Rp100.000 untuk min. belanja Rp1.000.000', 'nominal', 100000, 1000000, 1);
