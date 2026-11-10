<?php
/**
 * ReTaste - endpoint sinkronisasi (polling ringan) untuk admin & penjual.
 * GET ?ver=<hash terakhir>. Jika data tidak berubah -> {"changed":false}; jika berubah -> data lengkap + ver baru.
 * Sesi dilepas sebelum query supaya tidak mengunci halaman lain milik pengguna yang sama.
 */
ob_start();                       // tangkap output liar (warning, spasi/BOM dari file include) agar JSON tetap valid
ini_set('display_errors', '0');
session_start();
date_default_timezone_set('Asia/Jakarta');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function kirim(int $code, array $body): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$uid  = (int)($_SESSION['user_id'] ?? 0);
$role = (string)($_SESSION['role'] ?? '');
session_write_close();
if (!$uid || !in_array($role, ['admin', 'penjual'], true)) kirim(401, ['ok' => false, 'error' => 'Sesi habis, login ulang.']);

require __DIR__ . '/koneksi.php';
$verKlien = (string)($_GET['ver'] ?? '');

try {
    if ($role === 'admin') {
        require __DIR__ . '/data_admin.php';
        $data = ambilDataAdmin($pdo);
    } else {
        $st = $pdo->prepare('SELECT id_toko, status, COALESCE(catatan_admin, \'\') AS catatan FROM toko WHERE id_user = ? LIMIT 1');
        $st->execute([$uid]);
        $t = $st->fetch();
        $data = ['toko' => null, 'premium' => false, 'berakhir' => null, 'paket' => [], 'pesanan' => []];
        if ($t) {
            $data['toko'] = ['status' => $t['status'], 'catatan_admin' => $t['catatan']];
            $pr = $pdo->prepare(
                "SELECT MAX(UNIX_TIMESTAMP(l.berakhir_pada)) FROM langganan l
                 JOIN paket_langganan pl ON pl.id_paket_langganan = l.id_paket_langganan
                 WHERE l.id_user = ? AND pl.untuk = 'umkm' AND l.status = 'aktif' AND l.berakhir_pada > NOW()"
            );
            $pr->execute([$uid]);
            $akhir = $pr->fetchColumn();
            $data['premium'] = $akhir !== null && $akhir !== false;
            $data['berakhir'] = $data['premium'] ? (int)$akhir : null;

            $pk = $pdo->prepare('SELECT id_paket AS id, status FROM paket WHERE id_toko = ?');
            $pk->execute([(int)$t['id_toko']]);
            $data['paket'] = array_map(static fn($r) => ['id' => (int)$r['id'], 'status' => $r['status']], $pk->fetchAll());

            $po = $pdo->prepare(
                "SELECT o.id_pesanan AS id, o.status, COALESCE(o.alasan_batal, '') AS alasan
                 FROM pesanan o JOIN paket p ON p.id_paket = o.id_paket WHERE p.id_toko = ?"
            );
            $po->execute([(int)$t['id_toko']]);
            $data['pesanan'] = array_map(static fn($r) => ['id' => (int)$r['id'], 'status' => $r['status'], 'alasan' => $r['alasan']], $po->fetchAll());
        }
    }
    $ver = md5(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    if ($ver === $verKlien) kirim(200, ['ok' => true, 'changed' => false, 'ver' => $ver]);
    kirim(200, ['ok' => true, 'changed' => true, 'ver' => $ver, 'serverNow' => time(), 'data' => $data]);
} catch (Throwable $e) {
    error_log('api_sync: ' . $e->getMessage());
    kirim(500, ['ok' => false, 'error' => $e->getMessage()]);
}
