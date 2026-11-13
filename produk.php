<?php
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare(
    "SELECT p.*, c.name AS category_name, c.id AS category_id FROM products p
     LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?"
);
$st->execute([$id]);
$product = $st->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan.');
    redirect('katalog.php');
}

$page_title = $product['name'];

$reviews = $pdo->prepare(
    "SELECT r.*, u.name AS user_name FROM reviews r
     JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.id DESC"
);
$reviews->execute([$id]);
$reviews = $reviews->fetchAll();

$avg = product_rating_avg($id);
$avgRating = $avg[0] ?: 0;
$reviewCount = (int)$avg[1];

$similar = $pdo->prepare(
    "SELECT p.* FROM products p WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1 ORDER BY p.id DESC LIMIT 4"
);
$similar->execute([$product['category_id'], $id]);
$similar = $similar->fetchAll();

$user = current_user();
$alreadyReviewed = false;
if ($user) {
    $chk = $pdo->prepare('SELECT id FROM reviews WHERE user_id = ? AND product_id = ?');
    $chk->execute([$user['id'], $id]);
    $alreadyReviewed = (bool)$chk->fetch();
}

// Proses ulasan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    require_login();
    csrf_check();
    $rating = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) $rating = 5;

    if ($alreadyReviewed) {
        set_flash('warning', 'Anda sudah memberikan ulasan untuk produk ini.');
    } else {
        $ins = $pdo->prepare('INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?)');
        $ins->execute([$user['id'], $id, $rating, $comment]);
        set_flash('success', 'Terima kasih! Ulasan Anda berhasil dikirim.');
    }
    redirect('produk.php?id=' . $id);
}
?>
<div class="mb-2">
    <a class="text-muted" href="<?= url('katalog.php') ?>">← Kembali ke katalog</a>
</div>

<div class="detail-grid">
    <div class="detail-img">
        <img src="<?= e(product_image($product)) ?>" alt="<?= e($product['name']) ?>">
    </div>
    <div class="detail-info">
        <span class="cat" style="color:var(--accent-dark);font-weight:700;text-transform:uppercase;letter-spacing:.6px"><?= e($product['category_name'] ?? 'Umum') ?></span>
        <h1><?= e($product['name']) ?></h1>
        <div class="meta">Brand: <?= e($product['brand']) ?> &nbsp;•&nbsp;
            <span class="stars" style="color:var(--accent)">★ <?= number_format((float)$avgRating, 1, ',', '.') ?></span>
            (<?= $reviewCount ?> ulasan)
        </div>
        <div class="price-big"><?= rupiah($product['price']) ?></div>

        <p class="stock-label">
            <?php if ($product['stock'] > 10): ?>
                <span class="stock-ok">Stok tersedia (<?= (int)$product['stock'] ?> pcs)</span>
            <?php elseif ($product['stock'] > 0): ?>
                <span class="stock-low">Hampir habis! Sisa <?= (int)$product['stock'] ?> pcs</span>
            <?php else: ?>
                <span class="stock-out">Stok habis</span>
            <?php endif; ?>
        </p>

        <div class="detail-desc"><?= nl2br(e($product['description'])) ?></div>

        <?php if ($product['stock'] > 0): ?>
            <form method="post" action="<?= url('keranjang.php') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <div class="qty-box">
                    <button type="button" class="qty-btn minus">−</button>
                    <input class="qty-input" name="qty" value="1" max="<?= (int)$product['stock'] ?>" inputmode="numeric">
                    <button type="button" class="qty-btn plus">+</button>
                    <button class="btn btn-primary btn-lg" type="submit">🛒 Tambah ke Keranjang</button>
                </div>
            </form>
        <?php else: ?>
            <button class="btn btn-lg btn-outline-dark" disabled>Stok Habis</button>
        <?php endif; ?>

        <div class="mt-3">
            <span class="badge badge-success">✓ Pengiriman aman</span>
            <span class="badge badge-info">✓ Garansi original</span>
            <span class="badge badge-primary">✓ Bisa dikembalikan</span>
        </div>
    </div>
</div>

<div class="section-head">
    <h2>📝 Rating & Ulasan (<?= $reviewCount ?>)</h2>
</div>

<div class="panel-grid">
    <div class="card">
        <div class="card-body">
            <?php if ($user && !$alreadyReviewed && !$product['stock'] == 0): ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="submit_review" value="1">
                    <div class="form-group">
                        <label>Rating Anda</label>
                        <div class="stars-input">
                            <input type="radio" id="s1" name="rating" value="1"><label for="s1">★</label>
                            <input type="radio" id="s2" name="rating" value="2"><label for="s2">★</label>
                            <input type="radio" id="s3" name="rating" value="3"><label for="s3">★</label>
                            <input type="radio" id="s4" name="rating" value="4"><label for="s4">★</label>
                            <input type="radio" id="s5" name="rating" value="5" checked><label for="s5">★</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="comment">Komentar</label>
                        <textarea class="form-control" id="comment" name="comment" placeholder="Bagaimana pendapat Anda tentang produk ini?"></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit">Kirim Ulasan</button>
                </form>
                <hr style="border:none;border-top:1px solid var(--border);margin:22px 0">
            <?php elseif (!$user): ?>
                <p class="text-muted">Silakan <a href="<?= url('auth/login.php') ?>">masuk</a> untuk menulis ulasan.</p>
                <hr style="border:none;border-top:1px solid var(--border);margin:18px 0">
            <?php elseif ($alreadyReviewed): ?>
                <p class="text-muted">Anda sudah memberikan ulasan untuk produk ini. Terima kasih!</p>
                <hr style="border:none;border-top:1px solid var(--border);margin:18px 0">
            <?php endif; ?>

            <?php if (!$reviews): ?>
                <p class="text-muted">Belum ada ulasan. Jadilah yang pertama memberi ulasan!</p>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="review-item">
                        <div class="review-head">
                            <div class="avatar"><?= e(strtoupper(substr($r['user_name'], 0, 1))) ?></div>
                            <div>
                                <strong><?= e($r['user_name']) ?></strong>
                                <div class="stars"><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5 - (int)$r['rating']) ?></div>
                            </div>
                            <span class="time" style="margin-left:auto"><?= tgl_id($r['created_at']) ?></span>
                        </div>
                        <?php if ($r['comment']): ?>
                            <p><?= nl2br(e($r['comment'])) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card summary-box">
        <div class="card-body text-center">
            <div style="font-size:2.4rem;font-weight:800"><?= number_format((float)$avgRating, 1, ',', '.') ?></div>
            <div class="stars" style="color:var(--accent);font-size:1.3rem"><?= str_repeat('★', (int)round((float)$avgRating)) ?><?= str_repeat('☆', 5 - (int)round((float)$avgRating)) ?></div>
            <p class="text-muted mt-1"><?= $reviewCount ?> ulasan</p>
        </div>
    </div>
</div>

<?php if ($similar): ?>
    <div class="section-head">
        <h2>Produk Serupa</h2>
    </div>
    <div class="grid">
        <?php foreach ($similar as $p): ?>
            <div class="product-card">
                <a class="thumb" href="<?= url('produk.php?id=' . $p['id']) ?>">
                    <img src="<?= e(product_image($p)) ?>" alt="<?= e($p['name']) ?>">
                </a>
                <div class="body">
                    <h3><?= e($p['name']) ?></h3>
                    <span class="price"><?= rupiah($p['price']) ?></span>
                    <div class="foot">
                        <a class="btn btn-sm btn-info" href="<?= url('produk.php?id=' . $p['id']) ?>">Detail</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
