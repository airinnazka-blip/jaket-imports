<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    csrf_check();
    $upd = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $upd->execute([$user['id']]);
    redirect('notifikasi.php');
}

$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC');
$st->execute([$user['id']]);
$notifs = $st->fetchAll();
?>
<div class="section-head">
    <h2>🔔 Notifikasi</h2>
    <?php if ($notifs): ?>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-dark" type="submit" name="mark_read" value="1">Tandai semua sudah dibaca</button>
        </form>
    <?php endif; ?>
</div>

<?php if (!$notifs): ?>
    <div class="card empty">
        <div class="big">🔔</div>
        <h3>Belum ada notifikasi</h3>
        <p>Notifikasi tentang pesanan dan promo akan muncul di sini.</p>
    </div>
<?php else: ?>
    <div class="stack">
        <?php foreach ($notifs as $n): ?>
            <div class="card" style="<?= !$n['is_read'] ? 'border-left:5px solid var(--accent);background:#fffdf5' : '' ?>">
                <div class="card-body">
                    <div class="flex-between">
                        <strong><?= e($n['title']) ?></strong>
                        <?php if (!$n['is_read']): ?><span class="badge badge-warning">Baru</span><?php endif; ?>
                    </div>
                    <p class="text-muted mt-1" style="font-size:.92rem"><?= e($n['message']) ?></p>
                    <p class="text-muted" style="font-size:.8rem"><?= tgl_id($n['created_at']) ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
