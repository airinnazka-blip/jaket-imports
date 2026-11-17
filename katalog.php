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

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$countSt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE " . implode(' AND ', $where));
$countSt->execute($params);
$totalProducts = (int)$countSt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = "SELECT p.*, c.name AS category_name,
               (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) AS avg_rating,
               (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) AS rating_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE " . implode(' AND ', $where) . " ORDER BY $order
        LIMIT $perPage OFFSET $offset";
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
    <span class="text-muted"><?= $totalProducts ?> produk ditemukan</span>
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
                    <?php if ((int)$p['rating_count'] > 0): ?>
                        <span class="stars">★ <?= number_format((float)$p['avg_rating'], 1, ',', '.') ?> (<?= (int)$p['rating_count'] ?>)</span>
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

    <?php if ($totalPages > 1): ?>
        <div class="flex wrap mt-3" style="justify-content:center;gap:8px">
            <?php if ($page > 1): ?>
                <a class="btn btn-sm btn-outline-dark" href="<?= url('katalog.php?' . $qs(['page' => $page - 1])) ?>">← Sebelumnya</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="btn btn-sm <?= $i === $page ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= url('katalog.php?' . $qs(['page' => $i])) ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a class="btn btn-sm btn-outline-dark" href="<?= url('katalog.php?' . $qs(['page' => $page + 1])) ?>">Berikutnya →</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
