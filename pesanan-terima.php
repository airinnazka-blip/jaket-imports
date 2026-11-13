<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm_receive'])) {
    redirect('pesanan.php');
}
csrf_check();

$user = current_user();
$code = trim($_POST['order_code'] ?? '');

$st = $pdo->prepare('SELECT * FROM orders WHERE order_code = ? AND user_id = ?');
$st->execute([$code, $user['id']]);
$order = $st->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

$back = 'pesanan-detail.php?order=' . urlencode($code);

if ($order['status'] === 'delivered') {
    set_flash('info', 'Pesanan ini sudah dikonfirmasi diterima.');
    redirect($back);
}

if ($order['status'] !== 'shipped') {
    set_flash('warning', 'Pesanan belum dikirim, belum bisa dikonfirmasi diterima.');
    redirect($back);
}

$remaining = order_received_remaining($order);
if ($remaining !== null && $remaining <= 0) {
    set_flash('warning', 'Batas waktu konfirmasi ' . BATAS_PENERIMAAN_HARI . ' hari telah terlewati.');
    redirect($back);
}

$pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$order['id']]);

$notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
$notif->execute([
    $user['id'],
    'Pesanan ' . $order['order_code'] . ' telah diterima',
    'Terima kasih! Anda telah mengonfirmasi bahwa barang sudah diterima. Jangan lupa beri rating dan ulasan produk.'
]);

set_flash('success', 'Terima kasih! Pesanan dikonfirmasi telah diterima.');
redirect($back);
