<?php
ob_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$user = current_user();
$cartTotal = $user ? cart_count($user['id']) : 0;
$unread = $user ? unread_notif_count($user['id']) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . ' | ' : '' ?>Jaket Imports</title>
    <link rel="icon" href="<?= url('images/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: time() ?>">
</head>
<body>

<header class="navbar">
    <div class="container navbar-inner">
        <a class="brand" href="<?= url('index.php') ?>">
            <span class="brand-icon">🧥</span>
            <span>Jaket<em>Imports</em></span>
        </a>

        <nav class="nav-menu">
            <a href="<?= url('index.php') ?>">Beranda</a>
            <a href="<?= url('katalog.php') ?>">Katalog</a>
            <?php if ($user): ?>
                <a class="nav-icon" href="<?= url('keranjang.php') ?>" title="Keranjang">
                    🛒<span class="badge-count"><?= $cartTotal ?></span>
                </a>
                <a class="nav-icon" href="<?= url('notifikasi.php') ?>" title="Notifikasi">
                    🔔<span class="badge-count<?= $unread ? '' : ' hide' ?>" id="notifBadge"><?= $unread ?></span>
                </a>
            <?php endif; ?>
        </nav>

        <div class="nav-actions">
            <?php if ($user): ?>
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="btn btn-sm btn-outline" href="<?= url('admin/index.php') ?>">Dashboard Admin</a>
                <?php endif; ?>
                <div class="dropdown">
                    <button class="btn btn-sm btn-dark dropdown-toggle"><?= e($user['name']) ?> ▾</button>
                    <div class="dropdown-menu">
                        <a href="<?= url('pesanan.php') ?>">Riwayat Pesanan</a>
                        <a href="<?= url('profil.php') ?>">Profil Saya</a>
                        <a href="<?= url('auth/logout.php') ?>">Keluar</a>
                    </div>
                </div>
            <?php else: ?>
                <a class="btn btn-sm btn-outline" href="<?= url('auth/login.php') ?>">Masuk</a>
                <a class="btn btn-sm btn-primary" href="<?= url('auth/register.php') ?>">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="container page">
    <?php foreach (get_flash() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
