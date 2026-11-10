CREATE TABLE IF NOT EXISTS verifikasi_mahasiswa (
  id_verifikasi       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_user             INT UNSIGNED NOT NULL,
  file_bukti          VARCHAR(100) NOT NULL,
  status              ENUM('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  catatan_admin       VARCHAR(500) NULL,
  ditinjau_oleh       INT UNSIGNED NULL,
  ditinjau_pada       DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_verifikasi),
  KEY idx_verifikasi_user (id_user, id_verifikasi),
  KEY idx_verifikasi_status (status, created_at),
  CONSTRAINT fk_verifikasi_user FOREIGN KEY (id_user) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_verifikasi_admin FOREIGN KEY (ditinjau_oleh) REFERENCES users (id_user) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE users
SET email_kampus_terverifikasi_pada = NULL,
    token_email_kampus = NULL,
    token_email_kampus_dibuat_pada = NULL
WHERE email_kampus_terverifikasi_pada IS NOT NULL
   OR token_email_kampus IS NOT NULL
   OR token_email_kampus_dibuat_pada IS NOT NULL;
