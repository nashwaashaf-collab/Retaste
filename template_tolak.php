<?php
/**
 * Template alasan penolakan toko. Dipakai dashboard admin (tampilan) dan
 * api_admin_toko.php (validasi + penyusunan pesan di server, jadi klien tidak bisa memalsukan teks).
 * Untuk menambah opsi: cukup tambah baris baru di array ini.
 */
return [
    'foto_buruk'      => ['Foto toko tidak jelas', 'Foto toko buram, gelap, atau tidak memperlihatkan tampak depan usaha. Unggah foto yang jelas.'],
    'foto_tidak_sesuai' => ['Foto tidak sesuai usaha', 'Foto yang diunggah tidak sesuai dengan jenis usaha atau bukan foto milik toko sendiri.'],
    'alamat_tidak_jelas' => ['Alamat kurang lengkap', 'Alamat kurang lengkap atau tidak sesuai titik lokasi. Lengkapi nama jalan, nomor, dan patokan.'],
    'lokasi_salah'    => ['Titik lokasi tidak sesuai', 'Titik lokasi di peta tidak cocok dengan alamat yang ditulis.'],
    'telp_tidak_valid' => ['Nomor telepon tidak bisa dihubungi', 'Nomor telepon toko tidak valid atau tidak dapat dihubungi. Perbarui dengan nomor aktif.'],
    'jam_tidak_valid' => ['Jam operasional tidak wajar', 'Jam buka dan tutup tidak wajar atau tidak sesuai jenis usaha. Periksa kembali.'],
    'nama_tidak_pantas' => ['Nama toko tidak sesuai ketentuan', 'Nama toko mengandung unsur yang tidak diperbolehkan atau menyesatkan. Gunakan nama usaha yang sebenarnya.'],
    'data_ganda'      => ['Toko sudah terdaftar', 'Toko ini terindikasi sudah terdaftar sebelumnya. Hubungi admin bila ini kekeliruan.'],
    'bukan_makanan'   => ['Bukan usaha makanan/minuman', 'Jenis usaha belum sesuai dengan layanan ReTaste yang menyelamatkan makanan dan minuman.'],
    'lainnya'         => ['Lainnya (tulis catatan sendiri)', ''],
];
