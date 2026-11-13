<?php
require_once __DIR__ . '/../includes/header.php';
require_admin();
$active_menu = 'produk';

$action = $_GET['action'] ?? 'list';
$errors = [];

// ---- Hapus produk ----
if (isset($_POST['delete_product'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $del = $pdo->prepare('DELETE FROM products WHERE id = ?');
    $del->execute([$id]);
    set_flash('success', 'Produk berhasil dihapus.');
    redirect('admin/produk.php');
}

// ---- Toggle aktif ----
if (isset($_POST['toggle_product'])) {
    csrf_check();
    $id = (int)$_POST['id'];
    $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    set_flash('success', 'Status produk diperbarui.');
    redirect('admin/produk.php');
}

// ---- Simpan produk (tambah / ubah) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    csrf_check();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
    $price = (float)str_replace([',', ' '], '', $_POST['price'] ?? '0');
    $stock = (int)($_POST['stock'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $image = $_POST['existing_image'] ?? null;

    if ($name === '') $errors[] = 'Nama produk wajib diisi.';
    if ($price < 0) $errors[] = 'Harga tidak valid.';

    // Upload gambar baru (opsional)
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
        if (!isset($allowed[$mime])) {
            $errors[] = 'Format gambar tidak didukung (JPG, PNG, WEBP, GIF, SVG).';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran gambar maksimal 2MB.';
        } else {
            $fname = 'products/p_' . time() . '_' . rand(100, 999) . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['image']['tmp_name'], __DIR__ . '/../images/' . $fname);
            $image = $fname;
        }
    }

    if (!$errors) {
        if ($id > 0) {
            $upd = $pdo->prepare('UPDATE products SET category_id=?, name=?, brand=?, description=?, price=?, stock=?, image=?, is_active=? WHERE id=?');
            $upd->execute([$categoryId, $name, $brand, $desc, $price, $stock, $image, $isActive, $id]);
            set_flash('success', 'Produk berhasil diperbarui.');
        } else {
            $ins = $pdo->prepare('INSERT INTO products (category_id, name, brand, description, price, stock, image, is_active) VALUES (?,?,?,?,?,?,?,?)');
            $ins->execute([$categoryId, $name, $brand, $desc, $price, $stock, $image, $isActive]);
            set_flash('success', 'Produk baru berhasil ditambahkan.');
        }
        redirect('admin/produk.php');
    }
}

$categories = all_categories();

// ---- Mode form (tambah/ubah) ----
if ($action === 'add' || $action === 'edit') {
    $product = [
        'id' => 0, 'name' => '', 'brand' => '', 'category_id' => null,
        'price' => '', 'stock' => '', 'description' => '', 'image' => null, 'is_active' => 1,
    ];
    if ($action === 'edit') {
        $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([(int)($_GET['id'] ?? 0)]);
        $product = $st->fetch();
        if (!$product) { set_flash('danger', 'Produk tidak ditemukan.'); redirect('admin/produk.php'); }
    }
    // isi ulang dari POST jika ada error
    foreach ($product as $k => $v) {
        if (isset($_POST[$k])) $product[$k] = $_POST[$k];
    }
    ?>
    <div class="admin-layout">
        <?php require __DIR__ . '/_sidebar.php'; ?>
        <div>
            <div class="section-head" style="margin-top:0">
                <h2><?= $action === 'add' ? '➕ Tambah Produk' : '✏️ Edit Produk' ?></h2>
                <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/produk.php') ?>">← Kembali</a>
            </div>

            <?php foreach ($errors as $er): ?>
                <div class="alert alert-danger"><?= e($er) ?></div>
            <?php endforeach; ?>

            <div class="card">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="save_product" value="1">
                        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                        <input type="hidden" name="existing_image" value="<?= e($product['image'] ?? '') ?>">

                        <div class="form-group">
                            <label for="name">Nama Produk</label>
                            <input class="form-control" id="name" name="name" value="<?= e($product['name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="brand">Brand</label>
                            <input class="form-control" id="brand" name="brand" value="<?= e($product['brand']) ?>">
                        </div>

                        <div class="flex wrap">
                            <div class="form-group" style="flex:1;min-width:200px">
                                <label for="category_id">Kategori</label>
                                <select class="form-control" id="category_id" name="category_id">
                                    <option value="">— Pilih Kategori —</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= (int)$product['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;min-width:160px">
                                <label for="price">Harga (Rp)</label>
                                <input class="form-control" id="price" name="price" type="number" min="0" step="1000" value="<?= e($product['price']) ?>" required>
                            </div>
                            <div class="form-group" style="flex:1;min-width:120px">
                                <label for="stock">Stok</label>
                                <input class="form-control" id="stock" name="stock" type="number" min="0" value="<?= e($product['stock']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description"><?= e($product['description']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Gambar Produk</label>
                            <?php if ($product['image']): ?>
                                <div class="mb-1">
                                    <img src="<?= e(product_image($product)) ?>" class="thumbnail" style="width:120px;height:90px" alt="">
                                    <span class="text-muted" style="font-size:.8rem"><?= e($product['image']) ?></span>
                                </div>
                            <?php endif; ?>
                            <input class="form-control" type="file" name="image" accept="image/*">
                            <p class="form-hint">Kosongkan untuk memakai gambar lama / gambar default.</p>
                        </div>

                        <label class="form-inline" style="margin-bottom:18px">
                            <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
                            <span>Tampilkan di toko (aktif)</span>
                        </label>

                        <button class="btn btn-primary" type="submit">Simpan Produk</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Daftar produk ----
$products = $pdo->query(
    'SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC'
)->fetchAll();
?>
<div class="admin-layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div>
        <div class="section-head" style="margin-top:0">
            <h2>🧥 Kelola Produk</h2>
            <a class="btn btn-primary" href="<?= url('admin/produk.php?action=add') ?>">+ Tambah Produk</a>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Gambar</th><th>Nama</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><img class="thumbnail" src="<?= e(product_image($p)) ?>" alt=""></td>
                            <td><strong><?= e($p['name']) ?></strong><br><span class="text-muted" style="font-size:.8rem"><?= e($p['brand']) ?></span></td>
                            <td><?= e($p['category_name'] ?? '—') ?></td>
                            <td class="num"><?= rupiah($p['price']) ?></td>
                            <td class="num">
                                <?= (int)$p['stock'] ?>
                                <?php if ((int)$p['stock'] <= 5 && (int)$p['stock'] > 0): ?><span class="text-danger">(menipis)</span><?php endif; ?>
                                <?php if ((int)$p['stock'] === 0): ?><span class="text-danger">(habis)</span><?php endif; ?>
                            </td>
                            <td><?= $p['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
                            <td>
                                <div class="flex">
                                    <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/produk.php?action=edit&id=' . $p['id']) ?>">Edit</a>
                                    <form method="post"><?= csrf_field() ?>
                                        <input type="hidden" name="toggle_product" value="1">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button class="btn btn-sm btn-outline-dark" type="submit"><?= $p['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                    </form>
                                    <form method="post"><?= csrf_field() ?>
                                        <input type="hidden" name="delete_product" value="1">
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button class="btn btn-sm btn-danger" type="submit" data-confirm="Hapus produk ini?">Hapus</button>
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
