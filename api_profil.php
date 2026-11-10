<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function respond(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_GET['action'] ?? '') !== 'simpan' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Aksi profil tidak ditemukan.']);
}
if (($_SESSION['role'] ?? '') !== 'pembeli' || empty($_SESSION['user_id'])) {
    respond(401, ['ok' => false, 'message' => 'Silakan masuk dengan akun pembeli.']);
}
if (empty($_SESSION['csrf']) || !isset($_POST['csrf'])
    || !hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf'])) {
    respond(403, ['ok' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.']);
}

try {
    require __DIR__ . '/koneksi.php';
} catch (Throwable $exception) {
    error_log('Buyer profile API database connection failed: ' . $exception->getMessage());
    respond(503, ['ok' => false, 'message' => 'Database sedang tidak dapat diakses.']);
}

$name = trim((string)($_POST['nama'] ?? ''));
$phone = trim((string)($_POST['telp'] ?? ''));
if (mb_strlen($name) < 2 || mb_strlen($name) > 100
    || ($phone !== '' && !preg_match('/^[0-9+\-\s().]{8,20}$/', $phone))) {
    respond(422, ['ok' => false, 'message' => 'Periksa nama dan nomor telepon.']);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE users SET nama = ?, no_telp = ?
         WHERE id_user = ? AND role = 'pembeli' AND status_akun = 'aktif'"
    );
    $stmt->execute([$name, $phone === '' ? null : $phone, (int)$_SESSION['user_id']]);
    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare("SELECT 1 FROM users WHERE id_user = ? AND role = 'pembeli' AND status_akun = 'aktif'");
        $check->execute([(int)$_SESSION['user_id']]);
        if (!$check->fetchColumn()) respond(404, ['ok' => false, 'message' => 'Akun pembeli tidak ditemukan atau tidak aktif.']);
    }
    $_SESSION['nama'] = $name;
    respond(200, [
        'ok' => true,
        'message' => 'Perubahan profil berhasil disimpan.',
        'user' => ['nama' => $name, 'no_telp' => $phone],
    ]);
} catch (Throwable $exception) {
    error_log('Buyer profile save failed: ' . $exception->getMessage());
    respond(500, ['ok' => false, 'message' => 'Profil gagal disimpan. Coba lagi.']);
}
