<?php
/**
 * ReTaste - API ulasan pembeli.
 * POST api_ulasan.php   (csrf, pesanan_id, rating 1-5, komentar opsional)
 * Ulasan disimpan ke tabel `ulasan`, lalu rating toko (rating_rata & jumlah_ulasan) dihitung ulang
 * dari SEMUA ulasan, jadi rata-rata terakumulasi otomatis dan tetap ada setelah halaman dimuat ulang.
 */
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['ok' => false, 'message' => 'Metode permintaan tidak diizinkan.']);
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') respond(401, ['ok' => false, 'message' => 'Silakan masuk dengan akun pembeli.']);
if (empty($_SESSION['csrf']) || !isset($_POST['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf'])) {
    respond(403, ['ok' => false, 'message' => 'Sesi berakhir. Muat ulang halaman lalu coba lagi.']);
}

$orderId = filter_var($_POST['pesanan_id'] ?? null, FILTER_VALIDATE_INT);
$rating  = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$comment = trim((string)($_POST['komentar'] ?? ''));
if ($orderId === false || $orderId === null || $orderId < 1 || $rating === false || $rating === null || $rating < 1 || $rating > 5) {
    respond(422, ['ok' => false, 'message' => 'Pilih rating 1 sampai 5 bintang.']);
}
if (mb_strlen($comment) > 1000) respond(422, ['ok' => false, 'message' => 'Komentar maksimal 1000 karakter.']);

try {
    require __DIR__ . '/koneksi.php';
} catch (Throwable $e) {
    error_log('Review API database connection failed: ' . $e->getMessage());
    respond(503, ['ok' => false, 'message' => 'Database sedang tidak dapat diakses.']);
}

$userId = (int)$_SESSION['user_id'];
$activeCheck = $pdo->prepare("SELECT 1 FROM users WHERE id_user = ? AND role = 'pembeli' AND status_akun = 'aktif'");
$activeCheck->execute([$userId]);
if (!$activeCheck->fetchColumn()) respond(403, ['ok' => false, 'message' => 'Akun ini ditangguhkan oleh admin.']);
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare(
        "SELECT o.status, o.id_paket, p.id_toko, p.nama_paket, t.id_user AS penjual
         FROM pesanan o JOIN paket p ON p.id_paket = o.id_paket JOIN toko t ON t.id_toko = p.id_toko
         WHERE o.id_pesanan = ? AND o.id_pembeli = ? FOR UPDATE"
    );
    $st->execute([$orderId, $userId]);
    $o = $st->fetch();
    if (!$o) { $pdo->rollBack(); respond(404, ['ok' => false, 'message' => 'Pesanan tidak ditemukan.']); }
    if ($o['status'] !== 'selesai') { $pdo->rollBack(); respond(409, ['ok' => false, 'message' => 'Ulasan hanya bisa diberikan setelah pesanan selesai.']); }

    $ex = $pdo->prepare('SELECT 1 FROM ulasan WHERE id_pesanan = ?');
    $ex->execute([$orderId]);
    if ($ex->fetchColumn()) { $pdo->rollBack(); respond(409, ['ok' => false, 'message' => 'Pesanan ini sudah kamu ulas.']); }

    $pdo->prepare('INSERT INTO ulasan (id_pesanan, id_toko, id_pembeli, rating, komentar) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, (int)$o['id_toko'], $userId, $rating, $comment !== '' ? $comment : null]);

    /* hitung ulang dari semua ulasan toko (bukan menambah angka lama) */
    $pdo->prepare(
        'UPDATE toko SET
            rating_rata   = COALESCE((SELECT ROUND(AVG(rating), 2) FROM ulasan WHERE id_toko = ?), 0),
            jumlah_ulasan = (SELECT COUNT(*) FROM ulasan WHERE id_toko = ?)
         WHERE id_toko = ?'
    )->execute([(int)$o['id_toko'], (int)$o['id_toko'], (int)$o['id_toko']]);

    $t = $pdo->prepare('SELECT rating_rata, jumlah_ulasan FROM toko WHERE id_toko = ?');
    $t->execute([(int)$o['id_toko']]);
    $toko = $t->fetch();

    $pk = $pdo->prepare(
        'SELECT COALESCE(ROUND(AVG(u.rating), 2), 0) AS rata, COUNT(*) AS jumlah
         FROM ulasan u JOIN pesanan x ON x.id_pesanan = u.id_pesanan WHERE x.id_paket = ?'
    );
    $pk->execute([(int)$o['id_paket']]);
    $paket = $pk->fetch();

    $pdo->prepare("INSERT INTO notifikasi (id_user, tipe, judul, isi) VALUES (?, 'sistem', 'Ulasan baru', ?)")
        ->execute([(int)$o['penjual'], mb_substr($rating . ' bintang untuk ' . $o['nama_paket'] . ($comment !== '' ? ': ' . $comment : ''), 0, 255)]);
    $pdo->commit();

    respond(201, [
        'ok' => true,
        'message' => 'Terima kasih, ulasanmu tersimpan.',
        'ulasan' => ['rating' => $rating, 'komentar' => $comment],
        'toko'   => ['id' => (int)$o['id_toko'], 'rating' => (float)$toko['rating_rata'], 'jumlah' => (int)$toko['jumlah_ulasan']],
        'paket'  => ['id' => (int)$o['id_paket'], 'rating' => (float)$paket['rata'], 'jumlah' => (int)$paket['jumlah']],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Review API failed: ' . $e->getMessage());
    respond(500, ['ok' => false, 'message' => 'Ulasan gagal disimpan. Coba lagi.']);
}