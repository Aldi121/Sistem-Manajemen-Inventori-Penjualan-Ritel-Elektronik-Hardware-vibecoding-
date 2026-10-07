-- Jalankan sesudah tabel db_ritel_pplg1_kel5 tersedia.
-- Migrasi Laravel juga memasang ulang objek berikut pada koneksi MySQL/MariaDB.

DROP TRIGGER IF EXISTS trg_diskon_grosir;
DROP TRIGGER IF EXISTS trg_potong_stok;
DROP TRIGGER IF EXISTS trg_pemulihan_stok;
DROP PROCEDURE IF EXISTS sp_cek_ketersediaan_stok;

DELIMITER //

CREATE PROCEDURE sp_cek_ketersediaan_stok(IN p_id_barang VARCHAR(8), IN p_jumlah INT)
BEGIN
    DECLARE v_stok INT DEFAULT NULL;
    SELECT stok INTO v_stok FROM tb_barang WHERE id_barang = p_id_barang;

    IF v_stok IS NULL THEN
        SELECT 'BARANG TIDAK DITEMUKAN' AS pesan;
    ELSEIF v_stok >= p_jumlah THEN
        SELECT 'STOK TERSEDIA' AS pesan;
    ELSE
        SELECT CONCAT('STOK TIDAK CUKUP. STOK TERSEDIA: ', v_stok) AS pesan;
    END IF;
END//

CREATE TRIGGER trg_diskon_grosir
BEFORE INSERT ON tb_penjualan
FOR EACH ROW
BEGIN
    IF NEW.jumlah_beli >= 50 THEN
        SET NEW.total_bayar = FLOOR(NEW.total_bayar * 90 / 100);
    END IF;
END//

CREATE TRIGGER trg_potong_stok
AFTER INSERT ON tb_penjualan
FOR EACH ROW
BEGIN
    UPDATE tb_barang
    SET stok = stok - NEW.jumlah_beli
    WHERE id_barang = NEW.id_barang;

    INSERT INTO tb_log_inventori (nama_operasi, rincian, waktu_eksekusi)
    VALUES (
        'POTONG STOK',
        CONCAT('Barang ', NEW.id_barang, ' berkurang sebanyak ', NEW.jumlah_beli),
        NOW()
    );
END//

CREATE TRIGGER trg_pemulihan_stok
AFTER DELETE ON tb_penjualan
FOR EACH ROW
BEGIN
    UPDATE tb_barang
    SET stok = stok + OLD.jumlah_beli
    WHERE id_barang = OLD.id_barang;

    INSERT INTO tb_log_inventori (nama_operasi, rincian, waktu_eksekusi)
    VALUES (
        'PEMULIHAN STOK',
        CONCAT('Barang ', OLD.id_barang, ' dikembalikan sebanyak ', OLD.jumlah_beli),
        NOW()
    );
END//

DELIMITER ;

-- Contoh:
-- CALL sp_cek_ketersediaan_stok('BRG00001', 2);
