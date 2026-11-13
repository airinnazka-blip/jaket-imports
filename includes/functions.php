<?php
// ============================================================
// Fungsi bantu umum
// ============================================================

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . BASE_URL . '/' . ltrim($url, '/'));
    exit;
}

function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

// Format mata uang Rupiah
function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}

// Format tanggal Indonesia
function tgl_id($datetime) {
    $bulan = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($datetime);
    if (!$ts) return '-';
    $d = (int)date('j', $ts);
    $m = $bulan[(int)date('n', $ts)];
    $y = date('Y', $ts);
    $h = date('H:i', $ts);
    return "$d $m $y $h WIB";
}

// ----- Flash message -----
function set_flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (empty($_SESSION['flash'])) return [];
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ----- CSRF -----
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_check() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        http_response_code(403);
        die('Token keamanan tidak valid.');
    }
}

// ----- Keranjang -----
function cart_count($userId) {
    global $pdo;
    $st = $pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE user_id = ?');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

// ----- Notifikasi -----
function unread_notif_count($userId) {
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

// ----- Produk -----
function product_image($product) {
    $img = $product['image'] ?? null;
    if ($img && file_exists(__DIR__ . '/../images/' . $img)) {
        return url('images/' . $img);
    }
    return url('images/placeholder.svg');
}

function product_rating_avg($productId) {
    global $pdo;
    $st = $pdo->prepare('SELECT AVG(rating), COUNT(*) FROM reviews WHERE product_id = ?');
    $st->execute([$productId]);
    return $st->fetch(PDO::FETCH_NUM);
}

// Ambil semua kategori
function all_categories() {
    global $pdo;
    return $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();
}

// Status pesanan dalam Bahasa Indonesia
function status_label($status) {
    $map = [
        'pending'    => 'Menunggu Konfirmasi',
        'processed'  => 'Diproses',
        'shipped'    => 'Dikirim',
        'delivered'  => 'Selesai',
        'cancelled'  => 'Dibatalkan',
    ];
    return $map[$status] ?? ucfirst($status);
}

function status_badge($status) {
    $class = [
        'pending'   => 'badge-warning',
        'processed' => 'badge-info',
        'shipped'   => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger',
    ];
    return '<span class="badge ' . ($class[$status] ?? 'badge-secondary') . '">' . e(status_label($status)) . '</span>';
}

// ============================================================
// Konfirmasi penerimaan barang (batas 5 hari sejak dikirim)
// ============================================================
define('BATAS_PENERIMAAN_HARI', 5);

// Unix time batas konfirmasi (shipped_at + 5 hari), atau null
function order_received_deadline($order) {
    if (empty($order['shipped_at'])) return null;
    $ts = strtotime($order['shipped_at']);
    if (!$ts) return null;
    return $ts + BATAS_PENERIMAAN_HARI * 86400;
}

// Sisa waktu (dalam detik) untuk konfirmasi, atau null. Bisa negatif.
function order_received_remaining($order) {
    $deadline = order_received_deadline($order);
    return $deadline === null ? null : $deadline - time();
}

// Apakah pesanan masih boleh dikonfirmasi diterima
function can_confirm_received($order) {
    if (($order['status'] ?? '') !== 'shipped') return false;
    $rem = order_received_remaining($order);
    return $rem === null || $rem > 0;
}

// Tandai selesai otomatis jika batas 5 hari sudah lewat
function auto_complete_overdue_orders($userId = null) {
    global $pdo;
    $sql = "SELECT * FROM orders
            WHERE status = 'shipped' AND shipped_at IS NOT NULL
              AND DATE_ADD(shipped_at, INTERVAL " . BATAS_PENERIMAAN_HARI . " DAY) <= NOW()";
    $params = [];
    if ($userId !== null) { $sql .= ' AND user_id = ?'; $params[] = (int)$userId; }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $o) {
        $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$o['id']]);
        $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)')
            ->execute([
                $o['user_id'],
                'Pesanan ' . $o['order_code'] . ' selesai otomatis',
                'Batas waktu konfirmasi penerimaan ' . BATAS_PENERIMAAN_HARI . ' hari telah terlewati, pesanan ditandai selesai.'
            ]);
    }
}

// Badge status pembayaran: Lunas / Menunggu Verifikasi / Belum Bayar
function payment_status_badge($payment) {
    if ($payment && $payment['status'] === 'success') {
        return '<span class="badge badge-success">Lunas</span>';
    }
    if ($payment && !empty($payment['proof_image'])) {
        return '<span class="badge badge-info">Menunggu Verifikasi</span>';
    }
    return '<span class="badge badge-warning">Belum Bayar</span>';
}

// URL gambar bukti pembayaran (atau null)
function payment_proof_url($payment) {
    if (!empty($payment['proof_image']) && file_exists(__DIR__ . '/../uploads/payments/' . $payment['proof_image'])) {
        return url('uploads/payments/' . $payment['proof_image']);
    }
    return null;
}

// Label singkat metode bayar asal (untuk bukti transfer)
function bank_label($bank) {
    return $bank ?: '-';
}
