<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$user = current_user();
$errors = [];

// Update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Nama wajib diisi.';

    if (!$errors) {
        $upd = $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?');
        $upd->execute([$name, $phone, $address, $user['id']]);
        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$user['id']]);
        $_SESSION['user'] = $st->fetch();
        $user = $_SESSION['user'];
        set_flash('success', 'Profil berhasil diperbarui.');
        redirect('profil.php');
    }
}

// Ganti password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    csrf_check();
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirmation'] ?? '';

    if (!password_verify($current, $user['password'])) {
        $errors[] = 'Password saat ini salah.';
    }
    if (strlen($new) < 6) $errors[] = 'Password baru minimal 6 karakter.';
    if ($new !== $confirm) $errors[] = 'Konfirmasi password baru tidak cocok.';

    if (!$errors) {
        $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $upd->execute([password_hash($new, PASSWORD_BCRYPT), $user['id']]);
        set_flash('success', 'Password berhasil diganti.');
        redirect('profil.php');
    }
}
?>
<div class="section-head">
    <h2>👤 Profil Saya</h2>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-danger"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="panel-grid">
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Data Diri</h3>
                <div class="form-group mt-2">
                    <label for="name">Nama Lengkap</label>
                    <input class="form-control" id="name" name="name" value="<?= e($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input class="form-control" id="email" type="email" value="<?= e($user['email']) ?>" disabled>
                    <p class="form-hint">Email tidak dapat diubah.</p>
                </div>
                <div class="form-group">
                    <label for="phone">No. HP</label>
                    <input class="form-control" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="address">Alamat Pengiriman</label>
                    <textarea class="form-control" id="address" name="address"><?= e($user['address'] ?? '') ?></textarea>
                </div>
                <button class="btn btn-primary" type="submit" name="update_profile" value="1">Simpan Profil</button>
            </div>
        </div>
    </form>

    <form method="post" class="stack">
        <?= csrf_field() ?>
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Ganti Password</h3>
                <div class="form-group mt-2">
                    <label for="current_password">Password Saat Ini</label>
                    <input class="form-control" type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Password Baru</label>
                    <input class="form-control" type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password_confirmation">Ulangi Password Baru</label>
                    <input class="form-control" type="password" id="new_password_confirmation" name="new_password_confirmation" required>
                </div>
                <button class="btn btn-dark" type="submit" name="change_password" value="1">Ganti Password</button>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Statistik Akun</h3>
                <?php
                $c1 = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
                $c1->execute([$user['id']]);
                $c2 = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = "delivered"');
                $c2->execute([$user['id']]);
                $c3 = $pdo->prepare('SELECT COUNT(*) FROM reviews WHERE user_id = ?');
                $c3->execute([$user['id']]);
                ?>
                <div class="summary-row"><span>Total Pesanan</span><span><?= (int)$c1->fetchColumn() ?></span></div>
                <div class="summary-row"><span>Pesanan Selesai</span><span><?= (int)$c2->fetchColumn() ?></span></div>
                <div class="summary-row"><span>Ulasan Dikirim</span><span><?= (int)$c3->fetchColumn() ?></span></div>
            </div>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
