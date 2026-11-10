<?php
/**
 * ReTaste - API aksi admin atas toko (setuju, tolak, tangguh, aktifkan).
 * POST: action, id_toko, csrf, + (tolak) alasan[] & lainnya, + (tangguh) catatan
 * Perubahan langsung tersimpan ke DB; dashboard penjual menangkapnya lewat api_sync.php.
 */
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');

function out(int $code, array $body): void { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405, ['ok' => false, 'message' => 'Metode tidak diizinkan.']);
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') out(403, ['ok' => false, 'message' => 'Sesi admin tidak valid.']);
if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) out(403, ['ok' => false, 'message' => 'Token keamanan tidak cocok. Muat ulang halaman.']);
session_write_close();

require __DIR__ . '/koneksi.php';
$templates = require __DIR__ . '/template_tolak.php';

$idAdmin = (int)$_SESSION['user_id'];
$adminStmt = $pdo->prepare(
    "SELECT 1 FROM users
     WHERE id_user = ? AND role = 'admin' AND status_akun = 'aktif'
     LIMIT 1"
);
$adminStmt->execute([$idAdmin]);
if (!$adminStmt->fetchColumn()) out(401, ['ok' => false, 'message' => 'Akun admin tidak aktif. Silakan masuk kembali.']);

$action  = (string)($_POST['action'] ?? '');
$idToko  = (int)($_POST['id_toko'] ?? 0);
if ($idToko <= 0) out(422, ['ok' => false, 'message' => 'Toko tidak valid.']);

$stmt = $pdo->prepare('SELECT id_toko, id_user, nama_toko, status FROM toko WHERE id_toko = ?');
$stmt->execute([$idToko]);
$toko = $stmt->fetch();
if (!$toko) out(404, ['ok' => false, 'message' => 'Toko tidak ditemukan.']);

/** Ubah status hanya jika status saat ini cocok (cegah dua admin menimpa keputusan satu sama lain). */
function ganti_status(PDO $pdo, int $idToko, string $dari, string $ke, ?string $catatan, ?int $idAdmin = null): bool {
    $u = $pdo->prepare(
        'UPDATE toko
         SET status = ?, catatan_admin = ?,
             diverifikasi_oleh = COALESCE(?, diverifikasi_oleh),
             diverifikasi_pada = IF(? IS NULL, diverifikasi_pada, NOW())
         WHERE id_toko = ? AND status = ?'
    );
    $u->execute([$ke, $catatan, $idAdmin, $idAdmin, $idToko, $dari]);
    return $u->rowCount() === 1;
}
$konflik = ['ok' => false, 'conflict' => true, 'message' => 'Status toko sudah berubah (mungkin ditangani admin lain). Data diperbarui.'];

try {
    $pdo->beginTransaction();

    if ($action === 'setuju') {
        if (!ganti_status($pdo, $idToko, 'menunggu', 'aktif', null, $idAdmin)) { $pdo->rollBack(); out(409, $konflik); }
        $pdo->prepare(
            "INSERT INTO notifikasi (id_user, tipe, judul, isi, tautan)
             VALUES (?, 'sistem', 'Toko disetujui', 'Toko kamu sudah aktif dan dapat mulai berjualan.', 'dashboard_penjual.php')"
        )->execute([(int)$toko['id_user']]);
        $pdo->commit();
        out(200, ['ok' => true, 'message' => $toko['nama_toko'] . ' disetujui dan kini aktif.']);
    }

    if ($action === 'tolak') {
        $kode = $_POST['alasan'] ?? [];
        $kode = is_array($kode) ? array_values(array_unique(array_map('strval', $kode))) : [];
        $kode = array_values(array_filter($kode, static fn($k) => isset($templates[$k])));
        $lainnya = trim((string)($_POST['lainnya'] ?? ''));
        $lainnya = mb_substr($lainnya, 0, 500);
        $pakaiLainnya = in_array('lainnya', $kode, true);

        if (!$kode) { $pdo->rollBack(); out(422, ['ok' => false, 'message' => 'Pilih minimal satu alasan penolakan.']); }
        if ($pakaiLainnya && mb_strlen($lainnya) < 5) { $pdo->rollBack(); out(422, ['ok' => false, 'message' => 'Catatan untuk opsi Lainnya wajib diisi (minimal 5 karakter).']); }

        // Pesan disusun di server dari kode template; baris per alasan dipisah \n
        $baris = [];
        foreach ($kode as $k) {
            if ($k === 'lainnya') continue;
            $baris[] = $templates[$k][0] . ': ' . $templates[$k][1];
        }
        if ($pakaiLainnya) $baris[] = 'Catatan admin: ' . $lainnya;
        $catatan = implode("\n", $baris);

        if (!ganti_status($pdo, $idToko, 'menunggu', 'ditolak', $catatan, $idAdmin)) { $pdo->rollBack(); out(409, $konflik); }
        $pdo->prepare(
            "INSERT INTO notifikasi (id_user, tipe, judul, isi, tautan)
             VALUES (?, 'sistem', 'Pengajuan toko perlu diperbaiki', ?, 'dashboard_penjual.php')"
        )->execute([(int)$toko['id_user'], mb_substr($catatan, 0, 255)]);
        $pdo->commit();
        out(200, ['ok' => true, 'message' => $toko['nama_toko'] . ' ditolak. Alasan langsung terkirim ke penjual.']);
    }

    if ($action === 'tangguh') {
        $catatan = trim((string)($_POST['catatan'] ?? ''));
        if (mb_strlen($catatan) < 5) { $pdo->rollBack(); out(422, ['ok' => false, 'message' => 'Alasan penangguhan wajib diisi (minimal 5 karakter).']); }
        $catatan = mb_substr($catatan, 0, 500);
        if (!ganti_status($pdo, $idToko, 'aktif', 'ditangguhkan', $catatan)) { $pdo->rollBack(); out(409, $konflik); }

        // Tutup paket yang masih berjalan lalu batalkan pesanan aktifnya
        $pk = $pdo->prepare("UPDATE paket SET status = 'ditangguhkan' WHERE id_toko = ? AND status IN ('tersedia','habis')");
        $pk->execute([$idToko]); $nPaket = $pk->rowCount();
        $ps = $pdo->prepare(
            "UPDATE pesanan o JOIN paket p ON p.id_paket = o.id_paket
             SET o.status = 'dibatalkan', o.alasan_batal = 'Toko ditangguhkan'
             WHERE p.id_toko = ? AND o.status IN ('siap_diambil','menunggu_pembayaran')"
        );
        $ps->execute([$idToko]); $nPesanan = $ps->rowCount();

        $pdo->commit();
        out(200, ['ok' => true, 'message' => $toko['nama_toko'] . " ditangguhkan. $nPaket paket ditutup, $nPesanan pesanan dibatalkan."]);
    }

    if ($action === 'aktifkan') {
        if (!ganti_status($pdo, $idToko, 'ditangguhkan', 'aktif', '')) { $pdo->rollBack(); out(409, $konflik); }
        $pdo->commit();
        out(200, ['ok' => true, 'message' => $toko['nama_toko'] . ' diaktifkan kembali.']);
    }

    $pdo->rollBack();
    out(400, ['ok' => false, 'message' => 'Aksi tidak dikenal.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('api_admin_toko: ' . $e->getMessage());
    out(500, ['ok' => false, 'message' => 'Terjadi kesalahan di server. Coba lagi.']);
}
