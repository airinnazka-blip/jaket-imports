<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$code = trim($_GET['order'] ?? '');
if ($code === '') { set_flash('danger', 'Pesanan tidak ditemukan.'); redirect('pesanan.php'); }

$user = current_user();
auto_complete_overdue_orders($user['id']);

$st = $pdo->prepare('SELECT * FROM orders WHERE order_code = ? AND user_id = ?');
$st->execute([$code, $user['id']]);
$order = $st->fetch();

if (!$order) { set_flash('danger', 'Pesanan tidak ditemukan.'); redirect('pesanan.php'); }

$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$st->execute([$order['id']]);
$items = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ?');
$st->execute([$order['id']]);
$payment = $st->fetch();

// Batalkan pesanan (hanya jika masih pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
    csrf_check();
    if ($order['status'] === 'pending') {
        $upd = $pdo->prepare('UPDATE orders SET status = "cancelled" WHERE id = ?');
        $upd->execute([$order['id']]);

        // kembalikan stok
        $restore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($items as $it) {
            if ($it['product_id']) $restore->execute([$it['qty'], $it['product_id']]);
        }

        $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $notif->execute([$user['id'], 'Pesanan ' . $order['order_code'] . ' dibatalkan', 'Pesanan Anda telah dibatalkan.']);

        set_flash('success', 'Pesanan berhasil dibatalkan.');
    } else {
        set_flash('warning', 'Pesanan tidak dapat dibatalkan karena sudah diproses.');
    }
    redirect('pesanan-detail.php?order=' . $code);
}

$steps = ['pending' => 'Pesanan Dibuat', 'processed' => 'Diproses', 'shipped' => 'Dikirim', 'delivered' => 'Selesai'];
$orderIdx = array_search($order['status'], array_keys($steps));
if ($order['status'] === 'cancelled') $orderIdx = -1;

$remaining = order_received_remaining($order);
$canReceive = can_confirm_received($order);
$deadline = order_received_deadline($order);
?>
<div class="mb-2">
    <a class="text-muted" href="<?= url('pesanan.php') ?>">← Riwayat Pesanan</a>
</div>

<div class="section-head">
    <h2>📦 Detail Pesanan</h2>
    <span class="badge badge-primary"><?= e($order['order_code']) ?></span>
</div>

<?php if ($order['status'] === 'shipped' && $order['shipped_at']): ?>
    <div class="alert alert-info ship-banner">
        <span class="ship-banner-icon">🚚</span>
        <div>
            <b>Barang Anda telah dikirim!</b>
            <div style="font-weight:500;font-size:.92rem">
                Dikirim pada <?= tgl_id($order['shipped_at']) ?>.
                <?php if ($deadline): ?>
                    Konfirmasi penerimaan paling lambat <b><?= tgl_id(date('Y-m-d H:i:s', $deadline)) ?></b>.
                <?php endif; ?>
                <?php if ($remaining !== null && $remaining > 0): ?>
                    Tersisa <b><?= (int)ceil($remaining / 86400) ?> hari</b> lagi.
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="panel-grid">
    <div class="stack">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Tracking Pesanan</h3>

                <?php if ($order['status'] === 'cancelled'): ?>
                    <div class="alert alert-danger mt-2">Pesanan ini telah dibatalkan.</div>
                <?php else: ?>
                    <div class="stepper">
                        <?php
                        $stepKeys = array_keys($steps);
                        foreach ($stepKeys as $sIdx => $key):
                            $cls = $sIdx < $orderIdx ? 'done' : ($sIdx === $orderIdx ? 'active' : '');
                        ?>
                            <div class="step <?= $cls ?>">
                                <div class="dot"><?= $sIdx < $orderIdx ? '✓' : ($sIdx + 1) ?></div>
                                <span><?= e($steps[$key]) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted" style="font-size:.88rem">
                        Status saat ini: <?= status_badge($order['status']) ?>
                        <span class="mt-1" style="display:block">Terakhir diperbarui: <?= tgl_id($order['updated_at']) ?></span>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Item Pesanan</h3>
                <?php foreach ($items as $it): ?>
                    <div class="cart-item" style="padding:12px 0">
                        <div style="min-width:64px">
                            <?php
                            $prow = null;
                            if ($it['product_id']) {
                                $ps = $pdo->prepare('SELECT * FROM products WHERE id = ?');
                                $ps->execute([$it['product_id']]);
                                $prow = $ps->fetch();
                            }
                            ?>
                            <img src="<?= $prow ? e(product_image($prow)) : url('images/placeholder.svg') ?>" style="width:64px;height:64px;object-fit:cover;border-radius:8px">
                        </div>
                        <div class="info">
                            <h4><?= e($it['product_name']) ?></h4>
                            <div class="price"><?= rupiah($it['price']) ?> × <?= (int)$it['qty'] ?></div>
                            <?php if ($order['status'] === 'delivered' && $prow): ?>
                                <a class="btn btn-sm btn-primary mt-1" href="<?= url('produk.php?id=' . $it['product_id']) ?>">Beri Rating & Ulasan</a>
                            <?php endif; ?>
                        </div>
                        <strong><?= rupiah($it['price'] * $it['qty']) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="stack">
        <div class="card summary-box summary-static">
            <div class="card-body">
                <h3 class="card-title">Ringkasan</h3>
                <div class="summary-row"><span>Subtotal</span><span><?= rupiah($order['subtotal']) ?></span></div>
                <?php if ((float)$order['discount'] > 0): ?>
                    <div class="summary-row text-success"><span>Diskon</span><span>− <?= rupiah($order['discount']) ?></span></div>
                <?php endif; ?>
                <div class="summary-row total"><span>Total</span><span><?= rupiah($order['total']) ?></span></div>
                <div class="summary-row"><span>Metode Bayar</span><span><?= e(strtoupper($order['payment_method'])) ?></span></div>
                <div class="summary-row">
                    <span>Status Bayar</span>
                    <?= payment_status_badge($payment) ?>
                </div>
                <?php if ($payment && $payment['status'] !== 'success'): ?>
                    <a class="btn btn-success btn-block mt-2" href="<?= url('pembayaran.php?order=' . $order['order_code']) ?>"><?= !empty($payment['proof_image']) ? 'Lihat / Ubah Bukti Bayar' : 'Lanjut Bayar' ?></a>
                <?php endif; ?>

                <?php if ($order['status'] === 'shipped'): ?>
                    <div class="confirm-box mt-2">
                        <b>Sudah menerima barang?</b>
                        <p class="text-muted" style="font-size:.83rem;margin:4px 0 10px">
                            <?php if ($canReceive && $remaining !== null): ?>
                                Konfirmasi dalam <?= (int)ceil($remaining / 86400) ?> hari.
                            <?php else: ?>
                                Batas konfirmasi 5 hari telah lewat.
                            <?php endif; ?>
                        </p>
                        <?php if ($canReceive): ?>
                            <form method="post" action="<?= url('pesanan-terima.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="confirm_receive" value="1">
                                <input type="hidden" name="order_code" value="<?= e($order['order_code']) ?>">
                                <button class="btn btn-confirm btn-block btn-lg" type="submit" data-confirm="Konfirmasi bahwa barang sudah Anda terima?">✓ Pesanan Diterima</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-confirm btn-block btn-lg" type="button" disabled>✓ Pesanan Diterima</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($order['status'] === 'pending'): ?>
                    <form method="post" class="mt-1">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger btn-block" type="submit" name="cancel" value="1" data-confirm="Batalkan pesanan ini?">Batalkan Pesanan</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="card-title">Alamat Pengiriman</h3>
                <p class="text-muted" style="font-size:.92rem"><?= nl2br(e($order['address'])) ?></p>
            </div>
        </div>

        <?php if ($payment && !empty($payment['proof_image'])): ?>
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Bukti Pembayaran</h3>
                    <div class="summary-row"><span>Nama Pengirim</span><span><?= e($payment['sender_name'] ?: '-') ?></span></div>
                    <div class="summary-row"><span>Bank / E-Wallet</span><span><?= e(bank_label($payment['sender_bank'])) ?></span></div>
                    <div class="summary-row"><span>Nominal</span><span><?= $payment['paid_amount'] !== null ? rupiah($payment['paid_amount']) : '-' ?></span></div>
                    <div class="summary-row"><span>Waktu Transfer</span><span><?= $payment['transfer_at'] ? tgl_id($payment['transfer_at']) : '-' ?></span></div>
                    <?php $proofUrl = payment_proof_url($payment); ?>
                    <?php if ($proofUrl): ?>
                        <a href="<?= e($proofUrl) ?>" target="_blank" class="mt-2" style="display:block">
                            <img src="<?= e($proofUrl) ?>" alt="Bukti pembayaran" style="border-radius:10px;border:1px solid var(--border)">
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
