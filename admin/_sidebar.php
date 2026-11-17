<?php
// Sidebar menu admin — dipakai di semua halaman admin
$active = $active_menu ?? '';
$countRow = $pdo->query(
    "SELECT COALESCE(SUM(status='pending'),0) AS pending,
            COALESCE(SUM(status='processed'),0) AS processed,
            COALESCE(SUM(status='shipped'),0) AS shipped
     FROM orders"
)->fetch();
$pendingSum = (int)$countRow['pending'] + (int)$countRow['processed'] + (int)$countRow['shipped'];
?>
<aside class="admin-sidebar card">
    <div class="card-body" style="padding:14px">
        <a href="<?= url('admin/index.php') ?>" class="<?= $active === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="<?= url('admin/pesanan.php') ?>" class="<?= $active === 'pesanan' ? 'active' : '' ?>">
            📦 Pesanan <?php if ($pendingSum > 0): ?><span class="tab-badge"><?= $pendingSum ?></span><?php endif; ?>
        </a>
        <a href="<?= url('admin/produk.php') ?>" class="<?= $active === 'produk' ? 'active' : '' ?>">🧥 Produk</a>
        <a href="<?= url('admin/promo.php') ?>" class="<?= $active === 'promo' ? 'active' : '' ?>">🎁 Promo</a>
        <a href="<?= url('admin/laporan.php') ?>" class="<?= $active === 'laporan' ? 'active' : '' ?>">📈 Laporan</a>
        <hr style="border:none;border-top:1px solid var(--border);margin:10px 0">
        <a href="<?= url('index.php') ?>">← Lihat Toko</a>
    </div>
</aside>
