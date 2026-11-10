<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function buyerResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    buyerResponse(405, ['ok' => false, 'message' => 'Metode permintaan tidak diizinkan.']);
}
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
    buyerResponse(401, ['ok' => false, 'message' => 'Silakan masuk dengan akun pembeli.']);
}
if (empty($_SESSION['csrf']) || !isset($_POST['csrf'])
    || !hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf'])) {
    buyerResponse(403, ['ok' => false, 'message' => 'Sesi berakhir. Muat ulang halaman lalu coba lagi.']);
}

try {
    require __DIR__ . '/koneksi.php';
} catch (Throwable $exception) {
    error_log('Buyer API database connection failed: ' . $exception->getMessage());
    buyerResponse(503, ['ok' => false, 'message' => 'Database sedang tidak dapat diakses.']);
}

$userId = (int)$_SESSION['user_id'];
$action = (string)($_POST['action'] ?? '');
$account = $pdo->prepare("SELECT 1 FROM users WHERE id_user = ? AND role = 'pembeli' AND status_akun = 'aktif'");
$account->execute([$userId]);
if (!$account->fetchColumn()) {
    buyerResponse(401, ['ok' => false, 'message' => 'Akun pembeli tidak ditemukan atau tidak aktif.']);
}

if ($action === 'favorite') {
    $storeId = filter_var($_POST['id_toko'] ?? null, FILTER_VALIDATE_INT);
    $favorite = filter_var($_POST['favorite'] ?? null, FILTER_VALIDATE_INT);
    if ($storeId === false || $storeId === null || $storeId < 1 || !in_array($favorite, [0, 1], true)) {
        buyerResponse(422, ['ok' => false, 'message' => 'Toko favorit tidak valid.']);
    }
    try {
        $store = $pdo->prepare("SELECT 1 FROM toko WHERE id_toko = ? AND status = 'aktif'");
        $store->execute([$storeId]);
        if (!$store->fetchColumn()) buyerResponse(404, ['ok' => false, 'message' => 'Toko tidak ditemukan atau tidak aktif.']);

        if ($favorite === 1) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO toko_favorit (id_user, id_toko) VALUES (?, ?)');
        } else {
            $stmt = $pdo->prepare('DELETE FROM toko_favorit WHERE id_user = ? AND id_toko = ?');
        }
        $stmt->execute([$userId, $storeId]);

        $favorites = $pdo->prepare('SELECT id_toko FROM toko_favorit WHERE id_user = ? ORDER BY created_at DESC');
        $favorites->execute([$userId]);
        buyerResponse(200, [
            'ok' => true,
            'message' => $favorite ? 'Toko ditambahkan ke favorit.' : 'Toko dihapus dari favorit.',
            'favorites' => array_map('intval', $favorites->fetchAll(PDO::FETCH_COLUMN)),
        ]);
    } catch (Throwable $exception) {
        error_log('Buyer favorite update failed: ' . $exception->getMessage());
        buyerResponse(500, ['ok' => false, 'message' => 'Toko favorit gagal diperbarui. Coba lagi.']);
    }
}

if ($action === 'report') {
    $orderId = filter_var($_POST['pesanan_id'] ?? null, FILTER_VALIDATE_INT);
    $type = (string)($_POST['jenis'] ?? '');
    $reason = trim((string)($_POST['alasan'] ?? ''));
    $validTypes = ['isi_tidak_sesuai', 'tidak_layak', 'alergen', 'penjual_tidak_melayani', 'lainnya'];
    if ($orderId === false || $orderId === null || $orderId < 1
        || !in_array($type, $validTypes, true) || mb_strlen($reason) < 5 || mb_strlen($reason) > 2000) {
        buyerResponse(422, ['ok' => false, 'message' => 'Periksa pesanan, jenis laporan, dan alasan (5–2.000 karakter).']);
    }

    $upload = $_FILES['foto'] ?? null;
    $photoPath = null;
    $photoName = null;
    if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$upload['tmp_name'])) {
            buyerResponse(422, ['ok' => false, 'message' => 'Foto laporan gagal diunggah. Pilih ulang foto JPG atau PNG.']);
        }
        if ((int)$upload['size'] < 1 || (int)$upload['size'] > 2 * 1024 * 1024) {
            buyerResponse(422, ['ok' => false, 'message' => 'Ukuran foto laporan maksimal 2 MB.']);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
        if ($extension === null) buyerResponse(422, ['ok' => false, 'message' => 'Foto laporan harus berupa JPG atau PNG yang valid.']);

        $directory = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'laporan';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Buyer report upload directory could not be created.');
            buyerResponse(500, ['ok' => false, 'message' => 'Penyimpanan foto laporan sedang bermasalah.']);
        }
        $photoName = bin2hex(random_bytes(16)) . '.' . $extension;
        $photoPath = $directory . DIRECTORY_SEPARATOR . $photoName;
        if (!move_uploaded_file((string)$upload['tmp_name'], $photoPath)) {
            error_log('Buyer report photo could not be stored.');
            buyerResponse(500, ['ok' => false, 'message' => 'Foto laporan gagal disimpan. Coba lagi.']);
        }
        $photoName = 'storage/laporan/' . $photoName;
    }

    try {
        $pdo->beginTransaction();
        $order = $pdo->prepare('SELECT status FROM pesanan WHERE id_pesanan = ? AND id_pembeli = ? FOR UPDATE');
        $order->execute([$orderId, $userId]);
        $status = $order->fetchColumn();
        if ($status === false) {
            $pdo->rollBack();
            if ($photoPath !== null && is_file($photoPath) && !unlink($photoPath)) error_log('Buyer report upload cleanup failed.');
            buyerResponse(404, ['ok' => false, 'message' => 'Pesanan tidak ditemukan.']);
        }
        if ($status !== 'selesai') {
            $pdo->rollBack();
            if ($photoPath !== null && is_file($photoPath) && !unlink($photoPath)) error_log('Buyer report upload cleanup failed.');
            buyerResponse(409, ['ok' => false, 'message' => 'Laporan hanya dapat dikirim untuk pesanan yang selesai.']);
        }
        $existing = $pdo->prepare('SELECT 1 FROM laporan WHERE id_pesanan = ? AND id_pelapor = ?');
        $existing->execute([$orderId, $userId]);
        if ($existing->fetchColumn()) {
            $pdo->rollBack();
            if ($photoPath !== null && is_file($photoPath) && !unlink($photoPath)) error_log('Buyer report upload cleanup failed.');
            buyerResponse(409, ['ok' => false, 'message' => 'Laporan untuk pesanan ini sudah pernah dikirim.']);
        }

        $insert = $pdo->prepare('INSERT INTO laporan (id_pesanan, id_pelapor, jenis, alasan, foto) VALUES (?, ?, ?, ?, ?)');
        $insert->execute([$orderId, $userId, $type, $reason, $photoName]);
        $pdo->commit();
        buyerResponse(201, ['ok' => true, 'message' => 'Laporan terkirim. Admin akan meninjaunya.']);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($photoPath !== null && is_file($photoPath) && !unlink($photoPath)) {
            error_log('Buyer report upload cleanup failed.');
        }
        error_log('Buyer report submission failed: ' . $exception->getMessage());
        buyerResponse(500, ['ok' => false, 'message' => 'Laporan gagal disimpan. Coba lagi.']);
    }
}

buyerResponse(404, ['ok' => false, 'message' => 'Aksi tidak ditemukan.']);
