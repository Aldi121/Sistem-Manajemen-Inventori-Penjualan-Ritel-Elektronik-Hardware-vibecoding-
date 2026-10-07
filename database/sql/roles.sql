-- Jalankan sebagai pengguna MariaDB yang memiliki hak CREATE USER dan GRANT.
-- Ganti semua nilai CHANGE_ME sebelum digunakan. Akun ini hanya untuk demo CLI DCL;
-- aplikasi Laravel memakai satu akun layanan dan membatasi akses lewat role aplikasi.

CREATE USER IF NOT EXISTS 'admin_ritel'@'localhost' IDENTIFIED BY 'CHANGE_ME_ADMIN';
CREATE USER IF NOT EXISTS 'admin_barang'@'localhost' IDENTIFIED BY 'CHANGE_ME_INVENTORY';
CREATE USER IF NOT EXISTS 'kasir_ritel'@'localhost' IDENTIFIED BY 'CHANGE_ME_CASHIER';

GRANT ALL PRIVILEGES ON db_ritel_pplg1_kel5.* TO 'admin_ritel'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE ON db_ritel_pplg1_kel5.tb_barang TO 'admin_barang'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_ritel_pplg1_kel5.tb_kategori TO 'admin_barang'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON db_ritel_pplg1_kel5.tb_distributor TO 'admin_barang'@'localhost';

GRANT SELECT, INSERT ON db_ritel_pplg1_kel5.tb_penjualan TO 'kasir_ritel'@'localhost';
GRANT SELECT ON db_ritel_pplg1_kel5.tb_pelanggan TO 'kasir_ritel'@'localhost';
GRANT SELECT ON db_ritel_pplg1_kel5.tb_barang TO 'kasir_ritel'@'localhost';

FLUSH PRIVILEGES;

-- Contoh verifikasi stored procedure:
-- CALL sp_cek_ketersediaan_stok('BRG00001', 2);
