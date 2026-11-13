<?php
require_once __DIR__ . '/includes/header.php';

$latest = $pdo->query(
    "SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE p.is_active = 1 ORDER BY p.id DESC LIMIT 8"
)->fetchAll();

$promos = $pdo->query("SELECT * FROM promos WHERE is_active = 1 ORDER BY id")->fetchAll();
$catCount = $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$prodCount = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn();
?>
<div class="hero">
    <span class="hero-deco">🧥</span>
    <p class="text-muted" style="color:var(--accent);font-weight:700;letter-spacing:1px">APLIKASI JAKET IMPORTS</p>
    <h1>Belanja Jaket Import Original<br>Lebih <em>Mudah</em>, <em>Cepat</em> & <em>Terpercaya</em></h1>
    <p>Temukan koleksi hoodie, bomber, parka, denim, dan blazer import dengan harga terbaik. Katalog lengkap, pembayaran aman, dan tracking pesanan real-time.</p>
    <div class="flex wrap">
        <a class="btn btn-primary btn-lg" href="<?= url('katalog.php') ?>">Lihat Katalog</a>
        <a class="btn btn-outline btn-lg" href="<?= url('katalog.php?cat=' . $pdo->query('SELECT MIN(id) FROM categories')->fetchColumn()) ?>">Koleksi Terbaru</a>
    </div>
</div>

<div class="section-head">
    <h2>🔥 Produk Terbaru</h2>
    <a class="text-muted" href="<?= url('katalog.php') ?>">Lihat semua →</a>
</div>

<div class="grid">
    <?php foreach ($latest as $p): ?>
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

<div class="section-head">
    <h2>🎁 Promo & Kode Diskon</h2>
</div>
<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
    <?php foreach ($promos as $pr): ?>
        <div class="card" style="padding:20px;border-left:5px solid var(--accent)">
            <div class="flex-between">
                <h3 class="card-title"><?= e($pr['name']) ?></h3>
                <span class="badge badge-warning"><?= e($pr['type'] === 'percent' ? round((float)$pr['value']) . '% OFF' : 'Rp' . number_format((float)$pr['value'], 0, ',', '.') . ' OFF') ?></span>
            </div>
            <?php if ($pr['min_order'] > 0): ?>
                <p class="card-subtitle">Min. belanja <?= rupiah($pr['min_order']) ?></p>
            <?php else: ?>
                <p class="card-subtitle">Tanpa syarat minimal belanja</p>
            <?php endif; ?>
            <p class="mt-2">Kode: <b style="background:var(--dark);color:#fff;padding:4px 12px;border-radius:6px;letter-spacing:1px"><?= e($pr['code']) ?></b></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="card mt-4" style="padding:26px">
    <div class="flex-between wrap">
        <div>
            <h3 class="card-title">Informasi Toko</h3>
            <p class="card-subtitle"><?= $catCount ?> kategori • <?= $prodCount ?> produk import original terkurasi</p>
        </div>
        <div class="flex">
            <span class="badge badge-success">✓ Pembayaran Aman</span>
            <span class="badge badge-info">✓ Tracking Real-time</span>
            <span class="badge badge-primary">✓ Membership & Promo</span>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
