<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$user = current_user();

// Tandai selesai otomatis bila batas 5 hari terlewat
auto_complete_overdue_orders($user['id']);

$st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
$st->execute([$user['id']]);
$orders = $st->fetchAll();

// Pesanan yang sedang dikirim (perlu konfirmasi diterima)
$shipping = array_values(array_filter($orders, fn($o) => $o['status'] === 'shipped'));

function count_items_of_order($orderId) {
    global $pdo;
    $st = $pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM order_items WHERE order_id = ?');
    $st->execute([$orderId]);
    return (int)$st->fetchColumn();
}
?>
<div class="section-head">
    <h2>📦 Riwayat Pesanan</h2>
    <a class="text-muted" href="<?= url('katalog.php') ?>">Belanja lagi →</a>
</div>

<?php foreach ($shipping as $s):
    $rem = order_received_remaining($s);
    $hari = $rem !== null ? max(0, ceil($rem / 86400)) : null;
?>
    <div class="alert alert-info ship-banner">
        <span class="ship-banner-icon">🚚</span>
        <div>
            <b>Barang Anda telah dikirim!</b>
            <div style="font-weight:500;font-size:.92rem">
                Pesanan <b><?= e($s['order_code']) ?></b> sedang dalam perjalanan.
                <?php if ($hari !== null): ?>
                    Konfirmasi penerimaan dalam <b><?= (int)$hari ?> hari</b> lagi, jika tidak pesanan otomatis ditandai selesai.
                <?php endif; ?>
            </div>
        </div>
        <a class="btn btn-sm btn-confirm" href="<?= url('pesanan-detail.php?order=' . $s['order_code']) ?>">Pantau & Terima</a>
    </div>
<?php endforeach; ?>

<?php if (!$orders): ?>
    <div class="card empty">
        <div class="big">📦</div>
        <h3>Belum ada pesanan</h3>
        <p>Ayo mulai belanja jaket import original favoritmu.</p>
        <a class="btn btn-primary mt-2" href="<?= url('katalog.php') ?>">Lihat Katalog</a>
    </div>
<?php else: ?>
    <div class="stack">
        <?php foreach ($orders as $o): ?>
            <div class="card">
                <div class="card-body">
                    <div class="flex-between wrap">
                        <div>
                            <strong><?= e($o['order_code']) ?></strong>
                            <span class="text-muted" style="font-size:.85rem"> • <?= tgl_id($o['created_at']) ?></span>
                        </div>
                        <?= status_badge($o['status']) ?>
                    </div>
                    <div class="flex-between wrap mt-2">
                        <span class="text-muted" style="font-size:.9rem">
                            <?= e($o['payment_method'] === 'cod' ? 'COD' : strtoupper($o['payment_method'])) ?> • <?= count_items_of_order($o['id']) ?> item
                        </span>
                        <strong><?= rupiah($o['total']) ?></strong>
                    </div>

                    <?php if ($o['status'] === 'shipped'): ?>
                        <div class="mt-2 text-info" style="font-size:.9rem">
                            🚚 Barang Anda telah dikirim. Silakan konfirmasi setelah barang diterima.
                        </div>
                    <?php elseif ($o['status'] === 'processed'): ?>
                        <div class="mt-2 text-muted" style="font-size:.9rem">📦 Pesanan sedang diproses admin.</div>
                    <?php elseif ($o['status'] === 'delivered'): ?>
                        <div class="mt-2 text-success" style="font-size:.9rem">✔ Barang sudah diterima. Terima kasih!</div>
                    <?php endif; ?>

                    <div class="flex wrap mt-2">
                        <a class="btn btn-sm btn-outline-dark" href="<?= url('pesanan-detail.php?order=' . $o['order_code']) ?>">Lihat Detail & Tracking</a>
                        <?php if (can_confirm_received($o)): ?>
                            <form method="post" action="<?= url('pesanan-terima.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="confirm_receive" value="1">
                                <input type="hidden" name="order_code" value="<?= e($o['order_code']) ?>">
                                <button class="btn btn-sm btn-confirm" type="submit" data-confirm="Konfirmasi bahwa barang sudah Anda terima?">✓ Pesanan Diterima</button>
                            </form>
                        <?php elseif ($o['status'] === 'shipped'): ?>
                            <button class="btn btn-sm btn-confirm" type="button" disabled title="Batas waktu konfirmasi telah lewat">✓ Pesanan Diterima</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
