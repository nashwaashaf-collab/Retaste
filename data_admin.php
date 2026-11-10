<?php
/**
 * ReTaste - kumpulan query data dashboard admin.
 * Dipakai bersama oleh dashboard_admin.php (muat awal) dan api_sync.php (polling real-time),
 * supaya keduanya selalu memakai bentuk data yang sama.
 */
function ambilDataAdmin(PDO $pdo): array
{
    $users = $pdo->query(
        "SELECT id_user AS id, nama, email, role,
                (status_akun = 'aktif') AS aktif, UNIX_TIMESTAMP(created_at) AS dibuat,
                '' AS alasan, NULL AS tangguh_pada
         FROM users ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as &$u) { $u['id'] = (int)$u['id']; $u['aktif'] = (int)$u['aktif']; $u['dibuat'] = (int)$u['dibuat']; }
    unset($u);

    $toko = $pdo->query(
        "SELECT t.id_toko AS id, t.id_user AS owner, t.nama_toko AS nama,
                t.kategori_usaha AS kat, t.alamat, t.lat, t.lng,
                TIME_FORMAT(t.jam_buka, '%H:%i') AS buka,
                TIME_FORMAT(t.jam_tutup, '%H:%i') AS tutup,
                t.no_telp AS telp, t.foto, t.status, COALESCE(t.catatan_admin, '') AS catatan,
                t.rating_rata AS rating, t.jumlah_ulasan AS ulasan,
                EXISTS (
                    SELECT 1 FROM langganan l
                    JOIN paket_langganan pl ON pl.id_paket_langganan = l.id_paket_langganan
                    WHERE l.id_user = t.id_user AND pl.untuk = 'umkm'
                      AND l.status = 'aktif' AND l.berakhir_pada > NOW()
                ) AS premium,
                UNIX_TIMESTAMP(t.created_at) AS dibuat
         FROM toko t ORDER BY t.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($toko as &$s) {
        $s['id'] = (int)$s['id']; $s['owner'] = (int)$s['owner'];
        $s['lat'] = (float)$s['lat']; $s['lng'] = (float)$s['lng'];
        $s['rating'] = (float)$s['rating']; $s['ulasan'] = (int)$s['ulasan'];
        $s['premium'] = (bool)$s['premium']; $s['dibuat'] = (int)$s['dibuat'];
    }
    unset($s);

    $penjualBelumProfil = $pdo->query(
        "SELECT u.id_user AS id, u.nama, u.email, UNIX_TIMESTAMP(u.created_at) AS dibuat
         FROM users u
         LEFT JOIN toko t ON t.id_user = u.id_user
         WHERE u.role = 'penjual' AND u.status_akun = 'aktif' AND t.id_toko IS NULL
         ORDER BY u.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($penjualBelumProfil as &$penjual) {
        $penjual['id'] = (int)$penjual['id'];
        $penjual['dibuat'] = (int)$penjual['dibuat'];
    }
    unset($penjual);

    $paket = $pdo->query(
        'SELECT id_paket AS id, id_toko AS toko, nama_paket AS nama,
                id_kategori AS kat, status, stok_sisa AS stok
         FROM paket ORDER BY created_at DESC'
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($paket as &$p) { $p['id'] = (int)$p['id']; $p['toko'] = (int)$p['toko']; $p['kat'] = (int)$p['kat']; $p['stok'] = (int)$p['stok']; }
    unset($p);

    $pesanan = $pdo->query(
        "SELECT o.id_pesanan AS id, o.kode_pesanan AS kode, t.id_toko AS toko,
                p.id_paket AS paket, o.id_pembeli AS pembeli, o.jumlah,
                o.subtotal, o.ppn_nominal AS pajak, o.total_bayar AS total, o.status,
                UNIX_TIMESTAMP(o.created_at) AS ts, COALESCE(o.alasan_batal, '') AS alasan
         FROM pesanan o
         JOIN paket p ON p.id_paket = o.id_paket
         JOIN toko t ON t.id_toko = p.id_toko
         ORDER BY o.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pesanan as &$o) {
        foreach (['id', 'toko', 'paket', 'pembeli', 'jumlah', 'subtotal', 'pajak', 'total', 'ts'] as $f) $o[$f] = (int)$o[$f];
    }
    unset($o);

    $laporan = $pdo->query(
        "SELECT l.id_laporan AS id, o.kode_pesanan AS kode, l.id_pelapor AS pelapor,
                t.id_toko AS toko, p.id_paket AS paket, l.jenis, l.alasan, l.foto,
                l.status, l.tindakan_admin AS tindakan, COALESCE(l.catatan_admin, '') AS catatan,
                UNIX_TIMESTAMP(l.created_at) AS dibuat, UNIX_TIMESTAMP(l.ditangani_pada) AS ditangani
         FROM laporan l
         JOIN pesanan o ON o.id_pesanan = l.id_pesanan
         JOIN paket p ON p.id_paket = o.id_paket
         JOIN toko t ON t.id_toko = p.id_toko
         ORDER BY l.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($laporan as &$r) {
        foreach (['id', 'pelapor', 'toko', 'paket', 'dibuat'] as $f) $r[$f] = (int)$r[$f];
        $r['ditangani'] = $r['ditangani'] === null ? null : (int)$r['ditangani'];
    }
    unset($r);

    // Pemasukan dari pembelian ReTaste Pro di dalam aplikasi
    $pro = $pdo->query(
        "SELECT l.id_langganan AS id, CONCAT('PRO-', LPAD(l.id_langganan, 8, '0')) AS kode,
                l.id_user AS owner, COALESCE(t.nama_toko, u.nama) AS toko, u.nama AS pemilik,
                l.harga_dibayar AS jumlah, pl.durasi_hari AS durasi,
                COALESCE(l.metode_bayar, '-') AS metode,
                CASE
                    WHEN l.waktu_bayar IS NULL THEN 'menunggu'
                    WHEN l.status IN ('aktif', 'berakhir') THEN 'berhasil'
                    ELSE 'gagal'
                END AS status,
                UNIX_TIMESTAMP(COALESCE(l.waktu_bayar, l.created_at)) AS ts,
                UNIX_TIMESTAMP(COALESCE(
                    l.berakhir_pada,
                    DATE_ADD(l.created_at, INTERVAL pl.durasi_hari DAY)
                )) AS berakhir
         FROM langganan l
         JOIN paket_langganan pl ON pl.id_paket_langganan = l.id_paket_langganan
         JOIN users u ON u.id_user = l.id_user
         LEFT JOIN toko t ON t.id_user = l.id_user
         ORDER BY l.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pro as &$b) { foreach (['id', 'owner', 'jumlah', 'durasi', 'ts', 'berakhir'] as $f) $b[$f] = (int)$b[$f]; }
    unset($b);

    return ['users' => $users, 'toko' => $toko, 'penjualBelumProfil' => $penjualBelumProfil, 'paket' => $paket, 'pesanan' => $pesanan, 'laporan' => $laporan, 'pro' => $pro];
}
