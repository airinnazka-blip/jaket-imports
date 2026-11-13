<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$code = trim($_GET['order'] ?? '');
if ($code === '') {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

$user = current_user();
$st = $pdo->prepare('SELECT * FROM orders WHERE order_code = ? AND user_id = ?');
$st->execute([$code, $user['id']]);
$order = $st->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('pesanan.php');
}

$st = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$st->execute([$order['id']]);
$items = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM payments WHERE order_id = ?');
$st->execute([$order['id']]);
$payment = $st->fetch();

$isCod = $order['payment_method'] === 'cod';
$errors = [];

// Sumber daftar bank / e-wallet
$banks = ['BCA', 'BRI', 'Mandiri', 'BNI', 'BSI', 'CIMB Niaga', 'Permata', 'GoPay', 'OVO', 'DANA', 'ShopeePay', 'LinkAja', 'Lainnya'];

// Nilai awal form (dari data tersimpan, atau isi ulang dari POST yang error)
$form = [
    'sender_name' => $_POST['sender_name'] ?? ($payment['sender_name'] ?? ($user['name'] ?? '')),
    'sender_bank' => $_POST['sender_bank'] ?? ($payment['sender_bank'] ?? ''),
    'paid_amount' => $_POST['paid_amount'] ?? ($payment['paid_amount'] ?? $order['total']),
    'transfer_at' => $_POST['transfer_at'] ?? (!empty($payment['transfer_at']) ? date('Y-m-d\TH:i', strtotime($payment['transfer_at'])) : date('Y-m-d\TH:i')),
    'note'        => $_POST['note'] ?? ($payment['note'] ?? ''),
];

// ---- Kirim bukti pembayaran ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if ($isCod) {
        $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $notif->execute([$user['id'], 'Pesanan ' . $order['order_code'] . ' (COD)', 'Pesanan COD dikonfirmasi. Silakan bayar tunai saat barang diterima.']);
        set_flash('success', 'Pesanan COD dikonfirmasi. Bayar tunai saat barang diterima ya.');
        redirect('pesanan-detail.php?order=' . $code);
    }

    $senderName = trim($form['sender_name']);
    $senderBank = trim($form['sender_bank']);
    $paidAmount = (float)$form['paid_amount'];
    $transferAt = trim($form['transfer_at']);
    $note       = trim($form['note']);
    $proofImage = $payment['proof_image'] ?? null;

    if ($senderName === '') $errors[] = 'Nama pengirim wajib diisi.';
    if ($senderBank === '') $errors[] = 'Bank / e-wallet asal wajib dipilih.';
    if ($paidAmount <= 0) $errors[] = 'Nominal transfer wajib diisi.';
    if ($transferAt === '') $errors[] = 'Tanggal & jam transfer wajib diisi.';

    if (!empty($_FILES['proof']['name']) && $_FILES['proof']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['proof']['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) {
            $errors[] = 'Format bukti tidak didukung (JPG, PNG, WEBP, GIF).';
        } elseif ($_FILES['proof']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Ukuran bukti maksimal 3MB.';
        } else {
            $fname = 'proof_' . $order['id'] . '_' . time() . '_' . rand(100, 999) . '.' . $allowed[$mime];
            if (move_uploaded_file($_FILES['proof']['tmp_name'], __DIR__ . '/uploads/payments/' . $fname)) {
                $proofImage = $fname;
            } else {
                $errors[] = 'Gagal mengunggah bukti. Coba lagi.';
            }
        }
    } elseif (empty($payment['proof_image'])) {
        $errors[] = 'Foto bukti transfer wajib diunggah.';
    }

    if (!$errors) {
        $transferAtSql = date('Y-m-d H:i:s', strtotime($transferAt));
        $upd = $pdo->prepare(
            'UPDATE payments SET paid_amount=?, sender_name=?, sender_bank=?, transfer_at=?, proof_image=?, note=? WHERE order_id=?'
        );
        $upd->execute([$paidAmount, $senderName, $senderBank, $transferAtSql, $proofImage, $note, $order['id']]);

        $payment['paid_amount'] = $paidAmount;
        $payment['sender_name'] = $senderName;
        $payment['sender_bank'] = $senderBank;
        $payment['transfer_at'] = $transferAtSql;
        $payment['proof_image'] = $proofImage;
        $payment['note'] = $note;

        $notif = $pdo->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $notif->execute([
            $user['id'],
            'Bukti pembayaran ' . $order['order_code'] . ' diterima',
            'Bukti pembayaran Anda telah kami terima dan sedang menunggu verifikasi admin.'
        ]);

        set_flash('success', 'Bukti pembayaran berhasil dikirim! Admin akan memverifikasi pembayaran Anda.');
        redirect('pesanan-detail.php?order=' . $code);
    }
}

$page_title = 'Pembayaran ' . $code;

$reks = [
    'transfer' => [
        'icon'  => '💳',
        'title' => 'Transfer Bank',
        'rows'  => ['BCA : 8830-1234-5678', 'BRI : 0021-0101-9898-99', 'Mandiri : 1370-0098-7654'],
        'note'  => 'Transfer sesuai nominal total, lalu unggah bukti transfer di bawah agar admin dapat memverifikasi.',
    ],
    'ewallet' => [
        'icon'  => '📱',
        'title' => 'E-Wallet',
        'rows'  => ['Nominal: ' . rupiah($order['total']), 'Atas Nama: Jaket Imports'],
        'note'  => 'Scan QRIS berikut sesuai nominal, lalu unggah tangkapan layar bukti pembayaran.',
    ],
    'cod'     => [
        'icon'  => '🚚',
        'title' => 'COD (Bayar di Tempat)',
        'rows'  => ['Bayar tunai saat barang diterima.'],
        'note'  => 'Tidak perlu bayar di muka. Klik konfirmasi untuk melanjutkan pesanan.',
    ],
];

// Generate QRIS dinamis untuk e-wallet
$qris_img = null;
if ($order['payment_method'] === 'ewallet') {
    $qr_data = 'JAKETIMPORTS|ORDER:' . $order['order_code'] . '|AMOUNT:' . (int)$order['total'];
    $qris_img = 'https://quickchart.io/qr?size=220x220&format=png&margin=2&text=' . urlencode($qr_data);
}

$rk = $reks[$order['payment_method']];
$proofUrl = $payment ? payment_proof_url($payment) : null;
?>
<div class="section-head">
    <h2>💳 Pembayaran</h2>
    <span class="badge badge-primary"><?= e($order['order_code']) ?></span>
</div>

<?php foreach ($errors as $er): ?>
    <div class="alert alert-danger"><?= e($er) ?></div>
<?php endforeach; ?>

<div class="panel-grid">
    <div class="stack">
        <div class="card">
            <div class="card-body">
                <div class="flex-between">
                    <h3 class="card-title"><?= $rk['icon'] ?> <?= e($rk['title']) ?></h3>
                    <?= payment_status_badge($payment) ?>
                </div>

                <div class="card mt-3" style="border:1px dashed var(--border);padding:18px;background:#f8fafc">
                    <?php if ($order['payment_method'] === 'ewallet'): ?>
                        <div class="text-center" style="margin-bottom:12px">
                            <img src="<?= $qris_img ?? url('images/qris-jaketimports.png') ?>" alt="QRIS JAKETIMPORTS"
                                 style="width:220px;max-width:100%;border:8px solid #fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.15)">
                            <p style="font-weight:700;letter-spacing:.5px;margin-top:10px">JAKETIMPORTS
                                <span class="badge badge-success" style="vertical-align:middle">QRIS DINAMIS</span>
                            </p>
                            <p class="text-muted" style="font-size:.85rem">Total yang harus dibayar: <b><?= rupiah($order['total']) ?></b></p>
                            <p class="text-muted" style="font-size:.85rem">Scan kode QRIS di atas dengan e-wallet / mobile banking yang mendukung QRIS</p>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($rk['rows'] as $row): ?>
                        <p style="font-family:Consolas,monospace;font-size:1.02rem;padding:4px 0"><?= e($row) ?></p>
                    <?php endforeach; ?>
                    <p class="text-muted mt-2" style="font-size:.88rem"><?= e($rk['note']) ?></p>
                </div>

                <?php if ($payment && $payment['status'] === 'success'): ?>
                    <p class="text-success mt-3"><b>Pembayaran Anda telah diverifikasi.</b> Admin akan segera memproses pesanan Anda.</p>

                <?php elseif ($isCod): ?>
                    <form method="post" class="mt-3">
                        <?= csrf_field() ?>
                        <button class="btn btn-success btn-lg" type="submit"
                                data-confirm="Konfirmasi pesanan COD ini?">✓ Konfirmasi Pesanan COD</button>
                    </form>

                <?php else: ?>

                    <?php if ($proofUrl): ?>
                        <div class="alert alert-info mt-3" style="display:flex;gap:14px;align-items:flex-start">
                            <img src="<?= e($proofUrl) ?>" alt="Bukti" style="width:110px;border-radius:8px;border:1px solid #bfdbfe">
                            <div>
                                <b>Bukti pembayaran sudah diunggah.</b>
                                <div style="font-size:.9rem;font-weight:500">Menunggu verifikasi admin. Anda masih dapat memperbarui data / foto di bawah.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <h3 class="card-title mt-3">Bukti Pembayaran</h3>
                    <p class="text-muted" style="font-size:.88rem;margin-bottom:12px">Isi detail transfer dengan benar agar admin dapat memverifikasi dengan cepat.</p>

                    <form method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="flex wrap">
                            <div class="form-group" style="flex:1;min-width:200px">
                                <label for="sender_name">Nama Pengirim <span class="text-danger">*</span></label>
                                <input class="form-control" id="sender_name" name="sender_name" value="<?= e($form['sender_name']) ?>" required>
                                <p class="form-hint">Nama sesuai rekening / akun pengirim.</p>
                            </div>
                            <div class="form-group" style="flex:1;min-width:200px">
                                <label for="sender_bank">Bank / E-Wallet Asal <span class="text-danger">*</span></label>
                                <select class="form-control" id="sender_bank" name="sender_bank" required>
                                    <option value="">— Pilih Bank / E-Wallet —</option>
                                    <?php foreach ($banks as $b): ?>
                                        <option value="<?= e($b) ?>" <?= $form['sender_bank'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex wrap">
                            <div class="form-group" style="flex:1;min-width:200px">
                                <label for="paid_amount">Nominal Transfer (Rp) <span class="text-danger">*</span></label>
                                <input class="form-control" id="paid_amount" name="paid_amount" type="number" min="0" step="1000"
                                       value="<?= e((int)round((float)$form['paid_amount'])) ?>" required>
                                <p class="form-hint">Total tagihan: <b><?= rupiah($order['total']) ?></b></p>
                            </div>
                            <div class="form-group" style="flex:1;min-width:200px">
                                <label for="transfer_at">Tanggal &amp; Jam Transfer <span class="text-danger">*</span></label>
                                <input class="form-control" id="transfer_at" name="transfer_at" type="datetime-local" value="<?= e($form['transfer_at']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="proof">Foto Bukti Transfer <span class="text-danger">*</span></label>
                            <input class="form-control" id="proof" name="proof" type="file" accept="image/*" <?= $proofUrl ? '' : 'required' ?>>
                            <p class="form-hint">Unggah foto / tangkapan layar bukti transfer (JPG, PNG, WEBP, GIF maks. 3MB).</p>
                        </div>

                        <div class="form-group">
                            <label for="note">Catatan (Opsional)</label>
                            <textarea class="form-control" id="note" name="note" placeholder="Misal: transfer dari rekening istri / atas nama berbeda"><?= e($form['note']) ?></textarea>
                        </div>

                        <button class="btn btn-success btn-lg btn-block" type="submit">📤 Kirim Bukti Pembayaran</button>
                    </form>

                <?php endif; ?>
            </div>
        </div>

        <a class="text-muted" href="<?= url('pesanan-detail.php?order=' . $code) ?>">← Lihat detail pesanan</a>
    </div>

    <div class="card summary-box">
        <div class="card-body">
            <h3 class="card-title">Ringkasan Tagihan</h3>
            <?php foreach ($items as $it): ?>
                <div class="summary-row">
                    <span><?= e($it['product_name']) ?> × <?= (int)$it['qty'] ?></span>
                    <span><?= rupiah($it['price'] * $it['qty']) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="summary-row">
                <span>Subtotal</span><span><?= rupiah($order['subtotal']) ?></span>
            </div>
            <?php if ((float)$order['discount'] > 0): ?>
                <div class="summary-row text-success">
                    <span>Diskon</span><span>− <?= rupiah($order['discount']) ?></span>
                </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span>Total</span><span><?= rupiah($order['total']) ?></span>
            </div>
            <p class="form-hint mt-2">Metode: <?= e(strtoupper($order['payment_method'])) ?></p>
            <p class="form-hint">Status: <?= payment_status_badge($payment) ?></p>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
