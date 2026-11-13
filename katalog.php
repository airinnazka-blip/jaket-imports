<?php
require_once __DIR__ . '/includes/header.php';

$search = trim($_GET['q'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';
$categories = all_categories();

$where = ['p.is_active = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.description LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($cat > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat;
}

$order = match ($sort) {
    'cheapest' => 'p.price ASC',
    'expensive' => 'p.price DESC',
    'name' => 'p.name ASC',
    default => 'p.id DESC',
};

$sql = "SELECT p.*, c.name AS category_name FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE " . implode(' AND ', $where) . " ORDER BY $order";
$st = $pdo->prepare($sql);
$st->execute($params);
$products = $st->fetchAll();

$qs = function ($extra = []) use ($search, $cat, $sort) {
    $q = array_merge(['q' => $search, 'cat' => $cat, 'sort' => $sort], $extra);
    return http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== 0));
};
?>
<div class="section-head">
    <h2>Katalog Produk</h2>
    <span class="text-muted"><?= count($products) ?> produk ditemukan</span>
</div>

<form class="card filter-bar" method="get" action="">
    <input class="form-control search" type="text" name="q" placeholder="Cari jaket, brand, atau kategori..." value="<?= e($search) ?>">
    <select class="form-control" name="cat" data-auto-filter>
        <option value="0">Semua Kategori</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control" name="sort" data-auto-filter>
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
        <option value="cheapest" <?= $sort === 'cheapest' ? 'selected' : '' ?>>Harga Terendah</option>
        <option value="expensive" <?= $sort === 'expensive' ? 'selected' : '' ?>>Harga Tertinggi</option>
        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nama A–Z</option>
    </select>
    <button class="btn btn-dark" type="submit">Cari</button>
    <?php if ($search !== '' || $cat > 0 || $sort !== 'newest'): ?>
        <a class="btn btn-outline-dark" href="<?= url('katalog.php') ?>">Reset</a>
    <?php endif; ?>
</form>

<?php if (!$products): ?>
    <div class="card empty">
        <div class="big">🔍</div>
        <h3>Tidak ada produk ditemukan</h3>
        <p>Coba ubah kata kunci atau filter lainnya.</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($products as $p): ?>
            <div class="product-card">
                <a class="thumb" href="<?= url('produk.php?id=' . $p['id']) ?>">
                    <img src="<?= e(product_image($p)) ?>" alt="<?= e($p['name']) ?>">
                </a>
                <div class="body">
                    <span class="cat"><?= e($p['category_name'] ?? 'Umum') ?></span>
                    <h3><?= e($p['name']) ?></h3>
                    <span class="brand"><?= e($p['brand']) ?></span>
                    <span class="price"><?= rupiah($p['price']) ?></span>
                    <?php
                    $avg = product_rating_avg($p['id']);
                    if ($avg[1] > 0):
                    ?>
                        <span class="stars">★ <?= number_format((float)$avg[0], 1, ',', '.') ?> (<?= (int)$avg[1] ?>)</span>
                    <?php endif; ?>
                    <div class="foot">
                        <?php if ($p['stock'] > 0): ?>
                            <form method="post" action="<?= url('keranjang.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="qty" value="1">
                                <button class="btn btn-sm btn-primary" type="submit">+ Keranjang</button>
                            </form>
                            <a class="btn btn-sm btn-info" href="<?= url('produk.php?id=' . $p['id']) ?>">Detail</a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-dark" disabled>Stok Habis</button>
                            <a class="btn btn-sm btn-info" href="<?= url('produk.php?id=' . $p['id']) ?>">Detail</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
