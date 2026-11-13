<?php
require_once __DIR__ . '/../includes/header.php';
require_admin();
$active_menu = 'pesanan';

$viewId = (int)($_GET['view'] ?? 0);
$filter = $_GET['status'] ?? 'all';

// ---- Ubah status pesanan ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    csrf_check();
    $orderId = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];

    $valid = ['processed', 'shipped', 'delivered', 'cancelled'];
    if (in_array($newStatus, $valid, true)) {
        $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $st->execute([$orderId]);
        $order = $st->fetch();

        if ($order) {
            // Saat diproses: tandai pembayaran lunas (jika belum)
            if ($newStatus === 'processed') {
                $pdo->prepare('UPDATE payments SET status = "success", paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ?')->execute([$orderId]);
            }
            // Saat dikirim: catat waktu kirim (batas konfirmasi 5 hari untuk user)
            if ($newStatus === 'shipped') {
                $pdo->prepare('UPDATE orders SET status = ?, shipped_at = COALESCE(shipped_at, NOW()) WHERE id = ?')->execute([$newStatus, $orderId]);
            } else {
                $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $orderId]);
            }

            $title = match ($newStatus) {
                'processed' => 'Pesanan ' . $order['order_code'] . ' sedang diproses',
                'shipped'   => 'Pesanan ' . $order['order_code'] . ' telah dikirim',
                'delivered' => 'Pesanan ' . $order['order_code'] . ' telah selesai',
                'cancelled' => 'Pesanan ' . $order['order_code'] . ' dibatalkan',
            };
            $msg = match ($newStatus) {
                'processed' => 'Admin telah mengonfirmasi pesanan Anda dan sedang menyiapkan barang.',
                'shipped'   => 'Barang Anda telah dikirim. Silakan pantau sampai barang diterima dan konfirmasi dalam 5 hari.',
                'delivered' => 'Barang telah sampai. Jangan lupa beri rating dan ulasan produk ya!',
                'cancelled' => 'Pesanan Anda telah dibatalkan oleh admin.',
            };

            // Kembalikan stok saat dibatalkan
            if ($newStatus === 'cancelled' && $order['status'] !== 'cancelled') {
                $items = $pdo->prepare('SELECT product_id, qty FROM order_items WHERE order_id = ?');
                $items->execute([$orderId]);
                $restore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($items->fetchAll() as $it) {
                    if ($it['product_id']) $restore->execute([$it['qty'], $it['product_id']]);
                }
            }

            $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
            $notif->execute([$order['user_id'], $title, $msg]);

            set_flash('success', 'Status pesanan diperbarui.');
            redirect('admin/pesanan.php' . ($viewId ? '?view=' . $viewId : ''));
        }
    }
    set_flash('danger', 'Gagal memperbarui status.');
    redirect('admin/pesanan.php');
}

// ---- Konfirmasi pembayaran manual (verifikasi bukti transfer) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    csrf_check();
    $orderId = (int)$_POST['order_id'];
    $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $st->execute([$orderId]);
    $order = $st->fetch();
    if ($order) {
        $pdo->prepare('UPDATE payments SET status = "success", paid_at = COALESCE(paid_at, NOW()) WHERE order_id = ?')->execute([$orderId]);
        $pdo->prepare('UPDATE orders SET status = IF(status = "pending", "processed", status) WHERE id = ?')->execute([$orderId]);
        $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $notif->execute([
            $order['user_id'],
            'Pembayaran ' . $order['order_code'] . ' terverifikasi',
            'Pembayaran Anda telah diverifikasi admin. Pesanan segera diproses.'
        ]);
        set_flash('success', 'Pembayaran dikonfirmasi lunas.');
    } else {
        set_flash('danger', 'Pesanan tidak ditemukan.');
    }
    redirect('admin/pesanan.php' . ($viewId ? '?view=' . $viewId : ''));
}

// ---- Detail satu pesanan ----
if ($viewId > 0) {
    $st = $pdo->prepare('SELECT o.*, u.name, u.email, u.phone FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
    $st->execute([$viewId]);
    $order = $st->fetch();
    if (!$order) { set_flash('danger', 'Pesanan tidak ditemukan.'); redirect('admin/pesanan.php'); }

    $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([$viewId]);
    $items = $items->fetchAll();

    $pay = $pdo->prepare('SELECT * FROM payments WHERE order_id = ?');
    $pay->execute([$viewId]);
    $payment = $pay->fetch();

    $can = ['pending' => ['processed', 'cancelled'], 'processed' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];
    ?>
    <div class="admin-layout">
        <?php require __DIR__ . '/_sidebar.php'; ?>
        <div>
            <div class="section-head" style="margin-top:0">
                <h2>📦 <?= e($order['order_code']) ?></h2>
                <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/pesanan.php') ?>">← Daftar Pesanan</a>
            </div>

            <div class="stat-cards">
                <div class="card stat-card"><div class="label">Pelanggan</div><div class="value" style="font-size:1.1rem"><?= e($order['name']) ?></div></div>
                <div class="card stat-card"><div class="label">Total</div><div class="value"><?= rupiah($order['total']) ?></div></div>
                <div class="card stat-card"><div class="label">Status</div><div class="value" style="font-size:1rem"><?= status_badge($order['status']) ?></div></div>
                <div class="card stat-card"><div class="label">Pembayaran</div>
                    <div class="value" style="font-size:1rem">
                        <?= e(strtoupper($order['payment_method'])) ?> • <?= payment_status_badge($payment) ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">Item Pesanan</h3>
                    <div class="table-wrap mt-2">
                        <table class="table">
                            <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th class="num">Subtotal</th></tr></thead>
                            <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= e($it['product_name']) ?></td>
                                    <td><?= rupiah($it['price']) ?></td>
                                    <td><?= (int)$it['qty'] ?></td>
                                    <td class="num"><?= rupiah($it['price'] * $it['qty']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr><td colspan="3" class="text-right"><b>Subtotal</b></td><td class="num"><?= rupiah($order['subtotal']) ?></td></tr>
                                <?php if ((float)$order['discount'] > 0): ?>
                                    <tr><td colspan="3" class="text-right text-success"><b>Diskon</b></td><td class="num">− <?= rupiah($order['discount']) ?></td></tr>
                                <?php endif; ?>
                                <tr><td colspan="3" class="text-right"><b>Total</b></td><td class="num"><b><?= rupiah($order['total']) ?></b></td></tr>
                            </tfoot>
                        </table>
                    </div>

                    <h3 class="card-title mt-3">Alamat Pengiriman</h3>
                    <p class="text-muted" style="font-size:.92rem"><?= nl2br(e($order['address'])) ?></p>
                    <p class="text-muted" style="font-size:.85rem">Kontak: <?= e($order['phone'] ?? '-') ?> • <?= e($order['email']) ?> • Dibuat <?= tgl_id($order['created_at']) ?></p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="flex-between">
                        <h3 class="card-title">💳 Bukti Pembayaran</h3>
                        <?= payment_status_badge($payment) ?>
                    </div>

                    <?php $proofUrl = $payment ? payment_proof_url($payment) : null; ?>
                    <?php if ($payment && (!empty($payment['proof_image']) || $payment['paid_amount'] !== null)): ?>
                        <div class="table-wrap mt-2">
                            <table class="table">
                                <tbody>
                                    <tr><th style="width:220px">Metode</th><td><?= e(strtoupper($order['payment_method'])) ?></td></tr>
                                    <tr><th>Nama Pengirim</th><td><?= e($payment['sender_name'] ?: '-') ?></td></tr>
                                    <tr><th>Bank / E-Wallet Asal</th><td><?= e(bank_label($payment['sender_bank'])) ?></td></tr>
                                    <tr><th>Nominal Ditransfer</th><td><b><?= $payment['paid_amount'] !== null ? rupiah($payment['paid_amount']) : '-' ?></b></td></tr>
                                    <tr><th>Waktu Transfer</th><td><?= $payment['transfer_at'] ? tgl_id($payment['transfer_at']) : '-' ?></td></tr>
                                    <tr><th>Catatan User</th><td><?= $payment['note'] ? nl2br(e($payment['note'])) : '-' ?></td></tr>
                                    <tr><th>Waktu Verifikasi</th><td><?= $payment['paid_at'] ? tgl_id($payment['paid_at']) : 'Belum diverifikasi' ?></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($proofUrl): ?>
                            <div class="mt-2">
                                <p class="form-hint">Foto Bukti Transfer (klik untuk memperbesar):</p>
                                <a href="<?= e($proofUrl) ?>" target="_blank">
                                    <img src="<?= e($proofUrl) ?>" alt="Bukti transfer" style="max-width:340px;border-radius:10px;border:1px solid var(--border)">
                                </a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mt-2">User belum mengunggah foto bukti transfer.</p>
                        <?php endif; ?>

                        <?php if ($payment['status'] !== 'success'): ?>
                            <form method="post" class="mt-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                <input type="hidden" name="confirm_payment" value="1">
                                <button class="btn btn-success" type="submit" data-confirm="Konfirmasi pembayaran ini sudah lunas?">✓ Konfirmasi Pembayaran Lunas</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted mt-2">Belum ada bukti pembayaran dari user.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h3 class="card-title">Perbarui Status</h3>
                    <form method="post" class="flex wrap mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="change_status" value="1">
                        <?php if (in_array('processed', $can[$order['status']], true)): ?>
                            <button class="btn btn-success" type="submit" name="status" value="processed">✓ Konfirmasi & Proses</button>
                        <?php endif; ?>
                        <?php if (in_array('shipped', $can[$order['status']], true)): ?>
                            <button class="btn btn-info" type="submit" name="status" value="shipped">🚚 Tandai Dikirim</button>
                        <?php endif; ?>
                        <?php if (in_array('delivered', $can[$order['status']], true)): ?>
                            <button class="btn btn-primary" type="submit" name="status" value="delivered">✔ Tandai Selesai</button>
                        <?php endif; ?>
                        <?php if (in_array('cancelled', $can[$order['status']], true)): ?>
                            <button class="btn btn-danger" type="submit" name="status" value="cancelled" data-confirm="Batalkan pesanan ini?">✖ Batalkan</button>
                        <?php endif; ?>
                        <?php if (!$can[$order['status']]): ?>
                            <p class="text-muted">Tidak ada aksi lebih lanjut untuk status ini.</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ---- Daftar pesanan ----
$where = "1=1";
$params = [];
if ($filter !== 'all') { $where .= " AND o.status = ?"; $params[] = $filter; }
$orders = $pdo->prepare(
    "SELECT o.*, u.name AS user_name FROM orders o JOIN users u ON u.id = o.user_id
     WHERE $where ORDER BY o.id DESC"
);
$orders->execute($params);
$orders = $orders->fetchAll();

$statuses = $pdo->query('SELECT status, COUNT(*) c FROM orders GROUP BY status')->fetchAll();
$countBy = ['pending' => 0, 'processed' => 0, 'shipped' => 0, 'delivered' => 0, 'cancelled' => 0];
foreach ($statuses as $s) $countBy[$s['status']] = (int)$s['c'];
?>
<div class="admin-layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div>
        <div class="section-head" style="margin-top:0">
            <h2>📦 Kelola Pesanan</h2>
            <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/pesanan.php') ?>">Reset Filter</a>
        </div>

        <div class="flex wrap mb-3">
            <a class="btn btn-sm <?= $filter === 'all' ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= url('admin/pesanan.php') ?>">Semua (<?= array_sum($countBy) ?>)</a>
            <?php foreach ($countBy as $k => $c): ?>
                <a class="btn btn-sm <?= $filter === $k ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= url('admin/pesanan.php?status=' . $k) ?>"><?= e(status_label($k)) ?> (<?= $c ?>)</a>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Kode</th><th>Pelanggan</th><th>Total</th><th>Metode</th><th>Bayar</th><th>Status</th><th>Tanggal</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?>
                        <tr><td colspan="8" class="text-center text-muted">Tidak ada pesanan dengan filter ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $o): ?>
                        <?php $opay = $pdo->prepare('SELECT status, proof_image FROM payments WHERE order_id = ?');
                        $opay->execute([$o['id']]);
                        $opayRow = $opay->fetch(); ?>
                        <tr>
                            <td><strong><?= e($o['order_code']) ?></strong></td>
                            <td><?= e($o['user_name']) ?></td>
                            <td class="num"><?= rupiah($o['total']) ?></td>
                            <td><?= e(strtoupper($o['payment_method'])) ?></td>
                            <td><?= payment_status_badge($opayRow ?: null) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td><?= tgl_id($o['created_at']) ?></td>
                            <td><a class="btn btn-sm btn-outline-dark" href="<?= url('admin/pesanan.php?view=' . $o['id']) ?>">Kelola</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
