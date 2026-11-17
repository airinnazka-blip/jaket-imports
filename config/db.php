<?php
// ============================================================
// Konfigurasi Database & Base URL
// Default koneksi Laragon/XAMPP: user root, tanpa password
// ============================================================

date_default_timezone_set('Asia/Jakarta');

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'jaket_imports');
define('DB_USER', 'root');
define('DB_PASS', '');

// Auto-detect BASE_URL (jalankan di subfolder htdocs/www ataupun root vhost)
function detect_base_url() {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']) : '';
    $projectDir = str_replace('\\', '/', dirname(__DIR__));
    $relative = '';
    if ($docRoot !== '' && strpos($projectDir, $docRoot) === 0) {
        $relative = substr($projectDir, strlen($docRoot));
    }
    return rtrim($relative, '/');
}
define('BASE_URL', detect_base_url());

// Koneksi PDO
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:30px;max-width:600px;margin:40px auto;border:1px solid #ddd;border-radius:8px">
        <h2 style="margin-top:0">Koneksi database gagal</h2>
        <p>Pastikan MySQL/Laragon sudah berjalan dan database <b>' . DB_NAME . '</b> sudah dibuat dari <code>database/schema.sql</code>.</p>
        <p style="color:#b00"><b>Pesan error:</b> ' . htmlspecialchars($e->getMessage()) . '</p>
        </div>');
}
