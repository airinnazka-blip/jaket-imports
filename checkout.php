<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$user = current_user();

$st = $pdo->prepare(
    "SELECT ci.id AS cart_id, ci.qty, p.* FROM cart_items ci
     JOIN products p ON p.id = ci.product_id WHERE ci.user_id = ?"
);
$st->execute([$user['id']]);
$items = $st->fetchAll();

if (!$items) {
    set_flash('info', 'Keranjang Anda kosong, tidak bisa checkout.');
    redirect('katalog.php');
}

$subtotal = 0;
$invalid = [];
foreach ($items as $it) {
    $subtotal += $it['price'] * $it['qty'];
    if ($it['stock'] <= 0) $invalid[] = $it['name'];
}

$errors = [];
$oldAddress = $user['address'] ?? '';
$method = 'transfer';
$promoCode = '';
$discount = 0;
$promoName = '';
$confirmOrder = isset($_POST['create_order']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $oldAddress = trim($_POST['address'] ?? '');
    $method = in_array($_POST['method'] ?? '', ['transfer', 'ewallet', 'cod']) ? $_POST['method'] : 'transfer';
    $promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));

    if ($confirmOrder && $oldAddress === '') $errors[] = 'Alamat pengiriman wajib diisi.';
    if ($confirmOrder && $invalid) $errors[] = 'Beberapa produk stok habis: ' . implode(', ', $invalid);

    // Validasi promo
    if ($promoCode !== '' && !$errors) {
        $st = $pdo->prepare('SELECT * FROM promos WHERE code = ? AND is_active = 1');
        $st->execute([$promoCode]);
        $promo = $st->fetch();
        if (!$promo) {
            $errors[] = "Kode promo '$promoCode' tidak valid.";
        } elseif ($subtotal < (float)$promo['min_order']) {
            $errors[] = 'Minimal belanja ' . rupiah($promo['min_order']) . ' untuk memakai kode ' . $promoCode . '.';
        } else {
            $discount = $promo['type'] === 'percent'
                ? $subtotal * ((float)$promo['value'] / 100)
                : (float)$promo['value'];
            if ($discount > $subtotal) $discount = $subtotal;
            $promoName = $promo['name'];
        }
    }

    if (!$errors && $confirmOrder) {
        $total = $subtotal - $discount;
        $code = 'JI-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = ? FOR UPDATE');
            foreach ($items as $it) {
                $lock->execute([$it['id']]);
                $row = $lock->fetch();
                if (!$row || (int)$row['stock'] < (int)$it['qty']) {
                    throw new RuntimeException('Stok produk "' . $it['name'] . '" tidak mencukupi (sisa ' . (int)($row['stock'] ?? 0) . '). Silakan perbarui keranjang.');
                }
            }

            $insOrder = $pdo->prepare(
                'INSERT INTO orders (order_code, user_id, address, payment_method, subtotal, discount, total)
                 VALUES (?,?,?,?,?,?,?)'
            );
            $insOrder->execute([$code, $user['id'], $oldAddress, $method, $subtotal, $discount, $total]);
            $orderId = $pdo->lastInsertId();

            $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, qty) VALUES (?,?,?,?,?)');
            $decStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');

            foreach ($items as $it) {
                $insItem->execute([$orderId, $it['id'], $it['name'], $it['price'], $it['qty']]);
                $decStock->execute([$it['qty'], $it['id']]);
            }

            $insPay = $pdo->prepare('INSERT INTO payments (order_id, method) VALUES (?,?)');
            $insPay->execute([$orderId, $method]);

            $delCart = $pdo->prepare('DELETE FROM cart_items WHERE user_id = ?');
            $delCart->execute([$user['id']]);

            $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
            $notif->execute([
                $user['id'],
                'Pesanan ' . $code . ' dibuat',
                'Pesanan Anda berhasil dibuat. Silakan selesaikan pembayaran di halaman pembayaran.'
            ]);

            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Gagal membuat pesanan: ' . $ex->getMessage();
            $total = null;
        }

        if (!$errors && $confirmOrder) {
            set_flash('success', 'Pesanan berhasil dibuat! Selesaikan pembayaran Anda.');
            redirect('pembayaran.php?order=' . $code);
        }
    }
}

$metodes = [
    'transfer' => ['🏦', 'Transfer Bank (BCA / BRI / Mandiri)'],
    'ewallet'  => ['📱', 'E-Wallet (GoPay / OVO / DANA)'],
    'cod'      => ['💵', 'COD (Bayar di Tempat)'],
];
?>
<div class="section-head">
    <h2>Checkout</h2>
    <a class="text-muted" href="<?= url('keranjang.php') ?>">← Kembali ke keranjang</a>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-danger"><?= e($er) ?></div>
<?php endforeach; ?>
<?php if ($invalid): ?>
    <div class="alert alert-warning">Produk dengan stok habis tidak dapat diproses. Silakan hapus dari keranjang: <b><?= e(implode(', ', $invalid)) ?></b></div>
<?php endif; ?>

<div class="panel-grid">
    <form method="post" class="stack" id="checkoutForm">
        <?= csrf_field() ?>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">1. Alamat Pengiriman</h3>
                <div class="form-group mt-2">
                    <label for="address">Alamat Lengkap</label>
                    <textarea class="form-control" id="address" name="address" required><?= e($oldAddress) ?></textarea>
                    <p class="form-hint">Penerima: <?= e($user['name']) ?> — <?= e($user['phone'] ?? 'No. HP belum diisi') ?></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">2. Metode Pembayaran</h3>
                <?php foreach ($metodes as $key => $m): ?>
                    <label class="form-inline mt-2" style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;cursor:pointer">
                        <input type="radio" name="method" value="<?= $key ?>" <?= $method === $key ? 'checked' : '' ?>>
                        <span style="font-size:1.2rem"><?= $m[0] ?></span>
                        <span style="font-weight:600"><?= e($m[1]) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">3. Kode Promo (Opsional)</h3>
                <div class="form-inline mt-2">
                    <input class="form-control" type="text" name="promo_code" placeholder="Misal: JAKET10"
                           value="<?= e($promoCode) ?>" style="text-transform:uppercase">
                    <button class="btn btn-outline-dark" type="submit" name="preview_promo" value="1">Gunakan</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card summary-box">
        <div class="card-body">
            <h3 class="card-title">Ringkasan Pesanan</h3>
            <?php foreach ($items as $it): ?>
                <div class="summary-row">
                    <span><?= e($it['name']) ?> × <?= (int)$it['qty'] ?></span>
                    <span><?= rupiah($it['price'] * $it['qty']) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="summary-row">
                <span>Subtotal</span><span><?= rupiah($subtotal) ?></span>
            </div>
            <?php if ($discount > 0): ?>
                <div class="summary-row text-success">
                    <span>Diskon <?= e($promoName) ?></span><span>− <?= rupiah($discount) ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span>Total</span><span><?= rupiah($subtotal - $discount) ?></span>
            </div>
            <button form="checkoutForm" class="btn btn-primary btn-block btn-lg mt-3" type="submit" name="create_order" value="1">Buat Pesanan</button>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
