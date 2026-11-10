<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Jakarta');

header('Cache-Control: no-store, private');

function verificationResponse(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function verificationCsrf(): void
{
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], (string)$_POST['csrf'])) {
        verificationResponse(403, ['ok' => false, 'message' => 'Sesi formulir kedaluwarsa. Muat ulang halaman.']);
    }
}

function verificationAdmin(PDO $pdo): bool
{
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') return false;
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id_user = ? AND role = 'admin' AND status_akun = 'aktif'");
    $stmt->execute([(int)$_SESSION['user_id']]);
    return (bool)$stmt->fetchColumn();
}

require __DIR__ . '/koneksi.php';
$action = (string)($_GET['action'] ?? '');

if ($action === 'gambar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!verificationAdmin($pdo)) {
        http_response_code(403);
        exit;
    }
    $stmt = $pdo->prepare('SELECT file_bukti FROM verifikasi_mahasiswa WHERE id_verifikasi = ?');
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
    $filename = $stmt->fetchColumn();
    if (!$filename || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', (string)$filename)) {
        http_response_code(404);
        exit;
    }
    $path = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bukti_sks' . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        http_response_code(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($action === 'notifikasi' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!verificationAdmin($pdo)) verificationResponse(403, ['ok' => false, 'message' => 'Akses admin diperlukan.']);
    $adminId = (int)$_SESSION['user_id'];
    $count = $pdo->prepare(
        "SELECT COUNT(*) FROM notifikasi
         WHERE id_user = ? AND judul = 'Pengajuan verifikasi mahasiswa' AND dibaca = 0"
    );
    $count->execute([$adminId]);
    $rows = $pdo->prepare(
        "SELECT id_notifikasi AS id, judul, isi, dibaca, created_at
         FROM notifikasi
         WHERE id_user = ? AND judul = 'Pengajuan verifikasi mahasiswa'
         ORDER BY id_notifikasi DESC
         LIMIT 20"
    );
    $rows->execute([$adminId]);
    verificationResponse(200, [
        'ok' => true,
        'unread_count' => (int)$count->fetchColumn(),
        'notifikasi' => $rows->fetchAll(),
    ]);
}

if ($action === 'baca-notifikasi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificationAdmin($pdo)) verificationResponse(403, ['ok' => false, 'message' => 'Akses admin diperlukan.']);
    verificationCsrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) verificationResponse(422, ['ok' => false, 'message' => 'Notifikasi tidak valid.']);
    $stmt = $pdo->prepare(
        "UPDATE notifikasi SET dibaca = 1
         WHERE id_notifikasi = ? AND id_user = ? AND judul = 'Pengajuan verifikasi mahasiswa'"
    );
    $stmt->execute([$id, (int)$_SESSION['user_id']]);
    verificationResponse(200, ['ok' => true]);
}

if ($action === 'daftar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!verificationAdmin($pdo)) verificationResponse(403, ['ok' => false, 'message' => 'Akses admin diperlukan.']);
    $rows = $pdo->query(
        "SELECT v.id_verifikasi AS id, u.nama, u.email, v.status, v.catatan_admin,
                v.created_at, v.ditinjau_pada
         FROM verifikasi_mahasiswa v
         JOIN users u ON u.id_user = v.id_user
         WHERE v.id_verifikasi = (
             SELECT MAX(v2.id_verifikasi) FROM verifikasi_mahasiswa v2 WHERE v2.id_user = v.id_user
         )
         ORDER BY (v.status = 'menunggu') DESC, v.created_at DESC"
    )->fetchAll();
    verificationResponse(200, ['ok' => true, 'pengajuan' => $rows]);
}

if ($action === 'ajukan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'pembeli') {
        verificationResponse(401, ['ok' => false, 'message' => 'Sesi berakhir. Silakan masuk kembali.']);
    }
    verificationCsrf();
    $uid = (int)$_SESSION['user_id'];
    $upload = $_FILES['bukti_sks'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = is_array($upload) ? (int)$upload['error'] : UPLOAD_ERR_NO_FILE;
        $message = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Ukuran gambar melebihi batas 5 MB.'
            : 'Pilih gambar bukti SKS/KRS semester ini.';
        verificationResponse(422, ['ok' => false, 'message' => $message]);
    }
    if (!is_uploaded_file($upload['tmp_name']) || $upload['size'] < 1 || $upload['size'] > 5 * 1024 * 1024) {
        verificationResponse(422, ['ok' => false, 'message' => 'Gambar harus berukuran maksimal 5 MB.']);
    }
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!isset($extensions[$mime])) verificationResponse(422, ['ok' => false, 'message' => 'Format bukti harus JPG, PNG, atau WEBP.']);

    $directory = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bukti_sks';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        error_log('Student verification upload directory could not be created.');
        verificationResponse(500, ['ok' => false, 'message' => 'Penyimpanan bukti sedang bermasalah. Hubungi admin.']);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($upload['tmp_name'], $path)) {
        error_log('Student verification upload could not be stored.');
        verificationResponse(500, ['ok' => false, 'message' => 'Gambar gagal disimpan. Silakan coba lagi.']);
    }

    try {
        $pdo->beginTransaction();
        $account = $pdo->prepare("SELECT id_user FROM users WHERE id_user = ? AND role = 'pembeli' AND status_akun = 'aktif' FOR UPDATE");
        $account->execute([$uid]);
        if (!$account->fetchColumn()) {
            $pdo->rollBack();
            if (!unlink($path)) error_log('Student verification upload cleanup failed.');
            verificationResponse(401, ['ok' => false, 'message' => 'Akun tidak ditemukan atau tidak aktif.']);
        }
        $latest = $pdo->prepare('SELECT status FROM verifikasi_mahasiswa WHERE id_user = ? ORDER BY id_verifikasi DESC LIMIT 1');
        $latest->execute([$uid]);
        $previousStatus = $latest->fetchColumn();
        if ($previousStatus === 'menunggu' || $previousStatus === 'disetujui') {
            $pdo->rollBack();
            if (!unlink($path)) error_log('Duplicate student verification upload cleanup failed.');
            verificationResponse(409, [
                'ok' => false,
                'message' => $previousStatus === 'menunggu'
                    ? 'Pengajuanmu masih menunggu pemeriksaan admin.'
                    : 'Status mahasiswa sudah disetujui.',
            ]);
        }
        $pdo->prepare('INSERT INTO verifikasi_mahasiswa (id_user, file_bukti) VALUES (?, ?)')->execute([$uid, $filename]);
        $pdo->prepare('UPDATE users SET email_kampus_terverifikasi_pada = NULL, token_email_kampus = NULL WHERE id_user = ?')->execute([$uid]);
        $pdo->prepare(
            "INSERT INTO notifikasi (id_user, tipe, judul, isi, tautan)
             SELECT id_user, 'sistem', 'Pengajuan verifikasi mahasiswa',
                    'Ada pengajuan bukti SKS mahasiswa baru yang perlu diperiksa.',
                    'dashboard_admin.php#mahasiswa'
             FROM users
             WHERE role = 'admin' AND status_akun = 'aktif'"
        )->execute();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (is_file($path) && !unlink($path)) error_log('Failed to clean up student verification upload.');
        error_log('Student verification submission failed: ' . $exception->getMessage());
        verificationResponse(500, ['ok' => false, 'message' => 'Pengajuan gagal disimpan. Silakan coba lagi.']);
    }
    verificationResponse(200, ['ok' => true, 'status' => 'menunggu', 'message' => 'Bukti SKS berhasil dikirim dan menunggu pemeriksaan admin.']);
}

if ($action === 'tinjau' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificationAdmin($pdo)) verificationResponse(403, ['ok' => false, 'message' => 'Akses admin diperlukan.']);
    verificationCsrf();
    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? '');
    $note = trim((string)($_POST['catatan'] ?? ''));
    if ($id < 1 || !in_array($status, ['disetujui', 'ditolak'], true)) {
        verificationResponse(422, ['ok' => false, 'message' => 'Keputusan pengajuan tidak valid.']);
    }
    if (($status === 'ditolak' && mb_strlen($note) < 5) || mb_strlen($note) > 500) {
        verificationResponse(422, ['ok' => false, 'message' => $status === 'ditolak'
            ? 'Alasan penolakan wajib diisi (minimal 5 karakter).'
            : 'Catatan maksimal 500 karakter.']);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id_user, status FROM verifikasi_mahasiswa WHERE id_verifikasi = ? FOR UPDATE');
        $stmt->execute([$id]);
        $application = $stmt->fetch();
        if (!$application || $application['status'] !== 'menunggu') {
            $pdo->rollBack();
            verificationResponse(409, ['ok' => false, 'message' => 'Pengajuan sudah ditinjau atau tidak ditemukan. Muat ulang daftar.']);
        }
        $pdo->prepare(
            'UPDATE verifikasi_mahasiswa SET status = ?, catatan_admin = ?, ditinjau_oleh = ?, ditinjau_pada = NOW() WHERE id_verifikasi = ?'
        )->execute([$status, $status === 'ditolak' ? $note : null, (int)$_SESSION['user_id'], $id]);
        $verifiedAt = $status === 'disetujui' ? date('Y-m-d H:i:s') : null;
        $pdo->prepare('UPDATE users SET email_kampus_terverifikasi_pada = ? WHERE id_user = ?')->execute([$verifiedAt, (int)$application['id_user']]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Student verification review failed: ' . $exception->getMessage());
        verificationResponse(500, ['ok' => false, 'message' => 'Keputusan gagal disimpan. Silakan coba lagi.']);
    }
    verificationResponse(200, ['ok' => true, 'message' => $status === 'disetujui' ? 'Pengajuan mahasiswa disetujui.' : 'Pengajuan mahasiswa ditolak.']);
}

verificationResponse(404, ['ok' => false, 'message' => 'Aksi tidak ditemukan.']);
