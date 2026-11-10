# Database ReTaste

The project uses the `rejeki_sore` MySQL/MariaDB database. The schema and
required default settings, categories, and subscription plans are in
[`rejeki_sore.sql`](./rejeki_sore.sql). It does not insert sample accounts,
shops, packages, or transactions.

## Local XAMPP setup

1. Start Apache and MySQL in the XAMPP Control Panel.
2. Create an empty database named
   `rejeki_sore` in phpMyAdmin, select it, and import `rejeki_sore.sql`.
3. Open `http://localhost/retaste/`. Registration and login now use the
   `users` table. The default local connection is `127.0.0.1`, user `root`,
   and an empty password. Override these using `REJEKI_DB_HOST`,
   `REJEKI_DB_NAME`, `REJEKI_DB_USER`, and `REJEKI_DB_PASSWORD` as needed.

   For an existing database, apply the SQL files in `database/migrations/` once.
   Rejeki Pro for UMKM costs Rp29,999 per 30 days. Its demo payment is recorded
   as platform revenue in the admin dashboard; use a payment gateway integration
   before accepting real payments.
   Campus email verification uses PHP `mail()`. Configure the mail transport in
   PHP/XAMPP for delivery; on `localhost`, the profile page provides a temporary
   development link if sending fails. Set `REJEKI_APP_URL` to the site's public
   base URL and `REJEKI_MAIL_FROM` to a valid sender address when deploying.

The SQL file drops and recreates its tables and views, clearing existing
records. Import it only into a new/empty database or after backing up any data
you need to keep. After import, dashboards remain empty until real records are
created. Register buyer or seller accounts through the application; provision
an admin account separately through a trusted database administrator.

## Alamat email akun

Alamat email yang digunakan untuk tiap peran:

- Admin
email :`admin@gmail.com`
password : 12345678
- Penjual 
email : `warungrisol@gmail.com`
password : warungrisol
- Pembeli: `nashwaashaf@gmail.com`
password : nashwa123

Daftar ini hanya mendokumentasikan alamat akun; file SQL tidak membuat akun
tersebut. Pastikan akun sudah terdaftar atau dibuat secara terpisah sebelum
mencoba masuk.

## Alur lengkap ReTaste

### 1. Persiapan dan akun

1. Jalankan aplikasi dan database mengikuti langkah XAMPP di atas.
2. Pembeli dan penjual mendaftar melalui aplikasi. Pendaftaran menyimpan password dalam bentuk hash di `users`; akun admin dibuat terpisah oleh administrator database terpercaya.
3. Pengguna masuk dengan email dan password. Aplikasi mengarahkan tiap peran ke dashboard yang sesuai. Akun nonaktif tidak dapat masuk.

### 2. Penjual dan toko

1. Penjual mengisi profil toko, termasuk nama, kategori usaha, alamat, jam
   operasional, nomor telepon, dan foto, lalu mengirimkannya untuk ditinjau.
2. Toko berstatus menunggu sampai admin menyetujui atau menolak pengajuan.
   Jika ditolak, penjual dapat memperbaiki profil dan mengajukannya kembali.
3. Setelah toko aktif, penjual membuat paket makanan dengan detail menu atau
   paket kejutan, harga, stok/porsi, foto, informasi alergen dan keamanan
   makanan, serta jadwal pengambilan. Paket yang tersedia tampil kepada pembeli.
4. Penjual memantau pesanan masuk dan memeriksa kode pengambilan pembeli saat
   serah terima. Pesanan yang berhasil diverifikasi berubah menjadi selesai.
5. Penjual dapat membeli langganan Rejeki Pro. Pembayaran langganan saat ini
   merupakan simulasi; paket berbayar memberi ketentuan komisi dan fitur
   visibilitas sesuai pengaturan layanan.

### 3. Pembeli dan pesanan

1. Pembeli melihat toko dan paket yang tersedia, memfilter pilihan, dan dapat
   menyimpan toko sebagai favorit.
2. Secara opsional, pembeli mengajukan verifikasi mahasiswa dengan mengunggah
   bukti SKS/KRS. Admin meninjau pengajuan; diskon mahasiswa hanya berlaku
   setelah status verifikasi disetujui dan selama persetujuan terbaru masih aktif.
3. Pembeli memilih paket dan jumlah. Sistem memeriksa stok, batas jumlah,
   jadwal pemesanan, dan status toko, lalu membuat pesanan berstatus menunggu
   pembayaran serta menahan stok.
4. Pembeli membayar sebelum batas waktu. Metode QRIS, e-wallet, dan transfer
   bank yang tersedia saat ini adalah simulasi, bukan pemrosesan uang sungguhan.
   Setelah pembayaran simulasi berhasil, pesanan menjadi siap diambil dan
   pembeli menerima kode pengambilan.
5. Pembeli datang pada jadwal pengambilan dan menunjukkan kode kepada penjual.
   Penjual memverifikasi kode dalam dashboard; pesanan berubah menjadi selesai.
6. Setelah selesai, pembeli dapat memberi rating/ulasan dan mengirim laporan
   terkait pesanan. Laporan pesanan dapat ditinjau admin.

Pesanan yang belum dibayar dapat dibatalkan pembeli; jika batas pembayaran
terlewati, pesanan kedaluwarsa dan stok dikembalikan. Pesanan yang tidak
diambil sampai jadwal berakhir ditandai tidak diambil. Laporan terhadap akun
pengguna (bukan laporan atas pesanan) belum disimpan karena skema saat ini
belum menyediakan tabelnya.

### 4. Tugas admin

Admin mengelola pengajuan toko dan verifikasi mahasiswa, kategori serta
pengaturan layanan, akun dan toko, dan laporan atas pesanan. Admin dapat
menyetujui/menolak pengajuan, memberi peringatan, atau menangguhkan akun, toko,
dan paket. Perubahan status disimpan di database dan ditampilkan pada dashboard
terkait.

Login and registration use MySQL. The buyer, seller, and admin dashboards
require an authenticated account and load their initial data from the same
connection configured in `koneksi.php`. Admin shop verification, account/shop
suspension, report moderation, category changes, and system settings are sent
to `api_admin.php` and persisted in MySQL. Shop profiles use the written
address; sellers do not need to enter latitude or longitude.

Account reports are not persisted because the current schema has no account
reports table. The dashboard does not save account-report actions as browser
state.
