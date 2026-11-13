<?php
require_once __DIR__ . '/includes/header.php';

$user = current_user();

// ---- Tambah ke keranjang ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    if (!$user) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk berbelanja.');
        redirect('auth/login.php');
    }
    csrf_check();
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['qty'] ?? 1));

    $st = $pdo->prepare('SELECT stock FROM products WHERE id = ? AND is_active = 1');
    $st->execute([$pid]);
    $prod = $st->fetch();

    if (!$prod) {
        set_flash('danger', 'Produk tidak ditemukan.');
        redirect('katalog.php');
    }

    $st = $pdo->prepare('SELECT id, qty FROM cart_items WHERE user_id = ? AND product_id = ?');
    $st->execute([$user['id'], $pid]);
    $cartRow = $st->fetch();

    if ($cartRow) {
        $newQty = min($prod['stock'], $cartRow['qty'] + $qty);
        $up = $pdo->prepare('UPDATE cart_items SET qty = ? WHERE id = ?');
        $up->execute([$newQty, $cartRow['id']]);
    } else {
        $ins = $pdo->prepare('INSERT INTO cart_items (user_id, product_id, qty) VALUES (?,?,?)');
        $ins->execute([$user['id'], $pid, min($prod['stock'], $qty)]);
    }
    set_flash('success', 'Produk berhasil ditambahkan ke keranjang.');
    redirect('keranjang.php');
}

// ---- Update / hapus item ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'])) {
    if (!$user) { redirect('auth/login.php'); }
    csrf_check();
    $cartId = (int)$_POST['cart_id'];
    $action = $_POST['action'] ?? 'update';

    if ($action === 'remove') {
        $del = $pdo->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?');
        $del->execute([$cartId, $user['id']]);
        set_flash('success', 'Item dihapus dari keranjang.');
    } else {
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $st = $pdo->prepare(
            'UPDATE cart_items ci JOIN products p ON p.id = ci.product_id
             SET ci.qty = LEAST(?, p.stock) WHERE ci.id = ? AND ci.user_id = ?'
        );
        $st->execute([$qty, $cartId, $user['id']]);
        set_flash('success', 'Keranjang diperbarui.');
    }
    redirect('keranjang.php');
}

// ---- Tampilkan keranjang ----
if (!$user) {
    set_flash('info', 'Silakan login untuk melihat keranjang Anda.');
    redirect('auth/login.php');
}

$st = $pdo->prepare(
    "SELECT ci.id AS cart_id, ci.qty, p.*, c.name AS category_name FROM cart_items ci
     JOIN products p ON p.id = ci.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE ci.user_id = ? ORDER BY ci.id DESC"
);
$st->execute([$user['id']]);
$items = $st->fetchAll();

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += $it['price'] * $it['qty'];
}
?>
<div class="section-head">
    <h2>🛒 Keranjang Belanja</h2>
</div>

<?php if (!$items): ?>
    <div class="card empty">
        <div class="big">🛒</div>
        <h3>Keranjang Anda kosong</h3>
        <p>Yuk temukan jaket import favoritmu di katalog.</p>
        <a class="btn btn-primary mt-2" href="<?= url('katalog.php') ?>">Mulai Belanja</a>
    </div>
<?php else: ?>
    <div class="panel-grid">
        <div class="stack">
            <?php foreach ($items as $it): ?>
                <div class="card cart-item">
                    <a href="<?= url('produk.php?id=' . $it['id']) ?>">
                        <img src="<?= e(product_image($it)) ?>" alt="<?= e($it['name']) ?>">
                    </a>
                    <div class="info">
                        <h4><?= e($it['name']) ?></h4>
                        <div class="price"><?= rupiah($it['price']) ?> / pcs &nbsp;•&nbsp; <?= e($it['category_name'] ?? '') ?></div>
                        <div class="qty-box mt-1">
                            <form method="post" class="flex">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cart_id" value="<?= $it['cart_id'] ?>">
                                <input type="hidden" name="action" value="update">
                                <input class="qty-input" name="qty" value="<?= $it['qty'] ?>" max="<?= (int)$it['stock'] ?>" data-auto-submit>
                            </form>
                        </div>
                    </div>
                    <div class="text-right" style="min-width:110px">
                        <strong><?= rupiah($it['price'] * $it['qty']) ?></strong>
                        <form method="post" class="mt-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="cart_id" value="<?= $it['cart_id'] ?>">
                            <input type="hidden" name="action" value="remove">
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Hapus item ini?">Hapus</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card summary-box">
            <div class="card-body">
                <h3 class="card-title">Ringkasan Belanja</h3>
                <div class="summary-row">
                    <span>Total Item</span><span><?= count($items) ?> produk</span>
                </div>
                <?php
                $totalQty = 0;
                foreach ($items as $it) $totalQty += $it['qty'];
                ?>
                <div class="summary-row">
                    <span>Jumlah Pcs</span><span><?= $totalQty ?></span>
                </div>
                <div class="summary-row total">
                    <span>Subtotal</span><span><?= rupiah($subtotal) ?></span>
                </div>
                <a class="btn btn-primary btn-block btn-lg mt-3" href="<?= url('checkout.php') ?>">Lanjut ke Checkout</a>
                <a class="btn btn-success btn-block mt-1" href="<?= url('katalog.php') ?>">Lanjut Belanja</a>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
