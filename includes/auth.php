<?php
// ============================================================
// Sesi & proteksi akses
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function require_login() {
    if (!current_user()) {
        set_flash('warning', 'Silakan login terlebih dahulu.');
        redirect('auth/login.php');
    }
}

function require_admin() {
    require_login();
    if (current_user()['role'] !== 'admin') {
        set_flash('danger', 'Anda tidak punya akses ke halaman admin.');
        redirect('index.php');
    }
}
