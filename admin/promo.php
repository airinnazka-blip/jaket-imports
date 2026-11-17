<?php
require_once __DIR__ . '/../includes/header.php';
require_admin();
$active_menu = 'promo';

$action = $_GET['action'] ?? 'list';
$errors = [];

// ---- Hapus / toggle promo ----
if (isset($_POST['delete_promo'])) {
    csrf_check();
    $pdo->prepare('DELETE FROM promos WHERE id = ?')->execute([(int)$_POST['id']]);
    set_flash('success', 'Promo dihapus.');
    redirect('admin/promo.php');
}
if (isset($_POST['toggle_promo'])) {
    csrf_check();
    $pdo->prepare('UPDATE promos SET is_active = 1 - is_active WHERE id = ?')->execute([(int)$_POST['id']]);
    set_flash('success', 'Status promo diperbarui.');
    redirect('admin/promo.php');
}

// ---- Simpan promo ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_promo'])) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $type = in_array($_POST['type'] ?? '', ['percent', 'nominal']) ? $_POST['type'] : 'percent';
    $value = (float)str_replace([',', ' '], '', $_POST['value'] ?? '0');
    $minOrder = (float)str_replace([',', ' '], '', $_POST['min_order'] ?? '0');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($code === '') $errors[] = 'Kode promo wajib diisi.';
    if ($name === '') $errors[] = 'Nama promo wajib diisi.';
    if ($type === 'percent' && ($value <= 0 || $value > 100)) $errors[] = 'Nilai persen harus antara 1–100.';
    if ($type === 'nominal' && $value <= 0) $errors[] = 'Nilai nominal harus lebih dari 0.';

    if (!$errors && $code !== '') {
        $dup = $pdo->prepare('SELECT id FROM promos WHERE code = ? AND id <> ?');
        $dup->execute([$code, $id]);
        if ($dup->fetch()) {
            $errors[] = 'Kode promo "' . $code . '" sudah dipakai. Gunakan kode lain.';
        }
    }

    if (!$errors) {
        if ($id > 0) {
            $upd = $pdo->prepare('UPDATE promos SET code=?, name=?, type=?, value=?, min_order=?, is_active=? WHERE id=?');
            $upd->execute([$code, $name, $type, $value, $minOrder, $isActive, $id]);
            set_flash('success', 'Promo diperbarui.');
        } else {
            $ins = $pdo->prepare('INSERT INTO promos (code, name, type, value, min_order, is_active) VALUES (?,?,?,?,?,?)');
            $ins->execute([$code, $name, $type, $value, $minOrder, $isActive]);
            set_flash('success', 'Promo baru ditambahkan.');
        }
        redirect('admin/promo.php');
    }
}

$promos = $pdo->query('SELECT * FROM promos ORDER BY id DESC')->fetchAll();

// ---- Form promo ----
if ($action === 'add' || $action === 'edit') {
    $p = ['id' => 0, 'code' => '', 'name' => '', 'type' => 'percent', 'value' => '', 'min_order' => '', 'is_active' => 1];
    if ($action === 'edit') {
        $st = $pdo->prepare('SELECT * FROM promos WHERE id = ?');
        $st->execute([(int)($_GET['id'] ?? 0)]);
        $p = $st->fetch();
        if (!$p) { set_flash('danger', 'Promo tidak ditemukan.'); redirect('admin/promo.php'); }
    }
    foreach ($p as $k => $v) if (isset($_POST[$k])) $p[$k] = $_POST[$k];
    ?>
    <div class="admin-layout">
        <?php require __DIR__ . '/_sidebar.php'; ?>
        <div>
            <div class="section-head" style="margin-top:0">
                <h2><?= $action === 'add' ? '➕ Tambah Promo' : '✏️ Edit Promo' ?></h2>
                <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/promo.php') ?>">← Kembali</a>
            </div>
            <?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
            <div class="card" style="max-width:560px">
                <div class="card-body">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_promo" value="1">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <div class="form-group">
                            <label for="code">Kode Promo</label>
                            <input class="form-control" id="code" name="code" value="<?= e($p['code']) ?>" placeholder="Contoh: JAKET10" required>
                        </div>
                        <div class="form-group">
                            <label for="name">Nama Promo</label>
                            <input class="form-control" id="name" name="name" value="<?= e($p['name']) ?>" required>
                        </div>
                        <div class="flex wrap">
                            <div class="form-group" style="flex:1;min-width:160px">
                                <label for="type">Jenis Diskon</label>
                                <select class="form-control" id="type" name="type">
                                    <option value="percent" <?= $p['type'] === 'percent' ? 'selected' : '' ?>>Persen (%)</option>
                                    <option value="nominal" <?= $p['type'] === 'nominal' ? 'selected' : '' ?>>Nominal (Rp)</option>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;min-width:160px">
                                <label for="value">Nilai Diskon</label>
                                <input class="form-control" id="value" name="value" type="number" min="0" value="<?= e($p['value']) ?>" required>
                            </div>
                            <div class="form-group" style="flex:1;min-width:160px">
                                <label for="min_order">Min. Belanja (Rp)</label>
                                <input class="form-control" id="min_order" name="min_order" type="number" min="0" value="<?= e($p['min_order']) ?>">
                            </div>
                        </div>
                        <label class="form-inline" style="margin-bottom:18px">
                            <input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>>
                            <span>Aktif</span>
                        </label>
                        <button class="btn btn-primary" type="submit">Simpan Promo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}
?>
<div class="admin-layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div>
        <div class="section-head" style="margin-top:0">
            <h2>🎁 Kelola Promo & Diskon</h2>
            <a class="btn btn-primary" href="<?= url('admin/promo.php?action=add') ?>">+ Tambah Promo</a>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Nilai</th><th>Min. Belanja</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if (!$promos): ?>
                        <tr><td colspan="7" class="text-center text-muted">Belum ada promo.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($promos as $p): ?>
                        <tr>
                            <td><strong><?= e($p['code']) ?></strong></td>
                            <td><?= e($p['name']) ?></td>
                            <td><?= $p['type'] === 'percent' ? 'Persen' : 'Nominal' ?></td>
                            <td><?= $p['type'] === 'percent' ? round((float)$p['value']) . '%' : rupiah($p['value']) ?></td>
                            <td><?= (float)$p['min_order'] > 0 ? rupiah($p['min_order']) : 'Tanpa syarat' ?></td>
                            <td><?= $p['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
                            <td>
                                <div class="flex">
                                    <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/promo.php?action=edit&id=' . $p['id']) ?>">Edit</a>
                                    <form method="post"><?= csrf_field() ?>
                                        <input type="hidden" name="toggle_promo" value="1"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button class="btn btn-sm btn-outline-dark" type="submit"><?= $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                    </form>
                                    <form method="post"><?= csrf_field() ?>
                                        <input type="hidden" name="delete_promo" value="1"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button class="btn btn-sm btn-danger" type="submit" data-confirm="Hapus promo ini?">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
