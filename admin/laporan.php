<?php
require_once __DIR__ . '/../includes/header.php';
require_admin();
$active_menu = 'laporan';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

// Validasi range
$dateFrom = DateTime::createFromFormat('Y-m-d', $from);
$dateTo = DateTime::createFromFormat('Y-m-d', $to);
if (!$dateFrom || !$dateTo || $dateFrom > $dateTo) {
    set_flash('danger', 'Rentang tanggal tidak valid.');
    redirect('admin/laporan.php');
}

$where = "o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
$params = [$from, $to];

// Ringkasan
$summary = $pdo->prepare(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total END),0) AS revenue,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.discount END),0) AS discount_given,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN 1 END),0) AS paid_orders
     FROM orders o WHERE $where"
);
$summary->execute($params);
$sum = $summary->fetch();

$orders = $pdo->prepare(
    "SELECT o.*, u.name AS user_name FROM orders o JOIN users u ON u.id = o.user_id
     WHERE $where ORDER BY o.id DESC"
);
$orders->execute($params);
$orders = $orders->fetchAll();

// Item terjual
$itemsSold = $pdo->prepare(
    "SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled' AND $where"
);
$itemsSold->execute($params);
$soldQty = (int)$itemsSold->fetchColumn();

// Produk terlaris
$topProducts = $pdo->prepare(
    "SELECT oi.product_name, SUM(oi.qty) qty, SUM(oi.qty * oi.price) total
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled' AND $where
     GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 5"
);
$topProducts->execute($params);
$topProducts = $topProducts->fetchAll();

// Penjualan per kategori
$byCategory = $pdo->prepare(
    "SELECT c.name AS category_name, SUM(oi.qty * oi.price) total, SUM(oi.qty) qty
     FROM order_items oi JOIN orders o ON o.id = oi.order_id
     JOIN products p ON p.id = oi.product_id
     LEFT JOIN categories c ON c.id = p.category_id
     WHERE o.status <> 'cancelled' AND $where
     GROUP BY c.id, c.name ORDER BY total DESC"
);
$byCategory->execute($params);
$byCategory = $byCategory->fetchAll();

// Per metode pembayaran
$byMethod = $pdo->prepare(
    "SELECT payment_method, COUNT(*) c, COALESCE(SUM(total),0) total
     FROM orders o WHERE o.status <> 'cancelled' AND $where GROUP BY o.payment_method"
);
$byMethod->execute($params);
$byMethod = $byMethod->fetchAll();
?>
<div class="admin-layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>
    <div>
        <div class="section-head" style="margin-top:0">
            <h2>📈 Laporan Penjualan</h2>
        </div>

        <form class="card filter-bar" method="get" action="">
            <div class="form-inline">
                <label>Dari</label>
                <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
                <label>Sampai</label>
                <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
                <button class="btn btn-dark" type="submit">Tampilkan</button>
                <a class="btn btn-outline-dark" href="<?= url('admin/laporan.php') ?>">Bulan Ini</a>
            </div>
        </form>

        <div class="stat-cards">
            <div class="card stat-card"><span class="icon">💰</span><div class="label">Pendapatan</div><div class="value"><?= rupiah($sum['revenue']) ?></div></div>
            <div class="card stat-card"><span class="icon">🧾</span><div class="label">Total Pesanan</div><div class="value"><?= (int)$sum['total_orders'] ?></div></div>
            <div class="card stat-card"><span class="icon">📦</span><div class="label">Item Terjual</div><div class="value"><?= $soldQty ?></div></div>
            <div class="card stat-card"><span class="icon">🏷️</span><div class="label">Total Diskon</div><div class="value"><?= rupiah($sum['discount_given']) ?></div></div>
        </div>

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(340px,1fr))">
            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">🏆 Produk Terlaris</h3>
                    <table class="table mt-2">
                        <thead><tr><th>Produk</th><th class="num">Qty</th><th class="num">Pendapatan</th></tr></thead>
                        <tbody>
                        <?php if (!$topProducts): ?><tr><td colspan="3" class="text-center text-muted">Belum ada data.</td></tr><?php endif; ?>
                        <?php foreach ($topProducts as $t): ?>
                            <tr><td><?= e($t['product_name']) ?></td><td class="num"><?= (int)$t['qty'] ?></td><td class="num"><?= rupiah($t['total']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="card-title">📂 Penjualan per Kategori</h3>
                    <table class="table mt-2">
                        <thead><tr><th>Kategori</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>
                        <tbody>
                        <?php if (!$byCategory): ?><tr><td colspan="3" class="text-center text-muted">Belum ada data.</td></tr><?php endif; ?>
                        <?php foreach ($byCategory as $c): ?>
                            <tr><td><?= e($c['category_name'] ?? 'Tanpa Kategori') ?></td><td class="num"><?= (int)$c['qty'] ?></td><td class="num"><?= rupiah($c['total']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title">💳 Metode Pembayaran</h3>
                <div class="grid mt-2" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
                    <?php foreach ($byMethod as $m): ?>
                        <div class="card" style="padding:16px;text-align:center">
                            <div class="label" style="color:var(--muted);font-size:.8rem;font-weight:700;text-transform:uppercase"><?= e(strtoupper($m['payment_method'])) ?></div>
                            <div class="value" style="font-size:1.3rem;font-weight:800"><?= rupiah($m['total']) ?></div>
                            <div class="text-muted" style="font-size:.85rem"><?= (int)$m['c'] ?> pesanan</div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$byMethod): ?><p class="text-muted">Belum ada data.</p><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <div class="flex-between">
                    <h3 class="card-title">Rincian Pesanan</h3>
                    <span class="text-muted" style="font-size:.85rem"><?= count($orders) ?> pesanan • <?= $from ?> s/d <?= $to ?></span>
                </div>
                <div class="table-wrap mt-2">
                    <table class="table">
                        <thead><tr><th>Kode</th><th>Pelanggan</th><th>Subtotal</th><th>Diskon</th><th>Total</th><th>Status</th><th>Tanggal</th></tr></thead>
                        <tbody>
                        <?php if (!$orders): ?><tr><td colspan="7" class="text-center text-muted">Tidak ada pesanan pada rentang tanggal ini.</td></tr><?php endif; ?>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td><a href="<?= url('admin/pesanan.php?view=' . $o['id']) ?>"><?= e($o['order_code']) ?></a></td>
                                <td><?= e($o['user_name']) ?></td>
                                <td class="num"><?= rupiah($o['subtotal']) ?></td>
                                <td class="num"><?= rupiah($o['discount']) ?></td>
                                <td class="num"><b><?= rupiah($o['total']) ?></b></td>
                                <td><?= status_badge($o['status']) ?></td>
                                <td><?= tgl_id($o['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
