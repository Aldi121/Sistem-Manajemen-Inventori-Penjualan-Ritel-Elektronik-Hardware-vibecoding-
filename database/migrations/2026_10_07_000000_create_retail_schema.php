<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $createdProducts = false;
        $createdSales = false;

        if (! Schema::hasTable('tb_kategori')) {
            Schema::create('tb_kategori', function (Blueprint $table): void {
                $table->string('id_kategori', 8)->primary();
                $table->string('nama_kategori', 30);
            });
        }

        if (! Schema::hasTable('tb_distributor')) {
            Schema::create('tb_distributor', function (Blueprint $table): void {
                $table->string('id_distributor', 8)->primary();
                $table->string('nama_distributor', 50);
                $table->string('kota_asal', 30);
                $table->string('kontak', 15);
            });
        }

        if (! Schema::hasTable('tb_barang')) {
            Schema::create('tb_barang', function (Blueprint $table): void {
                $table->string('id_barang', 8)->primary();
                $table->string('id_kategori', 8);
                $table->string('id_distributor', 8);
                $table->string('nama_barang', 50);
                $table->integer('harga_satuan');
                $table->integer('stok');
                $table->foreign('id_kategori')->references('id_kategori')->on('tb_kategori');
                $table->foreign('id_distributor')->references('id_distributor')->on('tb_distributor');
            });
            $createdProducts = true;
        }

        if (! Schema::hasTable('tb_pelanggan')) {
            Schema::create('tb_pelanggan', function (Blueprint $table): void {
                $table->string('id_pelanggan', 8)->primary();
                $table->string('nama_pelanggan', 50);
                $table->char('jenis_kelamin', 1);
                $table->string('alamat', 100);
                $table->date('tanggal_daftar');
            });
        }

        if (! Schema::hasTable('tb_penjualan')) {
            Schema::create('tb_penjualan', function (Blueprint $table): void {
                $table->string('id_penjualan', 8)->primary();
                $table->string('id_pelanggan', 8);
                $table->string('id_barang', 8);
                $table->date('tanggal_jual');
                $table->integer('jumlah_beli');
                $table->integer('total_bayar');
                $table->string('status_transaksi', 15);
                $table->foreign('id_pelanggan')->references('id_pelanggan')->on('tb_pelanggan');
                $table->foreign('id_barang')->references('id_barang')->on('tb_barang');
            });
            $createdSales = true;
        }

        if (! Schema::hasTable('tb_log_inventori')) {
            Schema::create('tb_log_inventori', function (Blueprint $table): void {
                $table->increments('id_log');
                $table->string('nama_operasi', 30);
                $table->string('rincian', 255);
                $table->dateTime('waktu_eksekusi');
            });
        }

        if (! Schema::hasTable('tb_pengguna')) {
            Schema::create('tb_pengguna', function (Blueprint $table): void {
                $table->string('id_user', 8)->primary();
                $table->string('username', 30)->unique();
                $table->string('password', 60);
                $table->string('nama_lengkap', 50);
                $table->enum('role', ['ADMIN', 'ADMIN_BARANG', 'KASIR']);
            });
        }

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            if ($createdProducts) {
                DB::statement('ALTER TABLE tb_barang ADD CONSTRAINT chk_stok CHECK (stok >= 0)');
            }
            if ($createdSales) {
                DB::statement('ALTER TABLE tb_penjualan ADD CONSTRAINT chk_jumlah_beli CHECK (jumlah_beli > 0)');
            }

            DB::unprepared('DROP TRIGGER IF EXISTS trg_diskon_grosir');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_potong_stok');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_pemulihan_stok');

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER trg_diskon_grosir
                BEFORE INSERT ON tb_penjualan
                FOR EACH ROW
                BEGIN
                    IF NEW.jumlah_beli >= 50 THEN
                        SET NEW.total_bayar = FLOOR(NEW.total_bayar * 90 / 100);
                    END IF;
                END
            SQL);

            DB::unprepared(<<<'SQL'
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
                END
            SQL);

            DB::unprepared(<<<'SQL'
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
                END
            SQL);

            DB::unprepared('DROP PROCEDURE IF EXISTS sp_cek_ketersediaan_stok');
            DB::unprepared(<<<'SQL'
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
                END
            SQL);
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_diskon_grosir');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_potong_stok');
            DB::unprepared('DROP TRIGGER IF EXISTS trg_pemulihan_stok');
            DB::unprepared('DROP PROCEDURE IF EXISTS sp_cek_ketersediaan_stok');
        }

        Schema::dropIfExists('tb_penjualan');
        Schema::dropIfExists('tb_barang');
        Schema::dropIfExists('tb_log_inventori');
        Schema::dropIfExists('tb_pelanggan');
        Schema::dropIfExists('tb_distributor');
        Schema::dropIfExists('tb_kategori');
        Schema::dropIfExists('tb_pengguna');
    }
};
