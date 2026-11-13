<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) redirect('index.php');

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old = [
        'name'    => trim($_POST['name'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'phone'   => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirmation'] ?? '';

    if ($old['name'] === '') $errors[] = 'Nama wajib diisi.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if ($password !== $confirm) $errors[] = 'Konfirmasi password tidak cocok.';

    if (!$errors) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $errors[] = 'Email sudah terdaftar, silakan masuk.';
        }
    }

    if (!$errors) {
        $st = $pdo->prepare('INSERT INTO users (name, email, password, phone, address) VALUES (?,?,?,?,?)');
        $st->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_BCRYPT), $old['phone'], $old['address']]);

        $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        $row = $st->fetch();

        session_regenerate_id(true);
        $_SESSION['user'] = $row;

        $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $notif->execute([$row['id'], 'Selamat datang di Jaket Imports!', 'Akun Anda berhasil dibuat. Selamat berbelanja jaket import original.']);

        set_flash('success', 'Pendaftaran berhasil! Selamat datang, ' . $row['name'] . '.');
        redirect('index.php');
    }
}

$page_title = 'Daftar';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Jaket Imports</title>
    <link rel="icon" href="<?= url('images/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-logo">🧥</div>
    <h1 class="auth-title">Buat Akun</h1>
    <p class="auth-sub">Daftar untuk mulai belanja jaket import original.</p>

    <div class="card">
        <?php foreach ($errors as $er): ?>
            <div class="alert alert-danger"><?= e($er) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Nama Lengkap</label>
                <input class="form-control" id="name" name="name" value="<?= e($old['name']) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">No. HP</label>
                <input class="form-control" id="phone" name="phone" value="<?= e($old['phone']) ?>">
            </div>
            <div class="form-group">
                <label for="address">Alamat Pengiriman</label>
                <textarea class="form-control" id="address" name="address"><?= e($old['address']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required>
                <p class="form-hint">Minimal 6 karakter.</p>
            </div>
            <div class="form-group">
                <label for="password_confirmation">Ulangi Password</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Daftar</button>
        </form>

        <p class="text-center mt-3 text-muted">Sudah punya akun?
            <a href="<?= url('auth/login.php') ?>">Masuk</a></p>
    </div>
</div>
</body>
</html>
