<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function subscriptionResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
    empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'penjual') {
    subscriptionResponse(401, ['ok' => false, 'message' => 'Silakan masuk dengan akun penjual.']);
}

$csrf = (string)($_POST['csrf'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $csrf)) {
    subscriptionResponse(403, ['ok' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.']);
}

$method = trim((string)($_POST['metode_bayar'] ?? ''));
$methods = ['QRIS (simulasi)', 'E-wallet (simulasi)', 'Transfer bank (simulasi)'];
if (!in_array($method, $methods, true)) {
    subscriptionResponse(422, ['ok' => false, 'message' => 'Metode pembayaran tidak valid.']);
}

try {
    require __DIR__ . '/koneksi.php';
    $userId = (int)$_SESSION['user_id'];
    $pdo->beginTransaction();

    $account = $pdo->prepare("SELECT id_user FROM users WHERE id_user = ? AND role = 'penjual' FOR UPDATE");
    $account->execute([$userId]);
    if (!$account->fetchColumn()) {
        $pdo->rollBack();
        subscriptionResponse(401, ['ok' => false, 'message' => 'Akun penjual tidak ditemukan.']);
    }

    $plan = $pdo->query(
        "SELECT id_paket_langganan, harga, durasi_hari
         FROM paket_langganan
         WHERE untuk = 'umkm' AND nama = 'Rejeki Pro' AND aktif = 1
         ORDER BY id_paket_langganan
         LIMIT 1
         FOR UPDATE"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$plan || (int)$plan['harga'] < 1 || (int)$plan['durasi_hari'] < 1) {
        $pdo->rollBack();
        error_log('Active Rejeki Pro plan is missing or has invalid pricing.');
        subscriptionResponse(503, ['ok' => false, 'message' => 'Paket Premium belum siap dibeli. Hubungi admin.']);
    }

    $latestEnd = $pdo->prepare(
        "SELECT MAX(l.berakhir_pada)
         FROM langganan l
         JOIN paket_langganan pl ON pl.id_paket_langganan = l.id_paket_langganan
         WHERE l.id_user = ? AND pl.untuk = 'umkm' AND l.status = 'aktif'
           AND l.berakhir_pada > NOW()"
    );
    $latestEnd->execute([$userId]);
    $currentEnd = $latestEnd->fetchColumn();
    $startsAt = new DateTimeImmutable($currentEnd ? (string)$currentEnd : 'now');
    $endsAt = $startsAt->modify('+' . (int)$plan['durasi_hari'] . ' days');

    $insert = $pdo->prepare(
        "INSERT INTO langganan
           (id_user, id_paket_langganan, status, harga_dibayar, metode_bayar,
            waktu_bayar, mulai_pada, berakhir_pada)
         VALUES (?, ?, 'aktif', ?, ?, NOW(), ?, ?)"
    );
    $insert->execute([
        $userId,
        (int)$plan['id_paket_langganan'],
        (int)$plan['harga'],
        $method,
        $startsAt->format('Y-m-d H:i:s'),
        $endsAt->format('Y-m-d H:i:s'),
    ]);

    $pdo->commit();
    subscriptionResponse(200, [
        'ok' => true,
        'message' => 'Pembayaran simulasi berhasil. ReTaste Pro aktif.',
        'premium' => true,
        'harga' => (int)$plan['harga'],
        'berakhir_pada' => $endsAt->getTimestamp(),
    ]);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Premium subscription purchase failed: ' . $exception->getMessage());
    subscriptionResponse(500, ['ok' => false, 'message' => 'Pembelian Premium gagal diproses. Silakan coba lagi.']);
}
