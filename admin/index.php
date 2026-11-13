<?php
require_once __DIR__ . '/../includes/header.php';
require_admin();
$active_menu = 'dashboard';

// Statistik
$stats = [
    'produk'    => (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'pesanan'   => (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'penjualan' => (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> "cancelled"')->fetchColumn(),
    'user'      => (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn(),
];

$todaySales = (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> "cancelled" AND DATE(created_at) = CURDATE()')->fetchColumn();
$pendingCount = (int)$pdo->query('SELECT COUNT(*) FROM orders WHERE status = "pending"')->fetchColumn();

$latestOrders = $pdo->query(
    'SELECT o.*, u.name AS user_name FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 8'
)->fetchAll();

// Penjualan 7 hari terakhir
$weekly = $pdo->query(
    "SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS total
     FROM orders WHERE status <> 'cancelled' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at) ORDER BY d"
)->fetchAll();
$chartLabels = [];
$chartValues = [];
for ($i = 6; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-$i day"));
    $chartLabels[] = date('d M', strtotime($key));
    $found = null;
    foreach ($weekly as $w) {
        if ($w['d'] === $key) { $found = (float)$w['total']; break; }
    }
    $chartValues[] = $found ?? 0;
}
?>
<div class="admin-layout">
    <?php require __DIR__ . '/_sidebar.php'; ?>

    <div>
        <div class="section-head" style="margin-top:0">
            <h2>📊 Dashboard Admin</h2>
            <span class="text-muted"><?= date('d F Y') ?></span>
        </div>

        <div class="stat-cards">
            <div class="card stat-card"><span class="icon">🧥</span><div class="label">Total Produk</div><div class="value"><?= $stats['produk'] ?></div></div>
            <div class="card stat-card"><span class="icon">📦</span><div class="label">Total Pesanan</div><div class="value"><?= $stats['pesanan'] ?></div></div>
            <div class="card stat-card"><span class="icon">💰</span><div class="label">Total Penjualan</div><div class="value"><?= rupiah($stats['penjualan']) ?></div></div>
            <div class="card stat-card"><span class="icon">👥</span><div class="label">Pelanggan</div><div class="value"><?= $stats['user'] ?></div></div>
        </div>

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            <div class="card stat-card"><div class="label">Penjualan Hari Ini</div><div class="value"><?= rupiah($todaySales) ?></div></div>
            <div class="card stat-card"><div class="label">Pesanan Perlu Diproses</div>
                <div class="value" style="color:<?= $pendingCount ? 'var(--warning)' : 'var(--success)' ?>"><?= $pendingCount ?></div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h3 class="card-title">Penjualan 7 Hari Terakhir</h3>
                <div class="bar-chart mt-2" id="barChart" data-values="<?= implode(',', $chartValues) ?>" data-labels="<?= e(implode(',', $chartLabels)) ?>"></div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <div class="flex-between">
                    <h3 class="card-title">Pesanan Terbaru</h3>
                    <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/pesanan.php') ?>">Kelola Semua</a>
                </div>
                <div class="table-wrap mt-2">
                    <table class="table">
                        <thead><tr><th>Kode</th><th>Pelanggan</th><th>Total</th><th>Metode</th><th>Status</th><th>Tanggal</th></tr></thead>
                        <tbody>
                        <?php foreach ($latestOrders as $o): ?>
                            <tr>
                                <td><a href="<?= url('admin/pesanan.php?view=' . $o['id']) ?>"><?= e($o['order_code']) ?></a></td>
                                <td><?= e($o['user_name']) ?></td>
                                <td class="num"><?= rupiah($o['total']) ?></td>
                                <td><?= e(strtoupper($o['payment_method'])) ?></td>
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
