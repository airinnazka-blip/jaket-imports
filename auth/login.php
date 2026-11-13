<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_user()) redirect('index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email dan password wajib diisi.';
    }

    if (!$errors) {
        $st = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();

        if ($row && password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = $row;
            set_flash('success', 'Selamat datang kembali, ' . $row['name'] . '!');
            redirect($row['role'] === 'admin' ? 'admin/index.php' : 'index.php');
        }
        $errors[] = 'Email atau password salah.';
    }
}

$page_title = 'Masuk';
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
    <h1 class="auth-title">Jaket <em style="color:var(--accent-dark)">Imports</em></h1>
    <p class="auth-sub">Belanja jaket import original, mudah & terpercaya.</p>

    <div class="card">
        <?php foreach ($errors as $er): ?>
            <div class="alert alert-danger"><?= e($er) ?></div>
        <?php endforeach; ?>
        <?php foreach (get_flash() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" action="">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email"
                       value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input class="form-control" type="password" id="password" name="password" required>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Masuk</button>
        </form>

        <p class="text-center mt-3 text-muted">Belum punya akun?
            <a href="<?= url('auth/register.php') ?>">Daftar sekarang</a></p>
    </div>

    <p class="text-center mt-3 text-muted" style="font-size:.85rem">
        Akun demo: <b>admin@jaketimports.com</b> / admin123 &nbsp;•&nbsp;
        <b>budi@mail.com</b> / user123
    </p>
</div>
</body>
</html>
